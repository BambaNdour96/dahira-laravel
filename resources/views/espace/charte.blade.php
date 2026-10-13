@extends('layouts.public')

@section('titre', 'Charte de confidentialité du bureau')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Charte de confidentialité du bureau</h1>
    <p class="mt-2 text-sm text-stone-600">Avant d'accéder au panneau, merci de lire et d'accepter ces engagements. Ils protègent les membres de la Daara, et vous protègent aussi.</p>

    <div class="mt-5 space-y-3 rounded-2xl bg-white p-5 text-sm text-stone-800 shadow-sm ring-1 ring-stone-200">
        <p class="font-semibold text-emerald-900">En tant que membre du bureau, je m'engage à :</p>
        <ol class="list-decimal space-y-2 pl-5">
            <li>n'utiliser les informations des membres (identité, téléphone, photo, adresse, paiements) que pour ma mission au sein de la Daara ;</li>
            <li>ne jamais partager de liste, d'export Excel, de rapport ou de capture d'écran contenant des données de membres dans un groupe WhatsApp, sur un réseau social ou avec une personne extérieure au bureau ;</li>
            <li>ne jamais communiquer à un membre les informations d'un autre membre (montants versés, téléphone, situation) ;</li>
            <li>garder mon mot de passe secret, ne jamais laisser ma session ouverte sur un appareil partagé, et activer la double authentification ;</li>
            <li>supprimer de mon téléphone ou de mon ordinateur les documents exportés dès qu'ils ne me sont plus utiles ;</li>
            <li>signaler sans attendre au super administrateur toute perte de téléphone, fuite ou utilisation anormale.</li>
        </ol>
        <p class="text-xs text-stone-500">Chaque document exporté porte votre nom, la date et l'heure, et chaque téléchargement est enregistré.</p>
    </div>

    <form method="POST" action="{{ route('charte.accepter') }}" class="mt-5 space-y-4">
        @csrf
        <label class="flex items-start gap-3 text-sm text-stone-800">
            <input type="checkbox" name="accepte" value="1" class="mt-1 h-5 w-5 rounded border-stone-300 text-emerald-800" required>
            <span>J'ai lu cette charte et je m'engage à la respecter.</span>
        </label>
        @error('accepte') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-5 py-3 font-semibold text-white hover:bg-emerald-900">J'accepte et j'accède au panneau</button>
    </form>
@endsection
