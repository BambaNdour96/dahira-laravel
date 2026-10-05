@extends('layouts.public')

@section('titre', 'Mon compte')

@section('contenu')
    @if (! $membre)
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <p>Ce compte n'est rattaché à aucune fiche de membre.</p>
            <a href="/admin" class="mt-3 inline-block text-sm font-medium text-emerald-800 underline">Aller au panneau d'administration</a>
        </div>
    @else
        @php
            $estValide = $membre->statut === \App\Enums\StatutMembre::Valide;
            $classes = match ($membre->statut) {
                \App\Enums\StatutMembre::Valide => 'bg-emerald-100 text-emerald-900',
                \App\Enums\StatutMembre::EnAttente => 'bg-amber-100 text-amber-900',
                \App\Enums\StatutMembre::ACorriger => 'bg-sky-100 text-sky-900',
                \App\Enums\StatutMembre::Suspendu => 'bg-stone-200 text-stone-800',
                default => 'bg-red-100 text-red-900',
            };
            $groupeCellule = $membre->cellule->whatsapp_lien;
        @endphp

        {{-- En-tête --}}
        <div class="flex items-center gap-4">
            @if ($membre->photo_path)
                <img src="{{ route('photos.show', $membre) }}" alt="Photo de {{ $membre->prenom }}"
                     class="h-20 w-20 rounded-full object-cover shadow ring-2 ring-or-400">
            @endif
            <div>
                <h1 class="text-2xl font-semibold text-emerald-950">{{ $membre->prenom }} {{ $membre->nom }}</h1>
                <span class="mt-1 inline-block rounded-full px-3 py-0.5 text-sm font-semibold {{ $classes }}">{{ $membre->statut->getLabel() }}</span>
            </div>
        </div>

        {{-- Compléter mon profil : seulement pour les membres validés --}}
        @if ($estValide && $membre->completude() < 100)
            <a href="{{ route('espace.profil') }}" class="mt-6 block rounded-xl bg-or-100 p-4 ring-1 ring-or-400 hover:bg-or-200">
                <div class="flex items-center justify-between text-sm font-semibold text-emerald-950">
                    <span>Compléter mon profil</span>
                    <span>{{ $membre->completude() }} %</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-white">
                    <div class="h-2 rounded-full bg-emerald-700" style="width: {{ $membre->completude() }}%"></div>
                </div>
                <p class="mt-2 text-xs text-emerald-900">Formation et situation professionnelle : quelques minutes suffisent.</p>
            </a>
        @endif

        {{-- Statut de l'adhésion --}}
        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="font-semibold text-emerald-900">Mon adhésion</h2>

            @if ($membre->statut === \App\Enums\StatutMembre::EnAttente)
                <p class="mt-2 text-sm text-stone-700">
                    Votre demande est en cours de vérification par votre Jewrine de cellule.
                    Vous aurez accès à votre compte complet dès sa validation.
                </p>
            @elseif ($membre->statut === \App\Enums\StatutMembre::ACorriger)
                <p class="mt-2 text-sm text-stone-700">Votre Jewrine de cellule vous demande de corriger votre fiche :</p>
                <p class="mt-2 rounded-lg bg-sky-50 p-3 text-sm italic text-sky-900">{{ $membre->motif_statut }}</p>
                <a href="{{ route('espace.profil') }}" class="mt-4 inline-block rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">Corriger ma fiche</a>
            @elseif ($membre->statut === \App\Enums\StatutMembre::Rejete)
                <p class="mt-2 text-sm text-stone-700">Votre demande n'a pas été retenue :</p>
                <p class="mt-2 rounded-lg bg-red-50 p-3 text-sm italic text-red-900">{{ $membre->motif_statut }}</p>
            @elseif ($estValide)
                <p class="mt-2 text-sm text-stone-700">Bienvenue parmi les membres de la Daara.</p>
            @else
                <p class="mt-2 text-sm text-stone-700">Pour toute question, contactez le bureau de la Daara.</p>
            @endif
        </div>

        @if ($estValide)
            {{-- Groupes WhatsApp --}}
            @if ($groupeCellule || $whatsappNational)
                <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                    <h2 class="font-semibold text-emerald-900">Groupes WhatsApp</h2>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                        @if ($groupeCellule)
                            <a href="{{ $groupeCellule }}" target="_blank" rel="noopener"
                               class="flex-1 rounded-lg bg-[#25D366] px-4 py-3 text-center font-semibold text-white hover:opacity-90">
                                Groupe de la cellule {{ $membre->cellule->nom }}
                            </a>
                        @endif
                        @if ($whatsappNational)
                            <a href="{{ $whatsappNational }}" target="_blank" rel="noopener"
                               class="flex-1 rounded-lg bg-[#25D366] px-4 py-3 text-center font-semibold text-white hover:opacity-90">
                                Groupe national
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Mes affectations --}}
            <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <h2 class="font-semibold text-emerald-900">Mes affectations</h2>
                <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-stone-500">Cellule</dt>
                        <dd class="font-medium">{{ $membre->cellule->nom }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Poste dans la Daara</dt>
                        <dd class="font-medium">{{ $membre->poste?->nom ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Commissions</dt>
                        <dd class="font-medium">{{ $membre->commissions->pluck('nom')->join(', ') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Matricule</dt>
                        <dd class="font-mono font-medium">{{ $membre->matricule }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Date d'adhésion</dt>
                        <dd class="font-medium">{{ $membre->date_adhesion?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-stone-500">Ces informations sont attribuées par le bureau.</p>
            </div>

            {{-- Ma carte et mes cotisations : bientôt --}}
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-dashed border-stone-300 p-5 text-sm">
                    <h2 class="font-semibold text-emerald-900">Ma carte de membre</h2>
                    <p class="mt-2 text-stone-500">Bientôt disponible ici.</p>
                </div>
                <div class="rounded-xl border border-dashed border-stone-300 p-5 text-sm">
                    <h2 class="font-semibold text-emerald-900">Mes cotisations</h2>
                    <p class="mt-2 text-stone-500">Bientôt : le suivi de vos paiements et le paiement par Wave.</p>
                </div>
            </div>

            <a href="{{ route('espace.profil') }}" class="mt-6 inline-block text-sm font-medium text-emerald-800 underline">Modifier mon profil</a>
        @endif
    @endif
@endsection
