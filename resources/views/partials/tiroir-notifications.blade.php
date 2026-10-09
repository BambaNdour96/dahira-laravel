@auth
    <div id="voile-notifications" class="fixed inset-0 z-40 hidden bg-black/40"></div>

    <aside id="tiroir-notifications" aria-hidden="true" aria-label="Mes notifications"
           class="fixed inset-y-0 right-0 z-50 flex w-80 max-w-[85vw] translate-x-full flex-col bg-white shadow-xl transition-transform duration-300 ease-out">
        <div class="flex items-center justify-between border-b border-stone-200 px-4 py-3">
            <h2 class="font-semibold text-emerald-900">Notifications</h2>
            <button type="button" id="fermer-notifications" aria-label="Fermer"
                    class="rounded-full px-2 text-2xl leading-none text-stone-500 hover:bg-stone-100">×</button>
        </div>

        <div class="flex-1 space-y-3 overflow-y-auto p-3">
            @forelse ($notificationsMembre ?? [] as $notification)
                <div class="rounded-xl p-3 text-sm {{ $notification->read_at === null ? 'bg-or-100 ring-1 ring-or-400' : 'bg-stone-50 ring-1 ring-stone-200' }}">
                    <p class="font-semibold text-emerald-950">{{ $notification->data['titre'] ?? '' }}</p>
                    <p class="mt-1 text-stone-700">{{ $notification->data['message'] ?? '' }}</p>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <span class="text-xs text-stone-500">{{ $notification->created_at?->diffForHumans() }}</span>
                        @if (! empty($notification->data['lien']))
                            <a href="{{ \App\Notifications\NotificationMembre::chemin($notification->data['lien']) }}"
                               class="shrink-0 rounded-lg bg-emerald-800 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-900">
                                {{ $notification->data['libelle_lien'] ?? 'Ouvrir' }}
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-4 text-center text-sm text-stone-500">Aucune notification pour le moment.</p>
            @endforelse
        </div>

        <a href="{{ route('notifications.index') }}"
           class="border-t border-stone-200 px-4 py-3 text-center text-sm font-medium text-emerald-800 underline">
            Voir toutes mes notifications
        </a>
    </aside>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const tiroir = document.getElementById('tiroir-notifications');
            const voile = document.getElementById('voile-notifications');
            const bouton = document.getElementById('ouvrir-notifications');

            if (!tiroir || !voile || !bouton) {
                return;
            }

            const ouvrir = () => {
                tiroir.classList.remove('translate-x-full');
                tiroir.setAttribute('aria-hidden', 'false');
                voile.classList.remove('hidden');
                bouton.setAttribute('aria-expanded', 'true');

                const badge = document.getElementById('badge-notifications');
                if (badge) {
                    fetch(@js(route('notifications.lues')), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
                    }).then(() => badge.remove()).catch(() => {});
                }
            };

            const fermer = () => {
                tiroir.classList.add('translate-x-full');
                tiroir.setAttribute('aria-hidden', 'true');
                voile.classList.add('hidden');
                bouton.setAttribute('aria-expanded', 'false');
            };

            bouton.addEventListener('click', ouvrir);
            voile.addEventListener('click', fermer);
            document.getElementById('fermer-notifications')?.addEventListener('click', fermer);
            document.addEventListener('keydown', (evenement) => {
                if (evenement.key === 'Escape') {
                    fermer();
                }
            });
        })();
    </script>
@endauth