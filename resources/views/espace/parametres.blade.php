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

    <p class="mt-6 text-xs font-medium uppercase tracking-wide text-stone-500">Connexion par empreinte</p>
    <div class="mt-2 rounded-xl bg-white p-4 ring-1 ring-stone-200">
        <p class="text-sm text-stone-700">Connectez-vous en une seconde avec l'empreinte digitale, le visage ou le code de votre téléphone. Votre empreinte ne quitte jamais votre appareil.</p>
        @foreach (auth()->user()->passkeys()->latest()->get() as $cle)
            <div class="mt-3 flex items-center justify-between gap-3 border-t border-stone-100 pt-3 text-sm">
                <div>
                    <p class="font-medium text-stone-800">🔐 {{ $cle->name }}</p>
                    <p class="text-xs text-stone-500">{{ $cle->last_used_at ? 'Utilisée '.$cle->last_used_at->diffForHumans() : 'Pas encore utilisée' }}</p>
                </div>
                <form method="POST" action="{{ route('empreinte.supprimer', $cle->id) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-medium text-red-800 underline">Retirer</button>
                </form>
            </div>
        @endforeach
        <div data-empreinte class="hidden">
            <button type="button" data-activer-empreinte data-options="{{ route('empreinte.options') }}" data-enregistrer="{{ route('empreinte.enregistrer') }}"
                    data-jeton="{{ csrf_token() }}" data-message="message-empreinte"
                    class="mt-4 w-full rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">
                Activer sur cet appareil
            </button>
            <p id="message-empreinte" class="mt-2 text-sm"></p>
        </div>
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