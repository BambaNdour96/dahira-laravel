<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1e5223">
    <title>@yield('titre', 'Accueil') – Daara Nouroud Daareyni Kaolack</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ndk.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-stone-50 text-stone-800 antialiased">
    <header class="border-b-4 border-or-500 bg-emerald-800 text-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3">
            <a href="{{ route('accueil') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo-ndk.png') }}" alt="Logo de la Daara Nouroud Daareyni Kaolack"
                     class="h-12 w-12 rounded-full bg-white object-cover p-0.5 ring-2 ring-or-400">
                <span class="leading-tight">
                    <span class="block text-sm font-semibold sm:text-base">Daara Nouroud Daareyni</span>
                    <span class="block text-xs tracking-widest text-or-200 uppercase">Kaolack</span>
                </span>
            </a>
            <nav class="flex items-center gap-1 text-sm">
                @auth
                    <a href="{{ route('espace') }}" class="rounded-lg px-3 py-2 hover:bg-white/10">Mon espace</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 hover:bg-white/10">Déconnexion</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg bg-white px-3 py-2 font-semibold text-emerald-900 hover:bg-or-100">Se connecter</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-xl flex-1 px-4 py-8">
        @if (session('succes'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                {{ session('succes') }}
            </div>
        @endif

        @yield('contenu')
    </main>

    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-2 px-4 py-6 text-center text-xs text-stone-500">
            <p class="font-medium text-stone-700">Daara Nouroud Daareyni Kaolack</p>
            <a href="{{ route('confidentialite') }}" class="underline hover:text-emerald-800">Données personnelles</a>
        </div>
    </footer>
</body>
</html>
