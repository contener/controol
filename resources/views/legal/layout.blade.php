<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') — Controool</title>
    <meta name="description" content="@yield('description')">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32.png">
    {{-- Pages légales publiques (exigées par Google Play) : autonomes, sans le bundle
         Vite/Inertia, pour rester lisibles par les robots de vérification. --}}
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f5f7fb; color: #0f172a; line-height: 1.65; }
        header { background: linear-gradient(135deg, #1d4ed8, #0ea5e9); color: #fff; padding: 40px 20px 56px; }
        header .contenu, main { max-width: 820px; margin: 0 auto; }
        header a { color: #fff; text-decoration: none; font-weight: 700; font-size: 18px; }
        header h1 { margin: 18px 0 6px; font-size: 30px; line-height: 1.2; }
        header p { margin: 0; color: #dbeafe; }
        main { background: #fff; margin-top: -28px; border-radius: 18px; padding: 32px 28px; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        h2 { font-size: 19px; margin: 30px 0 8px; color: #1d4ed8; }
        h2:first-child { margin-top: 0; }
        ul { padding-left: 22px; }
        li { margin: 4px 0; }
        .encart { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 14px 16px; margin: 16px 0; }
        .alerte { background: #fef2f2; border-color: #fecaca; }
        a { color: #1d4ed8; }
        footer { text-align: center; color: #64748b; font-size: 13px; padding: 28px 20px 40px; }
        @media (max-width: 600px) { main { padding: 24px 18px; border-radius: 0; margin-top: 0; } header { padding-bottom: 32px; } header h1 { font-size: 25px; } }
    </style>
</head>
<body>
    <header>
        <div class="contenu">
            <a href="{{ url('/') }}">Controool</a>
            <h1>@yield('titre')</h1>
            <p>Dernière mise à jour : 14 novembre 2026</p>
        </div>
    </header>
    <main>
        @yield('contenu')
    </main>
    <footer>
        <a href="{{ route('legal.confidentialite') }}">Politique de confidentialité</a> ·
        <a href="{{ route('legal.suppression-compte') }}">Supprimer son compte</a> ·
        Contact : <a href="mailto:{{ config('app.contact_email') }}">{{ config('app.contact_email') }}</a>
    </footer>
</body>
</html>
