<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$config = require __DIR__ . '/config.php';
session_name($config['session_name']);
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_start();

function respond(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function input_json(): array {
    $raw=file_get_contents('php://input');
    $data=json_decode($raw ?: '{}', true);
    return is_array($data)?$data:[];
}
function xtream_request(string $action, array $params=[]): mixed {
    global $config;
    $base=rtrim((string)($config['xtream_base_url'] ?? ''), '/');
    if (!$base || !filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['https','http'], true)) {
        throw new RuntimeException('O administrador precisa configurar xtream_base_url em config.php.');
    }
    $query=array_merge([
        'username'=>$_SESSION['xtream_username'],
        'password'=>$_SESSION['xtream_password'],
    ], $params);
    if ($action !== '') $query['action']=$action;
    $url=$base.'/player_api.php?'.http_build_query($query);
    $context=stream_context_create(['http'=>[
        'timeout'=>18,
        'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\nUser-Agent: CineJeffPlayer/1.0\r\n"
    ]]);
    $raw=@file_get_contents($url, false, $context);
    if ($raw===false) throw new RuntimeException('Não foi possível conectar ao servidor. Confira URL, HTTPS e disponibilidade da API.');
    $decoded=json_decode($raw, true);
    if (!is_array($decoded)) throw new RuntimeException('A API retornou uma resposta inválida.');
    return $decoded;
}
function require_login(): void {
    if (empty($_SESSION['xtream_username']) || empty($_SESSION['xtream_password'])) respond(['error'=>'Sua sessão expirou. Entre novamente.'], 401);
}
$action=(string)($_GET['action'] ?? '');
if ($action==='session') respond(['logged_in'=>!empty($_SESSION['xtream_username'])]);
if ($action==='login') {
    if ($_SERVER['REQUEST_METHOD']!=='POST') respond(['error'=>'Método não permitido.'],405);
    $input=input_json();
    $username=trim((string)($input['username'] ?? ''));
    $password=(string)($input['password'] ?? '');
    if ($username==='' || $password==='') respond(['error'=>'Informe usuário e senha.'],422);
    // Nunca registre credenciais em logs. Valida contra a API configurada pelo administrador.
    $_SESSION['xtream_username']=$username;
    $_SESSION['xtream_password']=$password;
    try {
        $result=xtream_request('',[]);
        $userInfo=$result['user_info'] ?? [];
        if ((string)($userInfo['auth'] ?? '0')!=='1' || strtolower((string)($userInfo['status'] ?? 'active'))!=='active') {
            unset($_SESSION['xtream_username'],$_SESSION['xtream_password']);
            respond(['error'=>'Usuário ou senha inválidos, ou conta inativa.'],401);
        }
        session_regenerate_id(true);
        respond(['ok'=>true,'username'=>$username]);
    } catch (Throwable $e) {
        unset($_SESSION['xtream_username'],$_SESSION['xtream_password']);
        respond(['error'=>$e instanceof RuntimeException?$e->getMessage():'Falha ao validar a conta.'],502);
    }
}
if ($action==='logout') {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $p=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']);
    }
    session_destroy();
    respond(['ok'=>true]);
}
require_login();
try {
    $map=[
        'get_live_streams'=>'get_live_streams',
        'get_vod_streams'=>'get_vod_streams',
        'get_series'=>'get_series',
    ];
    if (isset($map[$action])) {
        $result=xtream_request($map[$action]);
        if (!is_array($result) || isset($result['user_info']['auth']) && (string)$result['user_info']['auth']!=='1') respond(['error'=>'A API recusou a solicitação.'],502);
        $type=$action==='get_live_streams'?'live':($action==='get_vod_streams'?'movie':'series');
        $items=[];
        foreach ($result as $item) {
            if (!is_array($item)) continue;
            $items[]=[
                'id'=>$item['stream_id'] ?? $item['series_id'] ?? $item['id'] ?? null,
                'name'=>(string)($item['name'] ?? 'Sem título'),
                'category_id'=>$item['category_id'] ?? null,
                'category_name'=>(string)($item['category_name'] ?? ''),
                'icon'=>(string)($item['stream_icon'] ?? $item['cover'] ?? ''),
                'extension'=>(string)($item['container_extension'] ?? 'mp4'),
                'type'=>$type,
            ];
        }
        respond(['items'=>$items]);
    }
    if ($action==='stream') {
        $id=(string)($_GET['id'] ?? '');
        $type=(string)($_GET['type'] ?? '');
        if (!ctype_digit($id) || !in_array($type,['live','movie','series'],true)) respond(['error'=>'Conteúdo inválido.'],422);
        $base=rtrim((string)$config['xtream_base_url'],'/');
        $user=rawurlencode($_SESSION['xtream_username']);
        $pass=rawurlencode($_SESSION['xtream_password']);
        if ($type==='live') $path="live/$user/$pass/$id.m3u8";
        else {
            $ext=(string)($_GET['extension'] ?? 'mp4');
            if (!preg_match('/^[a-zA-Z0-9]{2,5}$/',$ext)) $ext='mp4';
            $folder=$type==='movie'?'movie':'series';
            $path="$folder/$user/$pass/$id.$ext";
        }
        // A URL de reprodução contém credenciais porque esse é o padrão de muitos servidores Xtream.
        // Para ocultá-las completamente, seria necessário um proxy de mídia dedicado, com custos de banda.
        respond(['url'=>$base.'/'.$path]);
    }
    respond(['error'=>'Ação não reconhecida.'],404);
} catch (Throwable $e) {
    respond(['error'=>'Erro ao consultar a API. Verifique a URL do servidor e a compatibilidade do painel.'],502);
}
