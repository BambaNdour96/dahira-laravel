@extends('layouts.public')

@section('titre', 'Supprimer mon compte')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Supprimer mon compte</h1>

    @if ($demande)
        <div class="mt-6 rounded-xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200">
            <p class="font-semibold">Votre demande est en cours de traitement.</p>
            <p class="mt-1">Envoyée le {{ $demande->created_at->format('d/m/Y à H:i') }}. Elle sera traitée sous 30 jours.</p>
        </div>
    @else
        <div class="mt-6 rounded-xl bg-red-50 p-5 text-sm leading-relaxed text-red-900 ring-1 ring-red-200">
            <p class="font-semibold">Ce qui se passera, de façon définitive :</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>votre nom, votre téléphone, votre photo et votre profil seront effacés ;</li>
                <li>votre carte de membre ne sera plus valide ;</li>
                <li>vous ne pourrez plus vous connecter ;</li>
                <li>vos paiements resteront dans la comptabilité de la Daara, mais sans votre nom.</li>
            </ul>
            <p class="mt-3">
                Avant de continuer, vous pouvez
                <a href="{{ route('donnees.export') }}" class="font-semibold underline">télécharger une copie de vos données</a>.
            </p>
        </div>

        <form method="POST" action="{{ route('suppression.store') }}"
              class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            @csrf

            <div>
                <label for="motif" class="block text-sm font-medium">
                    Motif <span class="font-normal text-stone-500">(facultatif)</span>
                </label>
                <textarea id="motif" name="motif" rows="3" maxlength="1000"
                          class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">{{ old('motif') }}</textarea>
            </div>

            <x-champ nom="mot_de_passe" libelle="Votre mot de passe, pour confirmer" type="password" requis
                     autocomplete="current-password" />

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="confirmation" value="1" class="mt-1" required>
                <span>J'ai compris que la suppression de mes données est définitive.</span>
            </label>
            @error('confirmation')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror

            <button type="submit" class="w-full rounded-lg bg-red-700 px-4 py-3 font-semibold text-white hover:bg-red-800">
                Envoyer ma demande de suppression
            </button>
        </form>
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection