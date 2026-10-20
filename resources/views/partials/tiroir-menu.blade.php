@auth
    @php
        $membreDuMenu = auth()->user()->membre;
        $valide = $membreDuMenu !== null && $membreDuMenu->statut === \App\Enums\StatutMembre::Valide;
        $liens = array_filter([
            ['🏠', 'Accueil', 'espace', true],
            ['🪪', 'Ma carte de membre', 'carte', $valide],
            ['💳', 'Mes cotisations', 'cotisations', $valide],
            ['🧾', 'Mes reçus', 'recus.index', $valide],
            ['🤲', 'Mes Sass et Jàyante', 'engagements.index', $valide],
            ['📅', 'Mes participations', 'evenements.index', $valide],
            ['👨‍👩‍👧', 'Ma famille', 'famille.index', $valide && $membreDuMenu->responsable_id === null],
            ['🤝', 'Payer pour un membre', 'autrui.show', $valide],
            ['🙋', 'Se porter volontaire', 'volontariat.index', $valide],
            ['📖', 'Souhaitent apprendre l\'arabe', 'recensement.apprenants', auth()->check() && ! app(\App\Services\ArabeCoranService::class)->peutVoirRecensement(auth()->user()) && app(\App\Services\ArabeCoranService::class)->peutVoirApprenants(auth()->user())],
            ['🏛️', 'Mes comités', 'comites.index', $membreDuMenu !== null && \App\Models\Instance::query()->where('statut', 'active')->whereHas('membres', fn ($query) => $query->whereKey($membreDuMenu->id))->exists()],
            ['💰', 'Ma caisse', 'caisse.index', auth()->check() && auth()->user()->can('caisse.tenir')],
            ['📋', 'Pointage', 'pointage.index', auth()->check() && app(\App\Services\PointageService::class)->rendezVousDuMoment(auth()->user())->isNotEmpty()],
            ['🔔', 'Mes notifications', 'notifications.index', true],
            ['⚙️', 'Paramètres', 'parametres', true],
        ], fn (array $lien): bool => $lien[3] && \Illuminate\Support\Facades\Route::has($lien[2]));
    @endphp

    <div id="voile-menu" class="fixed inset-0 z-40 hidden bg-black/40"></div>

    <aside id="tiroir-menu" aria-hidden="true" aria-label="Menu"
           class="fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] -translate-x-full flex-col bg-white shadow-xl transition-transform duration-300 ease-out">
        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <div class="min-w-0">
                <p class="truncate font-semibold text-emerald-900">{{ auth()->user()->name }}</p>
                @if ($membreDuMenu?->matricule)
                    <p class="text-xs text-stone-500">{{ $membreDuMenu->matricule }}</p>
                @endif
            </div>
            <button type="button" id="fermer-menu" aria-label="Fermer"
                    class="rounded-full px-2 text-2xl leading-none text-stone-500 hover:bg-stone-100">×</button>
        </div>

        <nav class="flex-1 overflow-y-auto py-2">
            @foreach ($liens as [$icone, $libelle, $route])
                <a href="{{ route($route) }}"
                   class="flex items-center gap-3 px-4 py-3 text-sm font-medium {{ request()->routeIs($route) ? 'bg-emerald-50 text-emerald-900' : 'text-stone-700 hover:bg-stone-100' }}">
                    <span aria-hidden="true">{{ $icone }}</span> {{ $libelle }}
                </a>
            @endforeach
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

            // Gestes : glisser depuis le bord gauche ouvre le menu, depuis le bord droit ouvre les notifications.
            const notifications = document.getElementById('tiroir-notifications');
            const menuOuvert = () => !tiroir.classList.contains('-translate-x-full');
            const notificationsOuvertes = () => notifications !== null && !notifications.classList.contains('translate-x-full');
            const BORD = 30;
            const DISTANCE = 60;
            let depart = null;

            document.addEventListener('touchstart', (evenement) => {
                const doigt = evenement.touches[0];
                depart = { x: doigt.clientX, y: doigt.clientY };
            }, { passive: true });

            document.addEventListener('touchend', (evenement) => {
                if (depart === null) {
                    return;
                }

                const doigt = evenement.changedTouches[0];
                const dx = doigt.clientX - depart.x;
                const dy = doigt.clientY - depart.y;
                const largeur = window.innerWidth;
                const debut = depart;
                depart = null;

                if (Math.abs(dy) > Math.abs(dx) || Math.abs(dx) < DISTANCE) {
                    return;
                }

                if (menuOuvert() && dx < 0) {
                    fermer();
                } else if (notificationsOuvertes() && dx > 0) {
                    document.getElementById('voile-notifications')?.click();
                } else if (! menuOuvert() && ! notificationsOuvertes() && debut.x <= BORD && dx > 0) {
                    ouvrir();
                } else if (! menuOuvert() && ! notificationsOuvertes() && debut.x >= largeur - BORD && dx < 0) {
                    document.getElementById('ouvrir-notifications')?.click();
                }
            }, { passive: true });
        })();
    </script>
@endauth