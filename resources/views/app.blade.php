<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php $meta = $page['props']['meta'] ?? null; @endphp

        <title inertia>{{ $meta['title'] ?? config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
        <link rel="apple-touch-icon" href="/images/favicon-180.png">

        {{-- Balises Open Graph / Twitter rendues côté serveur (pas de SSR Inertia dans ce
        projet) : indispensable pour que les robots de prévisualisation de WhatsApp,
        Facebook, Telegram... qui n'exécutent pas de JavaScript, affichent une carte de
        partage correcte. Les pages sans prop `meta` (l'essentiel de l'app, derrière
        authentification) n'affichent aucune de ces balises. --}}
        @if($meta)
            <meta name="description" content="{{ $meta['description'] ?? '' }}">
            <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] ?? '' }}">
            <meta property="og:url" content="{{ $meta['url'] ?? url()->current() }}">
            @if(!empty($meta['image']))
                <meta property="og:image" content="{{ $meta['image'] }}">
                <meta name="twitter:card" content="summary_large_image">
            @else
                <meta name="twitter:card" content="summary">
            @endif
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
