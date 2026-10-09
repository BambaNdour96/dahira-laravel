<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Plateforme officielle des membres de la Daara Nouroud Daareyni Kaolack : inscription, carte de membre, cotisations et vie de la Daara.')">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e5223">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <meta name="theme-color" content="#1e5223">
    <title>@yield('titre', 'Accueil') – Daara Nouroud Daareyni Kaolack</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ndk.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            let choix = 'auto';
            try { choix = localStorage.getItem('ndk-theme') || 'auto'; } catch (e) {}
            const sombre = choix === 'sombre' || (choix === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', sombre);
        })();
    </script>
</head>
<body class="flex min-h-screen flex-col bg-stone-50 text-stone-800 antialiased">
    @include('partials.bandeau-en-tant-que')

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
            <nav class="flex flex-wrap items-center justify-end gap-1 text-sm">
                @auth
                    @if (auth()->user()->hasAnyRole(\App\Services\CompteBureauService::ROLES_BUREAU))
                        <a href="/admin" class="rounded-lg bg-or-500 px-3 py-2 font-semibold text-emerald-950 hover:bg-or-400">Panneau du bureau</a>
                    @endif
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
        <div id="installer-app" hidden class="mb-4 flex justify-end">
            <button type="button" id="bouton-installer" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900 ring-1 ring-emerald-200 hover:bg-emerald-100">📲 Installer l'application</button>
        </div>
        @if (session('succes'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                {{ session('succes') }}
            </div>
        @endif

        @yield('contenu')
    </main>

    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-2 px-4 py-6 text-center text-xs text-stone-500">
            <button type="button" id="bouton-theme" class="rounded-full px-3 py-1 text-xs font-medium text-stone-600 ring-1 ring-stone-200 hover:bg-stone-100">Thème : automatique</button>
            <p class="font-medium text-stone-700">Daara Nouroud Daareyni Kaolack</p>
            <a href="{{ route('confidentialite') }}" class="underline hover:text-emerald-800">Données personnelles</a>
        </div>
    </footer>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
        }

        let invitation = null;
        const bloc = document.getElementById('installer-app');

        window.addEventListener('beforeinstallprompt', (evenement) => {
            evenement.preventDefault();
            invitation = evenement;
            bloc?.removeAttribute('hidden');
        });

        document.getElementById('bouton-installer')?.addEventListener('click', async () => {
            if (!invitation) return;
            invitation.prompt();
            await invitation.userChoice;
            invitation = null;
            bloc?.setAttribute('hidden', '');
        });
    </script>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const proprietaire = {{ \Illuminate\Support\Js::from(auth()->check() ? substr(hash('sha256', auth()->id().config('app.key')), 0, 16) : null) }};

            try {
                if (localStorage.getItem('ndk-proprietaire') !== proprietaire) {
                    if ('caches' in window) {
                        caches.delete('ndk-carte');
                    }

                    if (proprietaire) {
                        localStorage.setItem('ndk-proprietaire', proprietaire);
                    } else {
                        localStorage.removeItem('ndk-proprietaire');
                    }
                }
            } catch (erreur) {
                // Navigateur sans stockage : rien à effacer.
            }
        })();
    </script>
@auth
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" id="inactivite-retour">
        (() => {
            const limite = {{ \App\Http\Middleware\ExpirationInactivite::minutesPour(auth()->user()) }} * 60 * 1000;
            const ouverture = Date.now();
            const verifier = () => { if (Date.now() - ouverture > limite) { location.reload(); } };
            document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { verifier(); } });
            setInterval(verifier, 30000);
        })();
    </script>
@endauth
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}" id="changer-theme">
    (() => {
        const bouton = document.getElementById('bouton-theme');
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const libelles = { auto: 'automatique', clair: 'clair', sombre: 'sombre' };
        const suivant = { auto: 'clair', clair: 'sombre', sombre: 'auto' };
        const lire = () => { try { return localStorage.getItem('ndk-theme') || 'auto'; } catch (e) { return 'auto'; } };
        const appliquer = (choix) => {
            const sombre = choix === 'sombre' || (choix === 'auto' && media.matches);
            document.documentElement.classList.toggle('dark', sombre);
            if (bouton) { bouton.textContent = 'Thème : ' + libelles[choix]; }
        };
        appliquer(lire());
        bouton?.addEventListener('click', () => {
            const choix = suivant[lire()] || 'auto';
            try { localStorage.setItem('ndk-theme', choix); } catch (e) {}
            appliquer(choix);
        });
        media.addEventListener('change', () => appliquer(lire()));
    })();
</script>
</body>
</html>
