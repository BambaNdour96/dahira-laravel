@auth
    @php
        $membreDuMenu = auth()->user()->membre;
        $valide = $membreDuMenu !== null && $membreDuMenu->statut === \App\Enums\StatutMembre::Valide;
        $liens = array_filter([
            ['Mon compte', 'espace', true],
            ['Ma carte de membre', 'carte', $valide],
            ['Mes cotisations', 'cotisations', $valide],
            ['Mes reçus', 'recus.index', $valide],
            ['Mes participations', 'evenements.index', $valide],
            ['Mes notifications', 'notifications.index', true],
            ['Modifier mon profil et ma photo', 'espace.profil', $membreDuMenu !== null],
            ['Changer mon mot de passe', 'mot-de-passe.edit', true],
            ['Télécharger mes données', 'donnees.export', true],
            ['Données personnelles', 'confidentialite', true],
        ], fn (array $lien): bool => $lien[2] && \Illuminate\Support\Facades\Route::has($lien[1]));
    @endphp

    <div id="voile-menu" class="fixed inset-0 z-40 hidden bg-black/40"></div>

    <aside id="tiroir-menu" aria-hidden="true" aria-label="Menu"
           class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] -translate-x-full flex-col bg-white shadow-xl transition-transform duration-300 ease-out">
        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <div>
                <p class="font-semibold text-emerald-900">{{ auth()->user()->name }}</p>
                @if ($membreDuMenu?->matricule)
                    <p class="text-xs text-stone-500">{{ $membreDuMenu->matricule }}</p>
                @endif
            </div>
            <button type="button" id="fermer-menu" aria-label="Fermer"
                    class="rounded-full px-2 text-2xl leading-none text-stone-500 hover:bg-stone-100">×</button>
        </div>

        <nav class="flex-1 overflow-y-auto py-2">
            @foreach ($liens as [$libelle, $route])
                <a href="{{ route($route) }}"
                   class="block px-4 py-3 text-sm font-medium {{ request()->routeIs($route) ? 'bg-emerald-50 text-emerald-900' : 'text-stone-700 hover:bg-stone-100' }}">
                    {{ $libelle }}
                </a>
            @endforeach

            @if (\Illuminate\Support\Facades\Route::has('suppression.create'))
                <a href="{{ route('suppression.create') }}" class="mt-2 block border-t border-stone-200 px-4 py-3 text-sm font-medium text-red-800 hover:bg-stone-100">
                    Supprimer mon compte
                </a>
            @endif
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-200 p-4">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">
                Se déconnecter
            </button>
        </form>
    </aside>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const tiroir = document.getElementById('tiroir-menu');
            const voile = document.getElementById('voile-menu');
            const bouton = document.getElementById('ouvrir-menu');

            if (!tiroir || !voile || !bouton) {
                return;
            }

            const ouvrir = () => {
                tiroir.classList.remove('-translate-x-full');
                tiroir.setAttribute('aria-hidden', 'false');
                voile.classList.remove('hidden');
                bouton.setAttribute('aria-expanded', 'true');
            };

            const fermer = () => {
                tiroir.classList.add('-translate-x-full');
                tiroir.setAttribute('aria-hidden', 'true');
                voile.classList.add('hidden');
                bouton.setAttribute('aria-expanded', 'false');
            };

            bouton.addEventListener('click', ouvrir);
            voile.addEventListener('click', fermer);
            document.getElementById('fermer-menu')?.addEventListener('click', fermer);
            document.addEventListener('keydown', (evenement) => {
                if (evenement.key === 'Escape') {
                    fermer();
                }
            });
        })();
    </script>
@endauth