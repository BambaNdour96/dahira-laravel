@extends('layouts.public')

@section('titre', 'Mon profil')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">{{ $correction ? 'Corriger ma fiche' : 'Mon profil' }}</h1>

    @if ($correction)
        <p class="mt-3 rounded-lg bg-sky-50 p-3 text-sm text-sky-900">
            <span class="font-semibold">Demande de votre Jewrine de cellule :</span> {{ $membre->motif_statut }}
        </p>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            Merci de corriger les champs signalés en rouge.
        </div>
    @endif

    <form method="POST" action="{{ route('espace.profil.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        @if ($correction)
            <fieldset class="space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <legend class="px-1 font-semibold text-emerald-900">Identité</legend>
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
                </div>
                <x-date-naissance :value="$membre->date_naissance?->format('Y-m-d')" />
                <x-champ nom="lieu_naissance" libelle="Lieu de naissance" :value="$membre->lieu_naissance" />
                <div>
                    <label for="cellule_id" class="block text-sm font-medium">Cellule <span class="text-red-700">*</span></label>
                    <select id="cellule_id" name="cellule_id" required class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                        @foreach ($cellules as $cellule)
                            <option value="{{ $cellule->id }}" @selected((string) old('cellule_id', $membre->cellule_id) === (string) $cellule->id)>{{ $cellule->nom }}</option>
                        @endforeach
                    </select>
                </div>
            </fieldset>
        @endif

        <fieldset class="space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <legend class="px-1 font-semibold text-emerald-900">Contact et résidence</legend>
            <x-champ nom="email" libelle="Email" type="email" :value="$membre->email" aide="Facultatif." />
            <x-champ nom="adresse" libelle="Adresse ou quartier" requis :value="$membre->adresse" />
            <x-champ nom="ville" libelle="Ville" requis :value="$membre->ville" />
            <x-champ nom="pays_residence" libelle="Pays de résidence" requis :value="$membre->pays_residence" />
        </fieldset>

        <fieldset class="space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <legend class="px-1 font-semibold text-emerald-900">Formation</legend>
            <div>
                <label for="niveau_etudes" class="block text-sm font-medium">Niveau d'études</label>
                <select id="niveau_etudes" name="niveau_etudes" class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                    <option value="">Choisir</option>
                    @foreach (\App\Enums\NiveauEtudes::cases() as $niveau)
                        <option value="{{ $niveau->value }}" @selected(old('niveau_etudes', $membre->niveau_etudes?->value) === $niveau->value)>{{ $niveau->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <x-champ nom="niveau_etudes_autre" libelle="Précisez votre niveau d'études" :value="$membre->niveau_etudes_autre" maxlength="100" />
            <x-champ nom="diplome" libelle="Diplôme le plus élevé" :value="$membre->diplome" />
            <x-champ nom="domaine_etudes" libelle="Domaine d'études" :value="$membre->domaine_etudes" />
            <fieldset>
                <legend class="block text-sm font-medium">Langues parlées</legend>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach (\App\Support\Langues::liste() as $code => $langue)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="langues[]" value="{{ $code }}" @checked(in_array($code, (array) old('langues', $membre->langues ?? []), true)) class="accent-emerald-800">
                            {{ $langue }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </fieldset>

        <fieldset class="space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <legend class="px-1 font-semibold text-emerald-900">Situation professionnelle</legend>
            <div>
                <label for="situation_professionnelle" class="block text-sm font-medium">Situation</label>
                <select id="situation_professionnelle" name="situation_professionnelle" class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                    <option value="">Choisir</option>
                    @foreach (\App\Enums\SituationProfessionnelle::cases() as $situation)
                        <option value="{{ $situation->value }}" @selected(old('situation_professionnelle', $membre->situation_professionnelle?->value) === $situation->value)>{{ $situation->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <x-champ nom="profession" libelle="Profession" :value="$membre->profession" />
            <x-champ nom="secteur_activite" libelle="Secteur d'activité" :value="$membre->secteur_activite" />
        </fieldset>

        @if (! $correction)
            @include('espace.partials.arabe-coran', ['profil' => $membre->profilArabeCoran, 'legende' => 'Arabe et Coran'])

            <fieldset class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <legend class="px-1 font-semibold text-emerald-900">Mes savoir-faire</legend>
                <p class="text-xs text-stone-500">Pour être appelé quand la Daara a besoin de vous (Magal, Ziar, chantiers…).</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    @foreach ($competences as $id => $nom)
                        <label class="flex items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                            <input type="checkbox" name="competences[]" value="{{ $id }}" class="rounded" @checked(in_array($id, old('competences', $mesCompetences), false))>
                            {{ $nom }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <fieldset class="space-y-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <legend class="px-1 font-semibold text-emerald-900">Photo</legend>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:font-medium file:text-emerald-900">
            <p class="text-xs text-stone-500">Laissez vide pour garder votre photo actuelle.</p>
            @error('photo')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            {{ $correction ? 'Renvoyer ma fiche corrigée' : 'Enregistrer' }}
        </button>
    </form>

    @if (! $correction)
        <a href="{{ route('cellule.index') }}" class="mt-4 block rounded-xl bg-white p-4 text-sm font-medium text-emerald-950 ring-1 ring-stone-200 hover:bg-stone-100">
            📍 Ma cellule : {{ $membre->cellule->nom }} · <span class="underline">changer de cellule</span>
        </a>
    @endif

    <a href="{{ route('espace') }}" class="mt-4 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
