@extends('layouts.public')

@section('titre', 'Données personnelles')

@section('contenu')
    <h1 class="text-2xl font-semibold">Données personnelles</h1>
    <p class="mt-2 text-xs text-amber-700">Texte provisoire, en attente de validation par le bureau.</p>

    <div class="mt-6 space-y-5 text-sm leading-relaxed text-stone-700">
        <section>
            <h2 class="font-semibold text-stone-900">Responsable du traitement</h2>
            <p>Le bureau de la Daara Nouroud Daareyni Kaolack.</p>
        </section>
        <section>
            <h2 class="font-semibold text-stone-900">Données collectées</h2>
            <p>Prénom, nom, sexe, date de naissance, téléphone, email (facultatif), cellule et photo d'identité.</p>
        </section>
        <section>
            <h2 class="font-semibold text-stone-900">À quoi elles servent</h2>
            <p>Gérer votre adhésion, établir votre carte de membre, suivre les cotisations et vérifier votre carte lors des événements.</p>
        </section>
        <section>
            <h2 class="font-semibold text-stone-900">Qui y a accès</h2>
            <p>Les membres du bureau habilités et votre Jewrine de cellule, chacun selon son rôle. Vos données ne sont jamais vendues ni cédées.</p>
        </section>
        <section>
            <h2 class="font-semibold text-stone-900">Vos droits</h2>
            <p>
                Conformément à la loi n° 2008-12 sur la protection des données personnelles, vous pouvez consulter et corriger
                vos informations depuis votre espace, et demander leur suppression au bureau.
            </p>
        </section>
    </div>
@endsection
