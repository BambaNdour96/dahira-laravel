@extends('layouts.public')

@section('titre', 'Inscription')

@section('contenu')
    <h1 class="text-2xl font-semibold">Devenir membre</h1>
    <p class="mt-2 text-sm text-stone-600">
        Remplissez ce formulaire. Votre Jewrine de cellule vérifiera votre demande, puis vous recevrez votre matricule.
    </p>

    <form method="POST" action="{{ route('inscription.store') }}" enctype="multipart/form-data"
          class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf

        <x-champ nom="prenom" libelle="Prénom" requis autocomplete="given-name" />
        <x-champ nom="nom" libelle="Nom" requis autocomplete="family-name" />

        <div>
            <span class="block text-sm font-medium">Sexe <span class="text-red-700">*</span></span>
            <div class="mt-2 flex gap-6">
                @foreach (\App\Enums\Sexe::cases() as $sexe)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="sexe" value="{{ $sexe->value }}" @checked(old('sexe') === $sexe->value) required class="accent-emerald-800">
                        {{ $sexe->getLabel() }}
                    </label>
                @endforeach
            </div>
            @error('sexe')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-champ nom="date_naissance" libelle="Date de naissance" type="date" min="1900-01-01" :max="now()->subDay()->format('Y-m-d')" />
        <x-champ nom="lieu_naissance" libelle="Lieu de naissance" aide="Facultatif." />
        <x-champ nom="telephone" libelle="Téléphone" type="tel" requis inputmode="tel" autocomplete="tel"
                 placeholder="77 123 45 67" aide="Il vous servira à vous connecter." />
        <x-champ nom="email" libelle="Email" type="email" autocomplete="email" aide="Facultatif." />

        <div>
            <label for="cellule_id" class="block text-sm font-medium">Cellule <span class="text-red-700">*</span></label>
            <select id="cellule_id" name="cellule_id" required
                    class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                <option value="">Choisissez votre cellule</option>
                @foreach ($cellules as $cellule)
                    <option value="{{ $cellule->id }}" @selected((string) old('cellule_id') === (string) $cellule->id)>{{ $cellule->nom }}</option>
                @endforeach
            </select>
            @error('cellule_id')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        @if ($commissions->isNotEmpty())
            <fieldset>
                <legend class="block text-sm font-medium">
                    Commissions <span class="font-normal text-stone-500">(facultatif, 2 au maximum)</span>
                </legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($commissions as $commission)
                        <label class="flex items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="commissions[]" value="{{ $commission->id }}"
                                   @checked(in_array((string) $commission->id, array_map('strval', (array) old('commissions', [])), true))
                                   class="accent-emerald-800">
                            {{ $commission->nom }}
                        </label>
                    @endforeach
                </div>
                @error('commissions')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @error('commissions.*')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </fieldset>
        @endif

        <div>
            <label for="photo" class="block text-sm font-medium">Photo d'identité <span class="text-red-700">*</span></label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required
                   class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:font-medium file:text-emerald-900">
            <p class="mt-1 text-xs text-stone-500">Photo de face, visage bien visible. 5 Mo maximum.</p>
            @if ($errors->any())
                <p class="mt-1 text-xs text-amber-700">Pensez à choisir à nouveau votre photo.</p>
            @endif
            @error('photo')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-champ nom="password" libelle="Mot de passe" type="password" requis autocomplete="new-password" aide="8 caractères minimum." />
        <x-champ nom="password_confirmation" libelle="Confirmez le mot de passe" type="password" requis autocomplete="new-password" />

        <div>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="consentement" value="1" @checked(old('consentement')) required class="mt-0.5 accent-emerald-800">
                <span>
                    J'accepte que la Daara Nouroud Daareyni Kaolack enregistre mes informations pour gérer mon adhésion,
                    comme expliqué dans la page <a href="{{ route('confidentialite') }}" target="_blank" class="underline">Données personnelles</a>.
                </span>
            </label>
            @error('consentement')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
            Envoyer ma demande
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        Déjà inscrit ? <a href="{{ route('login') }}" class="font-medium text-emerald-800 underline">Se connecter</a>
    </p>
@endsection
