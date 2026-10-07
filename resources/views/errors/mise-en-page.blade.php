<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titre') – Daara Nouroud Daareyni Kaolack</title>
    <link rel="icon" type="image/png" href="/images/logo-ndk.png">
    {{-- Styles écrits ici, sans dépendance : la page s'affiche même en cas de panne. --}}
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 16px; background: #fafaf9; color: #292524;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
        }
        main {
            width: 100%; max-width: 440px; background: #fff; border-radius: 16px; padding: 32px;
            text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); border: 1px solid #e7e5e4;
        }
        img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; box-shadow: 0 0 0 3px #e2b93b; }
        .code { margin-top: 16px; font-size: 13px; font-weight: 700; letter-spacing: 3px; color: #b3850f; }
        h1 { margin: 6px 0 0; font-size: 22px; color: #1e5223; }
        p { margin: 12px 0 0; font-size: 15px; line-height: 1.5; color: #57534e; }
        .actions { margin-top: 24px; display: flex; flex-direction: column; gap: 10px; }
        a.bouton {
            display: block; padding: 12px 16px; border-radius: 10px; font-weight: 600; text-decoration: none;
            background: #1e5223; color: #fff;
        }
        a.bouton.secondaire { background: #fff; color: #44403c; border: 1px solid #d6d3d1; }
        footer { margin-top: 24px; font-size: 12px; color: #a8a29e; }
    </style>
</head>
<body>
    <main>
        <img src="/images/logo-ndk.png" alt="Logo de la Daara Nouroud Daareyni Kaolack">
        <div class="code">ERREUR @yield('code')</div>
        <h1>@yield('titre')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @yield('actions')
            <a class="bouton" href="/">Retour à l'accueil</a>
        </div>
        <footer>Daara Nouroud Daareyni Kaolack</footer>
    </main>
</body>
</html>
