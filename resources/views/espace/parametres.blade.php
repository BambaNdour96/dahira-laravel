@extends('layouts.public')

@section('titre', 'Paramètres')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Paramètres</h1>

    <p class="mt-6 text-xs font-medium uppercase tracking-wide text-stone-500">Mon compte</p>
    <div class="mt-2 divide-y divide-stone-100 rounded-xl bg-white ring-1 ring-stone-200">
        @if (auth()->user()?->membre)
            <a href="{{ route('espace.profil') }}" class="flex items-center justify-between px-4 py-3.5 text-sm font-medium text-stone-800 hover:bg-stone-100">
                <span>👤 Modifier mon profil et ma photo</span><span aria-hidden="true">›</span>
            </a>
        @endif
        <a href="{{ route('mot-de-passe.edit') }}" class="flex items-center justify-between px-4 py-3.5 text-sm font-medium text-stone-800 hover:bg-stone-100">
            <span>🔒 Changer mon mot de passe</span><span aria-hidden="true">›</span>
        </a>
        <button type="button" id="theme-parametres" class="flex w-full items-center justify-between px-4 py-3.5 text-left text-sm font-medium text-stone-800 hover:bg-stone-100">
            <span>🌓 Thème</span><span id="theme-parametres-valeur" class="text-stone-500">automatique</span>
        </button>
    </div>

    <p class="mt-6 text-xs font-medium uppercase tracking-wide text-stone-500">Mes données</p>
    <div class="mt-2 divide-y divide-stone-100 rounded-xl bg-white ring-1 ring-stone-200">
        <a href="{{ route('donnees.export') }}" class="flex items-center justify-between px-4 py-3.5 text-sm font-medium text-stone-800 hover:bg-stone-100">
            <span>⬇️ Télécharger mes données</span><span aria-hidden="true">›</span>
        </a>
        <a href="{{ route('confidentialite') }}" class="flex items-center justify-between px-4 py-3.5 text-sm font-medium text-stone-800 hover:bg-stone-100">
            <span>🛡️ Données personnelles</span><span aria-hidden="true">›</span>
        </a>
        <a href="{{ route('suppression.create') }}" class="flex items-center justify-between px-4 py-3.5 text-sm font-medium text-red-800 hover:bg-stone-100">
            <span>🗑️ Supprimer mon compte</span><span aria-hidden="true">›</span>
        </a>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="w-full rounded-xl bg-white px-4 py-3 text-sm font-semibold text-emerald-900 ring-1 ring-stone-200 hover:bg-stone-100">
            Se déconnecter
        </button>
    </form>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const bouton = document.getElementById('theme-parametres');
            const valeur = document.getElementById('theme-parametres-valeur');
            const bas = () => document.getElementById('bouton-theme');
            const recopier = () => { if (bas() && valeur) { valeur.textContent = bas().textContent.replace('Thème : ', ''); } };
            recopier();
            bouton?.addEventListener('click', () => { bas()?.click(); recopier(); });
        })();
    </script>
@endsection