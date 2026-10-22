<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
session_name($config['session_name']);
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_start();
$isLoggedIn = !empty($_SESSION['xtream_username']) && !empty($_SESSION['xtream_password']);
$appName = htmlspecialchars($config['app_name'] ?? 'CineJeff', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#090b12">
<title><?= $appName ?> — Web Player</title>
<style>
:root{color-scheme:dark;--bg:#090b12;--panel:#121624;--muted:#9aa3b7;--accent:#7c5cff;--line:#262c3d}
*{box-sizing:border-box}body{margin:0;background:radial-gradient(ellipse at top,#17152e 0,var(--bg) 55%);font:15px system-ui,-apple-system,Segoe UI,sans-serif;color:#f7f8fc;min-height:100vh}
header{display:flex;align-items:center;justify-content:space-between;padding:18px 5vw;border-bottom:1px solid #ffffff12;background:#090b12cc;position:sticky;top:0;backdrop-filter:blur(14px);z-index:2}
.brand{font-size:22px;font-weight:850;letter-spacing:.4px}.brand span{color:var(--accent)}button,.btn{border:0;border-radius:10px;background:var(--accent);color:white;padding:11px 16px;font-weight:700;cursor:pointer}button:disabled{opacity:.55;cursor:wait}
main{max-width:1250px;margin:auto;padding:30px 20px}.panel{background:#121624eF;border:1px solid var(--line);border-radius:18px;padding:24px;box-shadow:0 18px 70px #0005}
.login-wrap{max-width:440px;margin:8vh auto}.sub{color:var(--muted);line-height:1.55}label{display:block;margin:15px 0 7px;color:#c8cce0}input{width:100%;background:#0b0e17;border:1px solid #343b50;border-radius:10px;padding:13px;color:white;font:inherit;outline:none}input:focus{border-color:var(--accent)}
h1{font-size:clamp(26px,4vw,40px);margin:0 0 8px}h2{margin:0 0 14px}.hidden{display:none!important}.error{color:#ff8d9a;margin-top:12px}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.tabs button{background:#1b2031}.tabs button.active{background:var(--accent)}.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}.toolbar input{max-width:380px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}.item{border:1px solid var(--line);background:#171c2b;border-radius:12px;padding:12px;cursor:pointer;min-height:88px}.item:hover{border-color:var(--accent)}.item strong{display:block;overflow-wrap:anywhere}.item small{display:block;color:var(--muted);margin-top:6px}.player{margin:18px 0;background:#000;border-radius:14px;overflow:hidden}.player video{display:block;width:100%;max-height:65vh;background:#000}.status{color:var(--muted);font-size:13px}.empty{padding:25px;color:var(--muted);text-align:center}
@media(max-width:600px){main{padding:20px 12px}.panel{padding:17px}header{padding:14px 16px}}
</style>
</head>
<body>
<header><div class="brand">CINE<span>JEFF</span></div><div id="headerActions"><?php if ($isLoggedIn): ?><button id="logoutBtn" class="hidden">Sair</button><?php endif; ?></div></header>
<main>
<section id="loginSection" class="login-wrap panel <?= $isLoggedIn ? 'hidden' : '' ?>">
  <h1>Entre para assistir</h1>
  <p class="sub">Acesse sua conta para carregar automaticamente os conteúdos disponíveis.</p>
  <form id="loginForm">
    <label for="username">Usuário</label><input id="username" name="username" autocomplete="username" required>
    <label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="current-password" required>
    <button id="loginBtn" type="submit" style="width:100%;margin-top:20px">Entrar no CineJeff</button>
    <div id="loginError" class="error" role="alert"></div>
  </form>
  <p class="status">Use somente credenciais de um serviço que você está autorizado a acessar.</p>
</section>

<section id="appSection" class="<?= $isLoggedIn ? '' : 'hidden' ?>">
  <div class="panel">
    <h1>Seu entretenimento, em um só lugar.</h1>
    <p class="sub" id="welcome">Carregando sua biblioteca…</p>
    <div class="tabs" id="tabs">
      <button data-type="live" class="active">TV ao vivo</button><button data-type="movie">Filmes</button><button data-type="series">Séries</button><button data-type="favorites">Favoritos ★</button>
    </div>
    <div class="toolbar"><input id="search" placeholder="Buscar canal, filme ou série…"><button id="refreshBtn">Atualizar</button></div>
    <div id="status" class="status">Carregando…</div>
    <div id="items" class="grid"></div>
    <div id="empty" class="empty hidden">Nenhum conteúdo para exibir.</div>
  </div>
  <section class="panel player hidden" id="playerPanel">
    <div style="padding:12px 14px;display:flex;justify-content:space-between;gap:10px"><strong id="playingTitle">Reproduzindo</strong><button id="closePlayer" type="button">Fechar</button></div>
    <video id="video" controls playsinline></video>
    <p class="status" style="padding:0 14px 14px">Se o vídeo não iniciar, o formato pode não ser compatível com o navegador ou o servidor pode exigir outro formato.</p>
  </section>
</section>
</main>
<script>
const $ = id => document.getElementById(id);
let currentType='live', allItems=[], favorites=JSON.parse(localStorage.getItem('cinejeff_favorites')||'[]'), account=null;
const actionMap={live:'get_live_streams',movie:'get_vod_streams',series:'get_series'};
async function api(action, extra={}) {
  const response=await fetch('api.php?action='+encodeURIComponent(action), {
    method: action==='login'?'POST':'GET',
    headers: {'Content-Type':'application/json'},
    body: action==='login'?JSON.stringify(extra):undefined,
    credentials:'same-origin'
  });
  const data=await response.json().catch(()=>({error:'Resposta inválida do servidor.'}));
  if(!response.ok || data.error) throw new Error(data.error||'Não foi possível concluir a solicitação.');
  return data;
}
function showLogin(){ $('loginSection').classList.remove('hidden'); $('appSection').classList.add('hidden'); $('logoutBtn')?.classList.add('hidden'); }
function showApp(){ $('loginSection').classList.add('hidden'); $('appSection').classList.remove('hidden'); $('logoutBtn')?.classList.remove('hidden'); }
$('loginForm').addEventListener('submit', async e=>{
  e.preventDefault(); $('loginError').textContent=''; $('loginBtn').disabled=true; $('loginBtn').textContent='Validando…';
  try { account=await api('login',{username:$('username').value.trim(),password:$('password').value}); showApp(); await loadItems(); }
  catch(err){ $('loginError').textContent=err.message; }
  finally { $('loginBtn').disabled=false; $('loginBtn').textContent='Entrar no CineJeff'; }
});
async function loadItems(){
  $('status').textContent='Carregando conteúdo…'; $('items').innerHTML=''; $('empty').classList.add('hidden');
  if(currentType==='favorites'){ render(favorites.map(x=>({...x}))); $('status').textContent='Favoritos salvos neste dispositivo'; return; }
  try {
    const data=await api(actionMap[currentType]);
    allItems=Array.isArray(data.items)?data.items:[];
    $('status').textContent=allItems.length+' itens encontrados';
    render(allItems);
  } catch(err){ $('status').textContent=err.message; $('empty').classList.remove('hidden'); }
}
function render(items){
  const q=$('search').value.toLowerCase();
  const filtered=items.filter(x=>(x.name||'').toLowerCase().includes(q));
  $('items').innerHTML=''; $('empty').classList.toggle('hidden',filtered.length>0);
  filtered.forEach(item=>{
    const card=document.createElement('div'); card.className='item';
    const title=document.createElement('strong'); title.textContent=item.name||'Sem título';
    const meta=document.createElement('small'); meta.textContent=(item.category_name||'Conteúdo')+'  ·  '+(favorites.some(f=>String(f.id)===String(item.id)&&f.type===currentType)?'★ Favorito':'');
    const fav=document.createElement('button'); fav.textContent=favorites.some(f=>String(f.id)===String(item.id)&&f.type===currentType)?'★':'☆'; fav.style.cssText='float:right;padding:4px 8px;background:#292443';
    fav.addEventListener('click',e=>{e.stopPropagation();toggleFavorite(item,currentType);render(currentType==='favorites'?favorites:allItems);});
    card.append(fav,title,meta); card.addEventListener('click',()=>play(item,currentType)); $('items').append(card);
  });
}
function toggleFavorite(item,type){
 const key=String(item.id), index=favorites.findIndex(f=>String(f.id)===key&&f.type===type);
 if(index>=0) favorites.splice(index,1); else favorites.push({...item,type});
 localStorage.setItem('cinejeff_favorites',JSON.stringify(favorites));
}
async function play(item,type){
 try {
   const data=await api('stream',{id:item.id,type,extension:item.extension||''});
   $('playerPanel').classList.remove('hidden'); $('playingTitle').textContent=item.name||'Reproduzindo';
   const video=$('video'); video.pause(); video.src=data.url; video.load();
   video.play().catch(()=>{});
   $('playerPanel').scrollIntoView({behavior:'smooth',block:'start'});
 } catch(err){$('status').textContent=err.message;}
}
document.querySelectorAll('#tabs button').forEach(btn=>btn.addEventListener('click',()=>{
 document.querySelectorAll('#tabs button').forEach(b=>b.classList.remove('active')); btn.classList.add('active'); currentType=btn.dataset.type; loadItems();
}));
$('search').addEventListener('input',()=>render(currentType==='favorites'?favorites:allItems));
$('refreshBtn').addEventListener('click',loadItems);
$('closePlayer').addEventListener('click',()=>{$('video').pause();$('video').removeAttribute('src');$('video').load();$('playerPanel').classList.add('hidden');});
$('logoutBtn')?.addEventListener('click',async()=>{try{await api('logout')}catch(e){} account=null;showLogin();});
(async()=>{try{const s=await api('session');if(s.logged_in){showApp();await loadItems();}else showLogin();}catch(e){showLogin();}})();
</script>
</body></html>
