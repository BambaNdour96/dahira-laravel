@extends('layouts.public')

@section('titre', 'Mot de passe oublié')

@section('contenu')
    <h1 class="text-2xl font-semibold">Mot de passe oublié</h1>
    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
        <p class="text-stone-700">
            Contactez votre responsable de cellule : il pourra réinitialiser votre mot de passe.
        </p>
        <p class="mt-3 text-sm text-stone-500">
            La réinitialisation par email sera proposée prochainement aux membres qui ont renseigné une adresse.
        </p>
        <a href="{{ route('login') }}" class="mt-5 inline-block text-sm font-medium text-emerald-800 underline">Retour à la connexion</a>
    </div>
@endsection
