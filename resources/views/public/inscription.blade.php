@extends('layouts.public')

@section('titre', 'Inscription')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Devenir membre</h1>
    <p class="mt-2 text-sm text-stone-600">
        Deux étapes, environ deux minutes. Votre Jewrine de cellule vérifiera ensuite votre demande.
    </p>

    <ol class="mt-6 flex gap-2 text-sm" aria-label="Étapes de l'inscription">
        <li data-indicateur="1" class="flex-1 rounded-full bg-emerald-800 px-3 py-1.5 text-center font-semibold text-white">1. Identité</li>
        <li data-indicateur="2" class="flex-1 rounded-full bg-stone-200 px-3 py-1.5 text-center font-semibold text-stone-600">2. Contact et Daara</li>
    </ol>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Merci de corriger les points suivants :</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
            <p class="mt-2">Pensez aussi à choisir à nouveau votre photo.</p>
        </div>
    @endif

    <form id="formulaire-inscription" method="POST" action="{{ route('inscription.store') }}" enctype="multipart/form-data"
          class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        @csrf

        {{-- Page 1 : identité --}}
        <section data-etape="1" class="space-y-5">
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
            </div>

            <x-date-naissance />
            <x-champ nom="lieu_naissance" libelle="Lieu de naissance" aide="Facultatif." />

            <div>
                <label for="photo" class="block text-sm font-medium">Photo d'identité <span class="text-red-700">*</span></label>
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only">
                <input id="photo-appareil" type="file" accept="image/*" capture="user" class="sr-only">
                <div class="mt-2 grid grid-cols-2 gap-3">
                    <label for="photo-appareil" class="flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-emerald-800 px-3 py-3 text-sm font-semibold text-white hover:bg-emerald-900">📷 Prendre une photo</label>
                    <label for="photo" class="flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-emerald-50 px-3 py-3 text-sm font-semibold text-emerald-900 ring-1 ring-emerald-200 hover:bg-emerald-100">🖼️ Choisir une photo</label>
                </div>
                <p id="photo-choisie" class="mt-2 hidden text-sm font-medium text-emerald-800"></p>
                <p class="mt-1 text-xs text-stone-500">Photo de face, visage bien visible. 5 Mo maximum.</p>
            </div>

            <button type="button" data-suivant class="w-full rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
                Continuer
            </button>
        </section>

        {{-- Page 2 : contact, résidence et Daara --}}
        <section data-etape="2" class="hidden space-y-5">
            <x-indicatif />
            <x-champ nom="telephone" libelle="Téléphone" type="tel" requis inputmode="tel" autocomplete="tel"
                     placeholder="77 123 45 67" aide="Il vous servira à vous connecter." />
            <x-champ nom="email" libelle="Email" type="email" autocomplete="email" aide="Facultatif." />
            <x-champ nom="adresse" libelle="Adresse ou quartier" requis autocomplete="street-address" />
            <x-champ nom="ville" libelle="Ville" requis autocomplete="address-level2" />
            <x-champ nom="pays_residence" libelle="Pays de résidence" requis value="Sénégal" autocomplete="country-name" />

            <div>
                <label for="cellule_id" class="block text-sm font-medium">Cellule <span class="text-red-700">*</span></label>
                <select id="cellule_id" name="cellule_id" required
                        class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                    <option value="">Choisissez votre cellule</option>
                    @foreach ($cellules as $cellule)
                        <option value="{{ $cellule->id }}" @selected((string) old('cellule_id') === (string) $cellule->id)>{{ $cellule->nom }}</option>
                    @endforeach
                </select>
            </div>

            @if ($commissions->isNotEmpty())
                <div>
                    <label for="commission" class="block text-sm font-medium">
                        Commission <span class="text-red-700">*</span>
                    </label>
                    <select id="commission" name="commissions[]" required
                            class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
                        <option value="">Choisissez votre commission</option>
                        @foreach ($commissions as $commission)
                            <option value="{{ $commission->id }}"
                                    @selected(in_array((string) $commission->id, array_map('strval', (array) old('commissions', [])), true))>
                                {{ $commission->nom }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <x-champ nom="password" libelle="Mot de passe" type="password" requis autocomplete="new-password" aide="8 caractères minimum, avec au moins une lettre et un chiffre." />
            <x-champ nom="password_confirmation" libelle="Confirmez le mot de passe" type="password" requis autocomplete="new-password" />

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="consentement" value="1" @checked(old('consentement')) required class="mt-0.5 accent-emerald-800">
                <span>
                    J'accepte que la Daara Nouroud Daareyni Kaolack enregistre mes informations pour gérer mon adhésion,
                    comme expliqué dans la page <a href="{{ route('confidentialite') }}" target="_blank" class="underline">Données personnelles</a>.
                </span>
            </label>

            <div class="flex gap-3">
                <button type="button" data-precedent class="rounded-lg border border-stone-300 px-4 py-3 font-semibold text-stone-700 hover:bg-stone-50">
                    Retour
                </button>
                <button type="submit" class="flex-1 rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white hover:bg-emerald-900">
                    Envoyer ma demande
                </button>
            </div>
        </section>
    </form>

    <p class="mt-4 text-center text-sm">
        Déjà inscrit ? <a href="{{ route('login') }}" class="font-medium text-emerald-800 underline">Se connecter</a>
    </p>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const formulaire = document.getElementById('formulaire-inscription');
            const etapes = formulaire.querySelectorAll('[data-etape]');
            const indicateurs = document.querySelectorAll('[data-indicateur]');

            const afficher = (numero) => {
                etapes.forEach((etape) => etape.classList.toggle('hidden', etape.dataset.etape !== String(numero)));
                indicateurs.forEach((indicateur) => {
                    const actif = indicateur.dataset.indicateur === String(numero);
                    indicateur.classList.toggle('bg-emerald-800', actif);
                    indicateur.classList.toggle('text-white', actif);
                    indicateur.classList.toggle('bg-stone-200', !actif);
                    indicateur.classList.toggle('text-stone-600', !actif);
                });
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            formulaire.querySelector('[data-suivant]').addEventListener('click', () => {
                const champs = formulaire.querySelectorAll('[data-etape="1"] input, [data-etape="1"] select');
                for (const champ of champs) {
                    if (!champ.checkValidity()) {
                        champ.reportValidity();
                        return;
                    }
                }
                afficher(2);
            });

            formulaire.querySelector('[data-precedent]').addEventListener('click', () => afficher(1));

            // La photo prise avec l'appareil devient la photo envoyée.
            const photo = formulaire.querySelector('#photo');
            const appareil = formulaire.querySelector('#photo-appareil');
            const choisie = formulaire.querySelector('#photo-choisie');
            const montrerPhoto = () => {
                if (photo.files.length > 0) {
                    choisie.textContent = '✓ Photo choisie : ' + photo.files[0].name;
                    choisie.classList.remove('hidden');
                }
            };
            appareil.addEventListener('change', () => {
                if (appareil.files.length > 0) {
                    photo.files = appareil.files;
                    montrerPhoto();
                }
            });
            photo.addEventListener('change', montrerPhoto);

            // Une date impossible est signalée dès qu'on quitte le champ.
            formulaire.querySelectorAll('input[type="date"]').forEach((champ) => {
                champ.addEventListener('blur', () => {
                    if (!champ.checkValidity()) {
                        champ.reportValidity();
                    }
                });
            });
        })();
    </script>
@endsection
