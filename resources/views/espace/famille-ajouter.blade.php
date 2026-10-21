@extends('layouts.public')

@section('titre', 'Ajouter un proche')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Ajouter un proche</h1>
    <p class="mt-1 text-sm text-stone-600">
        Il sera dans votre cellule ({{ $membre->cellule->nom }}). Votre Jewrine de cellule valide sa fiche, puis vous réglez
        ses frais de carte ; il cotise ensuite comme tout membre.
    </p>

    <form method="POST" action="{{ route('famille.store') }}" enctype="multipart/form-data"
          class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf

        <x-champ nom="prenom" libelle="Prénom" requis />
        <x-champ nom="nom" libelle="Nom" requis />

        <fieldset>
            <legend class="block text-sm font-medium">Sexe <span class="text-red-700">*</span></legend>
            <div class="mt-1 grid grid-cols-2 gap-2">
                @foreach ($sexes as $sexe)
                    <label class="flex cursor-pointer items-center justify-center rounded-lg border border-stone-300 px-3 py-2.5 text-sm has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                        <input type="radio" name="sexe" value="{{ $sexe->value }}" class="sr-only" @checked(old('sexe') === $sexe->value) required>
                        {{ $sexe === \App\Enums\Sexe::Homme ? 'Goor Yalla' : 'Sokhna' }}
                    </label>
                @endforeach
            </div>
            @error('sexe')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-date-naissance />
        <x-champ nom="lieu_naissance" libelle="Lieu de naissance" />

        <div>
            <label for="lien_famille" class="block text-sm font-medium">Ce proche est <span class="text-red-700">*</span></label>
            <select id="lien_famille" name="lien_famille" required
                    class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                <option value="">Choisir</option>
                @foreach ($liens as $lien)
                    <option value="{{ $lien->value }}" @selected(old('lien_famille') === $lien->value)>{{ $lien->getLabel() }}</option>
                @endforeach
            </select>
            @error('lien_famille')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <x-champ nom="telephone" libelle="Son téléphone (facultatif)" type="tel" inputmode="tel"
                 aide="Son propre numéro, s'il en a un (à partir de {{ \App\Models\Membre::ageMinimumInscription() }} ans). À laisser vide pour un enfant plus jeune : vous gérez sa fiche." />

        <div>
            <label for="photo" class="block text-sm font-medium">Photo <span class="text-red-700">*</span></label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required
                   class="mt-1 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-2 file:font-semibold file:text-emerald-900">
            <p class="mt-1 text-xs text-stone-500">Photo de face, visage bien visible. 5 Mo maximum.</p>
            @error('photo')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-xs text-stone-500">
            En envoyant cette fiche, vous acceptez, pour votre proche, la
            <a href="{{ route('confidentialite') }}" class="underline">politique de confidentialité</a> de la Daara.
        </p>

        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">
            Envoyer sa fiche
        </button>
    </form>

    <a href="{{ route('famille.index') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à ma famille</a>
@endsection
