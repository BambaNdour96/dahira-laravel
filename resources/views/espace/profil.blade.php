@extends('layouts.public')

@section('titre', 'Ma fiche')

@section('contenu')
    <h1 class="text-2xl font-semibold">{{ $correction ? 'Corriger ma fiche' : 'Mes informations' }}</h1>

    @if ($correction)
        <p class="mt-3 rounded-lg bg-sky-50 p-3 text-sm text-sky-900">
            <span class="font-semibold">Demande de votre responsable :</span> {{ $membre->motif_statut }}
        </p>
    @endif

    <form method="POST" action="{{ route('espace.profil.update') }}" enctype="multipart/form-data"
          class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf
        @method('PUT')

        @if ($correction)
            <x-champ nom="prenom" libelle="Prénom" requis :value="$membre->prenom" />
            <x-champ nom="nom" libelle="Nom" requis :value="$membre->nom" />

            <div>
                <span class="block text-sm font-medium">Sexe <span class="text-red-700">*</span></span>
                <div class="mt-2 flex gap-6">
                    @foreach (\App\Enums\Sexe::cases() as $sexe)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="sexe" value="{{ $sexe->value }}" @checked(old('sexe', $membre->sexe->value) === $sexe->value) required class="accent-emerald-800">
                            {{ $sexe->getLabel() }}
                        </label>
                    @endforeach
                </div>
                @error('sexe')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <x-champ nom="date_naissance" libelle="Date de naissance" type="date" min="1900-01-01" :max="now()->subDay()->format('Y-m-d')" :value="$membre->date_naissance?->format('Y-m-d')" />
            <x-champ nom="lieu_naissance" libelle="Lieu de naissance" :value="$membre->lieu_naissance" />

            <div>
                <label for="cellule_id" class="block text-sm font-medium">Cellule <span class="text-red-700">*</span></label>
                <select id="cellule_id" name="cellule_id" required
                        class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                    @foreach ($cellules as $cellule)
                        <option value="{{ $cellule->id }}" @selected((string) old('cellule_id', $membre->cellule_id) === (string) $cellule->id)>{{ $cellule->nom }}</option>
                    @endforeach
                </select>
                @error('cellule_id')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <x-champ nom="email" libelle="Email" type="email" :value="$membre->email" aide="Facultatif." />

        <div>
            <label for="photo" class="block text-sm font-medium">Nouvelle photo</label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                   class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:font-medium file:text-emerald-900">
            <p class="mt-1 text-xs text-stone-500">Laissez vide pour garder votre photo actuelle.</p>
            @error('photo')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            {{ $correction ? 'Renvoyer ma fiche corrigée' : 'Enregistrer' }}
        </button>
    </form>

    <a href="{{ route('espace') }}" class="mt-4 inline-block text-sm text-emerald-800 underline">Retour à mon espace</a>
@endsection
