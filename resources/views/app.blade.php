<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Ícone e nome ao "Adicionar à Tela de Início" (logo da oficina ou "G"). ?v= muda quando o logo muda. --}}
    @php($versaoIcone = \App\Models\Oficina::atual()->versaoIcone())
    <link rel="apple-touch-icon" href="/icone/180.png?v={{ $versaoIcone }}">
    <link rel="icon" type="image/png" href="/icone/192.png?v={{ $versaoIcone }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="apple-mobile-web-app-title" content="Gestoque">
    <meta name="application-name" content="Gestoque">
    {{-- Aberto pela tela inicial, roda em tela cheia, sem a barra do Safari. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#FFFFFF">
    {{-- Vigia: se o sistema não montar (ex.: erro só no modo "app" do iPhone), mostra o motivo e um botão em vez de tela branca. --}}
    <script>
        (function () {
            var erros = [];
            window.addEventListener('error', function (e) {
                var alvo = e.target && (e.target.src || e.target.href);
                erros.push(alvo ? 'Não carregou: ' + alvo : (e.message || 'Erro') + (e.filename ? ' (' + e.filename + ':' + e.lineno + ')' : ''));
            }, true);
            window.addEventListener('unhandledrejection', function (e) {
                erros.push('Promessa: ' + (e.reason && (e.reason.stack || e.reason.message) || e.reason));
            });
            setTimeout(function () {
                var app = document.getElementById('app');
                if (!app || app.children.length > 0) return;
                var modoApp = window.navigator.standalone || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
                app.innerHTML =
                    '<div style="min-height:100vh;background:#171918;color:#fff;font-family:system-ui,sans-serif;padding:32px 20px;box-sizing:border-box">' +
                    '<div style="font-size:22px;font-weight:700;margin-bottom:8px">O sistema não abriu</div>' +
                    '<div style="color:#9A9D9B;font-size:14px;margin-bottom:20px">Verifique a internet e tente de novo.</div>' +
                    '<button onclick="location.reload()" style="background:#C9281F;color:#fff;border:0;border-radius:8px;padding:12px 20px;font-size:16px;font-weight:600">Tentar de novo</button>' +
                    '<pre id="gestoque-erro" style="margin-top:28px;white-space:pre-wrap;word-break:break-all;color:#9A9D9B;font-size:11px"></pre></div>';
                document.getElementById('gestoque-erro').textContent =
                    'Detalhes (mande um print para o suporte):\n' +
                    (erros.length ? erros.join('\n') : 'Nenhum erro registrado.') +
                    '\nEndereço: ' + location.href +
                    '\nModo app: ' + (modoApp ? 'sim' : 'não') +
                    '\n' + navigator.userAgent;
            }, 6000);
        })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>