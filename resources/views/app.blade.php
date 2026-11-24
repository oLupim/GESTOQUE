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
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>