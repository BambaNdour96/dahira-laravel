<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', 'Accueil') – Dahira Nouroud Dareyni</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    <header class="bg-emerald-900 text-white">
        <div class="mx-auto flex max-w-xl items-center justify-between px-4 py-4">
            <a href="{{ route('accueil') }}" class="font-semibold">Dahira Nouroud Dareyni</a>
            <nav class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('espace') }}" class="hover:underline">Mon espace</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="hover:underline">Déconnexion</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">Se connecter</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-xl px-4 py-8">
        @if (session('succes'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                {{ session('succes') }}
            </div>
        @endif

        @yield('contenu')
    </main>

    <footer class="mx-auto max-w-xl px-4 pb-10 text-center text-xs text-stone-500">
        <a href="{{ route('confidentialite') }}" class="underline">Données personnelles</a>
    </footer>
</body>
</html>
