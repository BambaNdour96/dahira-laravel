<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Accès non autorisé – Daara Nouroud Daareyni Kaolack</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ndk.png') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-stone-50 px-4 text-stone-800 antialiased">
    <main class="w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-stone-200">
        <img src="{{ asset('images/logo-ndk.png') }}" alt="" class="mx-auto h-20 w-20 rounded-full object-cover ring-2 ring-or-400">

        <h1 class="mt-5 text-xl font-semibold text-emerald-900">Accès non autorisé</h1>

        @auth
            <p class="mt-3 text-sm text-stone-600">
                Vous êtes connecté avec le compte de <strong>{{ auth()->user()->name }}</strong>,
                qui n'a pas accès à cette page.
            </p>
            <p class="mt-2 text-sm text-stone-600">
                Pour utiliser un autre compte, par exemple celui du bureau, déconnectez-vous d'abord.
            </p>

            <div class="mt-6 flex flex-col gap-3">
                <a href="{{ route('espace') }}"
                   class="rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
                    Retour à mon compte
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-lg border border-stone-300 px-4 py-3 font-semibold text-stone-700 hover:bg-stone-50">
                        Changer de compte
                    </button>
                </form>
            </div>
        @else
            <p class="mt-3 text-sm text-stone-600">Cette page est réservée. Connectez-vous avec un compte autorisé.</p>
            <a href="{{ route('login') }}"
               class="mt-6 inline-block rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
                Se connecter
            </a>
        @endauth
    </main>
</body>
</html>
