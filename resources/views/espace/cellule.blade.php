@extends('layouts.public')

@section('titre', 'Ma cellule')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Ma cellule</h1>
    <p class="mt-1 text-sm text-stone-600">Vous êtes dans la cellule <strong>{{ $membre->cellule->nom }}</strong>.</p>

    @if ($membre->historiqueCellules->count() > 1)
        <div class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="font-semibold text-emerald-900">Mes cellules</h2>
            <ul class="mt-2 divide-y divide-stone-100 text-sm">
                @foreach ($membre->historiqueCellules as $periode)
                    <li class="flex justify-between gap-2 py-2">
                        <span class="font-medium">{{ $periode->cellule->nom }}</span>
                        <span class="text-stone-500">depuis le {{ $periode->debut_le->format('d/m/Y') }}{{ $periode->fin_le ? ' jusqu\'au '.$periode->fin_le->format('d/m/Y') : '' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach ($demandes as $demande)
        <div class="mt-4 rounded-xl p-4 text-sm ring-1 {{ $demande->statut === \App\Models\ChangementCellule::EN_ATTENTE ? 'bg-amber-50 text-amber-900 ring-amber-200' : 'bg-stone-50 text-stone-700 ring-stone-200' }}">
            <p>
                <span class="font-semibold">{{ $demande->depart->nom }} → {{ $demande->arrivee->nom }}</span>
                · {{ \App\Models\ChangementCellule::LIBELLES[$demande->statut] }} · demandé le {{ $demande->created_at->format('d/m/Y') }}
            </p>
            @if ($demande->motif_refus)
                <p class="mt-1">Motif du refus : {{ $demande->motif_refus }}</p>
            @endif
            @if ($demande->statut === \App\Models\ChangementCellule::EN_ATTENTE)
                <form method="POST" action="{{ route('cellule.retirer', $demande) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-red-700 underline">Retirer ma demande</button>
                </form>
            @endif
        </div>
    @endforeach

    @if (! $enAttente && ! $membre->estGereParUnProche())
        <form method="POST" action="{{ route('cellule.demander') }}" class="mt-5 space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            @csrf
            <h2 class="font-semibold text-emerald-900">Demander à changer de cellule</h2>
            <p class="text-xs text-stone-500">
                Le super administrateur valide la demande. Les proches dont vous gérez la fiche vous suivent ; vos dahiras,
                présences et versements passés restent comptés pour votre cellule actuelle.
            </p>
            <div>
                <label for="cellule_id" class="block text-sm font-medium">Nouvelle cellule <span class="text-red-700">*</span></label>
                <select id="cellule_id" name="cellule_id" required class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                    <option value="">Choisir</option>
                    @foreach ($cellules as $id => $nom)
                        <option value="{{ $id }}" @selected((int) old('cellule_id') === $id)>{{ $nom }}</option>
                    @endforeach
                </select>
                @error('cellule_id')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <x-champ nom="motif" libelle="Pourquoi ? (facultatif)" placeholder="Déménagement, travail…" />
            <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">Envoyer ma demande</button>
        </form>
    @endif

    <a href="{{ route('espace.profil') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon profil</a>
@endsection
