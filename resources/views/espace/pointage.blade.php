@extends('layouts.public')

@section('titre', 'Pointage')

@section('contenu')
    <h1 class="text-xl font-semibold text-emerald-900">{{ $evenement->titre }}</h1>
    <p class="text-sm text-stone-600">{{ $evenement->debut->translatedFormat('l j F à H\hi') }} · {{ $evenement->lieu }}</p>
    @if ($perimetre !== 'tous')
        <p class="mt-1 text-xs font-semibold text-emerald-700">Vous pointez {{ $perimetre === 'femmes' ? 'les sœurs' : 'les frères' }}.</p>
    @endif

    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
        <div class="rounded-xl bg-emerald-50 p-3 ring-1 ring-emerald-200">
            <p class="text-2xl font-bold text-emerald-900">{{ $bilan['presents'] }}</p>
            <p class="text-xs text-stone-600">présents sur {{ $bilan['concernes'] }}</p>
        </div>
        <div class="rounded-xl bg-white p-3 ring-1 ring-stone-200">
            <p class="text-lg font-bold text-emerald-900">{{ $bilan['freres'] }} / {{ $bilan['soeurs'] }}</p>
            <p class="text-xs text-stone-600">frères / sœurs</p>
        </div>
        <div class="rounded-xl bg-white p-3 ring-1 ring-stone-200">
            <p class="text-lg font-bold text-amber-800">{{ $bilan['excuses'] }}</p>
            <p class="text-xs text-stone-600">excusés</p>
        </div>
    </div>

    <p class="mt-4 rounded-lg bg-sky-50 p-3 text-sm text-sky-900 ring-1 ring-sky-200">
        📷 Pour scanner une carte, ouvrez l'appareil photo du téléphone et visez le QR code : la fiche du membre s'ouvre avec le bouton « Marquer présent ». Ici, vous pointez à la main ceux qui ont oublié leur carte.
    </p>

    @if (session('pointage_succes'))
        <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-900">{{ session('pointage_succes') }}</p>
    @endif
    @if (session('pointage_erreur'))
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-900">{{ session('pointage_erreur') }}</p>
    @endif

    <input type="search" data-chercher-membre placeholder="Chercher un nom ou un matricule…"
           class="mt-4 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">

    <ul class="mt-3 space-y-2">
        @foreach ($membres as $membre)
            @php $presence = $presences->get($membre->id); @endphp
            <li id="membre-{{ $membre->id }}" data-membre="{{ mb_strtolower($membre->prenom.' '.$membre->nom.' '.$membre->matricule) }}"
                class="scroll-mt-24 rounded-xl bg-white p-3 shadow-sm ring-1 {{ $presence?->statut === 'present' ? 'ring-emerald-300' : ($presence?->statut === 'excuse' ? 'ring-amber-300' : 'ring-stone-200') }}">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-emerald-950">{{ $membre->prenom }} {{ $membre->nom }}</p>
                        <p class="text-xs text-stone-500">
                            {{ $membre->matricule }} · {{ $membre->sexe->value === 'F' ? 'Sœur' : 'Frère' }}
                            @if ($presence)
                                · <span class="font-semibold {{ $presence->statut === 'present' ? 'text-emerald-800' : 'text-amber-800' }}">
                                    {{ $presence->statut === 'present' ? 'Présent ✓ ('.mb_strtolower($presence->libelleMode()).')' : 'Excusé : '.$presence->motif }}
                                </span>
                            @endif
                        </p>
                    </div>
                    @if ($presence)
                        <form method="POST" action="{{ route('pointage.pointer', [$evenement, $membre]) }}">
                            @csrf
                            <input type="hidden" name="action" value="retirer">
                            <button type="submit" class="rounded-lg px-3 py-2 text-xs font-semibold text-stone-600 ring-1 ring-stone-300">Annuler</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('pointage.pointer', [$evenement, $membre]) }}">
                            @csrf
                            <input type="hidden" name="action" value="present">
                            <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">Présent</button>
                        </form>
                    @endif
                </div>
                @unless ($presence)
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-xs font-medium text-amber-800">Absent excusé…</summary>
                        <form method="POST" action="{{ route('pointage.pointer', [$evenement, $membre]) }}" class="mt-2 flex gap-2">
                            @csrf
                            <input type="hidden" name="action" value="excuse">
                            <input type="text" name="motif" required maxlength="150" placeholder="Motif : voyage, maladie, travail…"
                                   class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                            <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white">Excuser</button>
                        </form>
                    </details>
                @endunless
            </li>
        @endforeach
    </ul>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.querySelector('[data-chercher-membre]')?.addEventListener('input', (evenement) => {
            const texte = evenement.target.value.trim().toLowerCase();
            document.querySelectorAll('[data-membre]').forEach((ligne) => {
                ligne.classList.toggle('hidden', texte !== '' && !ligne.dataset.membre.includes(texte));
            });
        });
    </script>
@endsection
