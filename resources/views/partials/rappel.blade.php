@auth
    @php
        $membreRappel = auth()->user()->membre;
        $rappel = $membreRappel !== null
            && $membreRappel->statut === \App\Enums\StatutMembre::Valide
            && ! request()->routeIs('engagements.*', 'carte', 'cotisations', 'parametres')
                ? app(\App\Services\RappelService::class)->fenetre($membreRappel)
                : null;
    @endphp

    @if ($rappel)
        <div id="rappel-programme" role="dialog" aria-modal="true" aria-labelledby="rappel-titre"
             class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center">
            <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">🤲 Rappel</p>
                <h2 id="rappel-titre" class="mt-1 text-lg font-semibold text-emerald-950">{{ $rappel['titre'] }}</h2>
                <p class="mt-2 text-sm text-stone-700">{{ $rappel['message'] }}</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" data-fermer-rappel
                            class="rounded-lg bg-white px-4 py-3 text-sm font-semibold text-stone-700 ring-1 ring-stone-300 hover:bg-stone-100">Plus tard</button>
                    <a href="{{ $rappel['url'] }}"
                       class="rounded-lg bg-emerald-800 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-emerald-900">{{ $rappel['bouton'] }}</a>
                </div>
            </div>
        </div>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            document.querySelector('[data-fermer-rappel]')?.addEventListener('click', () => {
                document.getElementById('rappel-programme')?.remove();
            });
        </script>
    @endif
@endauth
