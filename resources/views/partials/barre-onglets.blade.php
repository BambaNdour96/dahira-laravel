@auth
    @php
        $membreOnglets = auth()->user()->membre;
        $valideOnglets = $membreOnglets !== null && $membreOnglets->statut === \App\Enums\StatutMembre::Valide;
        $onglets = array_filter([
            ['🏠', 'Accueil', 'espace', true],
            ['🪪', 'Carte', 'carte', $valideOnglets],
            ['💳', 'Payer', 'cotisations', $valideOnglets],
            ['🤲', 'Engagements', 'engagements.index', $valideOnglets],
            ['⚙️', 'Paramètres', 'parametres', ! $valideOnglets],
        ], fn (array $onglet): bool => $onglet[3]);
    @endphp

    {{-- Espace réservé pour que la barre ne cache jamais le bas de la page. --}}
    <div class="h-20 md:hidden" aria-hidden="true"></div>

    <nav id="barre-onglets" aria-label="Navigation principale"
         class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white/95 backdrop-blur md:hidden"
         style="padding-bottom: env(safe-area-inset-bottom);">
        <ul class="mx-auto flex max-w-lg items-stretch justify-around">
            @foreach ($onglets as [$icone, $libelle, $route])
                @php($actif = request()->routeIs($route))
                <li class="flex-1">
                    <a href="{{ route($route) }}" @if ($actif) aria-current="page" @endif
                       class="flex flex-col items-center gap-0.5 px-1 py-2 text-[11px] font-medium {{ $actif ? 'text-emerald-800' : 'text-stone-500' }}">
                        <span class="text-xl leading-none" aria-hidden="true">{{ $icone }}</span>
                        {{ $libelle }}
                    </a>
                </li>
            @endforeach
            <li class="flex-1">
                <button type="button" data-ouvrir-notifications
                        class="flex w-full flex-col items-center gap-0.5 px-1 py-2 text-[11px] font-medium text-stone-500">
                    <span class="text-xl leading-none" aria-hidden="true">🔔</span>
                    Notifications
                </button>
            </li>
        </ul>
    </nav>

    <div id="indicateur-actualiser" aria-hidden="true"
         class="pointer-events-none fixed inset-x-0 top-0 z-50 flex justify-center opacity-0 transition-opacity">
        <span class="mt-3 rounded-full bg-emerald-800 px-4 py-1.5 text-xs font-semibold text-white shadow">↻ Relâchez pour actualiser</span>
    </div>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            // L'onglet « Notifications » ouvre le tiroir des notifications.
            document.querySelector('[data-ouvrir-notifications]')?.addEventListener('click', () => {
                document.getElementById('ouvrir-notifications')?.click();
            });

            // Tirer vers le bas pour actualiser, dans l'application installée.
            const installee = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            const indicateur = document.getElementById('indicateur-actualiser');
            if (!installee || !indicateur) {
                return;
            }

            const tiroirOuvert = () => ['tiroir-menu', 'tiroir-notifications']
                .some((id) => document.getElementById(id)?.getAttribute('aria-hidden') === 'false');
            let depart = null;
            let pret = false;

            document.addEventListener('touchstart', (evenement) => {
                depart = window.scrollY <= 0 && !tiroirOuvert() ? evenement.touches[0].clientY : null;
                pret = false;
            }, { passive: true });

            document.addEventListener('touchmove', (evenement) => {
                if (depart === null) {
                    return;
                }
                pret = evenement.touches[0].clientY - depart > 90;
                indicateur.classList.toggle('opacity-0', !pret);
            }, { passive: true });

            document.addEventListener('touchend', () => {
                indicateur.classList.add('opacity-0');
                if (pret) {
                    location.reload();
                }
                depart = null;
                pret = false;
            }, { passive: true });
        })();
    </script>
@endauth
