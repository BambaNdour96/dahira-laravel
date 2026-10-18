@extends('layouts.public')

@section('titre', 'Payer pour un membre')

@section('contenu')
    @php
        $fcfa = fn (int $montant): string => number_format($montant, 0, ',', ' ').' FCFA';
        $champ = 'rounded-lg border border-stone-300 px-3 py-2.5 text-base';
    @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">Payer pour un membre</h1>
    <p class="mt-1 text-sm text-stone-600">
        Réglez la cotisation ou le Jàyante d'un autre membre (votre épouse, par exemple). Il est prévenu, et reçoit son reçu
        dès la confirmation du Jewrine finance. Pour un proche dont vous gérez la fiche, passez par « Ma famille ».
    </p>

    <form method="GET" action="{{ route('autrui.show') }}" class="mt-5 flex gap-2">
        <input type="text" name="recherche" value="{{ $recherche }}" required autocomplete="off"
               placeholder="Matricule ou téléphone du membre" aria-label="Matricule ou téléphone du membre"
               class="min-w-0 flex-1 {{ $champ }}">
        <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white hover:bg-emerald-900">Chercher</button>
    </form>

    @if ($erreur)
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-900 ring-1 ring-red-200">{{ $erreur }}</p>
    @endif

    @if ($beneficiaire)
        <div class="mt-5 flex items-center gap-3 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-700 font-semibold text-white" aria-hidden="true">{{ mb_substr($beneficiaire->prenom, 0, 1) }}</span>
            <div class="min-w-0">
                <p class="truncate font-semibold text-emerald-950">{{ $beneficiaire->prenom }} {{ $beneficiaire->nom }}</p>
                <p class="truncate text-xs text-stone-600">{{ $beneficiaire->matricule }} · {{ $beneficiaire->cellule->nom }}</p>
            </div>
        </div>

        {{-- Sa cotisation --}}
        <form method="POST" action="{{ route('autrui.cotisation', $beneficiaire) }}" class="mt-5 space-y-3 rounded-xl bg-white p-5 text-sm shadow-sm ring-1 ring-stone-200">
            @csrf
            <h2 class="font-semibold text-emerald-900">Sa cotisation ({{ $fcfa($mensuel) }} par mois)</h2>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($choix as $nombre)
                    <label class="flex cursor-pointer flex-col items-center rounded-lg border border-stone-300 px-2 py-3 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                        <input type="radio" name="nombre_mois" value="{{ $nombre }}" class="sr-only" @checked((int) old('nombre_mois', 1) === $nombre) required>
                        <span class="font-semibold">{{ $nombre }} mois</span>
                        <span class="text-xs text-stone-500">{{ $fcfa($nombre * $mensuel) }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-stone-500">Le paiement couvre d'abord ses mois de retard, s'il en a.</p>
            @if ($liensWave !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach ($liensWave as $nombre => $lien)
                        <a href="{{ $lien }}" class="rounded-lg bg-[#1dc8ff] px-3 py-2 font-semibold text-white hover:opacity-90">Wave : {{ $nombre }} mois</a>
                    @endforeach
                </div>
            @endif
            <input type="text" name="reference" value="{{ old('reference') }}" required autocomplete="off"
                   placeholder="Référence Wave, exemple : T_CN535JQDN4VAPI5R" class="block w-full font-mono {{ $champ }}">
            <input type="tel" name="numero_wave" value="{{ old('numero_wave', auth()->user()?->telephone) }}" inputmode="tel"
                   placeholder="Numéro Wave utilisé" class="block w-full {{ $champ }}">
            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">J'ai payé sa cotisation</button>
            @error('reference')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
        </form>

        {{-- Ses Sass et Jàyante --}}
        @foreach ($collectes as $collecte)
            <form method="POST" action="{{ route('autrui.versement', [$beneficiaire, $collecte]) }}" class="mt-5 space-y-3 rounded-xl bg-white p-5 text-sm shadow-sm ring-1 ring-or-400">
                @csrf
                <h2 class="font-semibold text-emerald-900">🤲 {{ $collecte->titre }}</h2>
                <p class="text-xs text-stone-500">Demandez-lui le montant à verser : ses montants restent privés.</p>
                <div class="flex items-center gap-2">
                    <input type="number" name="montant" min="100" step="50" inputmode="numeric" required value="{{ old('montant') }}"
                           placeholder="Montant" aria-label="Montant versé pour {{ $collecte->titre }}" class="w-40 font-semibold {{ $champ }}">
                    <span class="text-stone-600">FCFA</span>
                </div>
                @if ($lienGeneral)
                    <a href="{{ $lienGeneral }}" class="inline-block rounded-lg bg-[#1dc8ff] px-3 py-2 font-semibold text-white hover:opacity-90">Payer avec Wave</a>
                @endif
                <input type="text" name="reference" required autocomplete="off"
                       placeholder="Référence Wave, exemple : T_CN535JQDN4VAPI5R" class="block w-full font-mono {{ $champ }}">
                <input type="tel" name="numero_wave" value="{{ auth()->user()?->telephone }}" inputmode="tel"
                       placeholder="Numéro Wave utilisé" class="block w-full {{ $champ }}">
                <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">J'ai payé</button>
            </form>
        @endforeach
        @error('montant')
            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
