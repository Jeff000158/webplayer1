# CineJeff Web Player — versão inicial

## O que inclui
- Página de login em português.
- Validação no servidor via Xtream-compatible `player_api.php`.
- Listagem básica de TV ao vivo, filmes e séries.
- Pesquisa e favoritos guardados no navegador.
- Reprodução direta por URL do servidor.
- Sessão PHP com cookie HttpOnly e SameSite.

## Requisitos
- Hospedagem com PHP 8.1+.
- Extensão PHP `json` e suporte a HTTPS.
- Servidor Xtream compatível e autorização para usar sua API e os conteúdos.
- HTTPS ativo no domínio.

## Instalação
1. Faça backup do site atual.
2. Envie os arquivos deste pacote para uma pasta de teste, por exemplo `public_html/player/`.
3. Renomeie `config.php` conforme necessário e edite `xtream_base_url` para a URL base do seu servidor autorizado, sem `/player_api.php`.
   Exemplo ilustrativo: `https://painel.seuprovedor.com:443`
4. Não coloque usuário ou senha de cliente neste arquivo.
5. Acesse `https://SEU-DOMINIO/player/` e teste com uma conta autorizada.
6. Confirme que HTTPS funciona tanto no site quanto no servidor de streaming.

## Limitações desta versão
- O guia EPG ainda não foi conectado à interface.
- A listagem de categorias ainda não filtra por categoria.
- Séries são listadas, mas seleção de temporadas/episódios precisa de integração adicional com `get_series_info`.
- Favoritos ficam no navegador usado; não sincronizam entre dispositivos.
- A reprodução direta pode não funcionar em todos os navegadores, formatos ou servidores. HLS costuma funcionar melhor quando o servidor entrega HLS compatível; suporte a MPEG-TS/MP4 varia.
- A URL de reprodução Xtream geralmente contém usuário e senha e ficará visível nas ferramentas de rede do navegador. Para ocultar credenciais no navegador, seria necessário um proxy de mídia dedicado, com requisitos de banda, escala e controle de acesso.
- A autenticação passa pela API do provedor. Nunca use um endereço de servidor fornecido pelo visitante; o endereço é configurado pelo administrador para evitar SSRF.
- Antes de uso público, acrescente rate limiting, proteção contra tentativas repetidas, política de privacidade, monitoramento e testes de segurança.

## Segurança
- Use HTTPS.
- Não compartilhe `config.php` publicamente.
- Não registre URLs completas da API, pois elas contêm credenciais.
- Use apenas contas e conteúdo para os quais você tenha autorização.
