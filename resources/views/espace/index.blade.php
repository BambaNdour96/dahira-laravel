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
                \App\Enums\StatutMembre::Accepte => 'bg-or-100 text-emerald-950',
                \App\Enums\StatutMembre::ACorriger => 'bg-sky-100 text-sky-900',
                \App\Enums\StatutMembre::Suspendu, \App\Enums\StatutMembre::Expire => 'bg-stone-200 text-stone-800',
                default => 'bg-red-100 text-red-900',
            };
            $groupeCellule = $membre->cellule->whatsapp_lien;
            $montant = number_format($montantFraisCarte, 0, ',', ' ');
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

        {{-- Mon adhésion --}}
        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="font-semibold text-emerald-900">Mon adhésion</h2>

            @if ($membre->statut === \App\Enums\StatutMembre::EnAttente)
                <p class="mt-2 text-sm text-stone-700">
                    Votre demande est en cours de vérification par votre Jewrine de cellule.
                    Vous serez informé dès qu'elle sera acceptée.
                </p>

            @elseif ($membre->statut === \App\Enums\StatutMembre::Accepte)
                <p class="mt-2 text-sm text-stone-700">
                    Votre demande est <strong>acceptée</strong>. Dernière étape pour devenir membre et recevoir votre carte :
                    réglez les <strong>frais de carte de {{ $montant }} FCFA</strong>
                    avant le <strong>{{ $membre->dateLimitePaiement()?->format('d/m/Y') }}</strong>.
                </p>

                @if ($paiementEnCours)
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
                        Paiement déclaré le {{ $paiementEnCours->declare_le->format('d/m/Y à H:i') }}
                        (référence <span class="font-mono">{{ $paiementEnCours->reference }}</span>).
                        Il est en cours de vérification par le Jewrine finance.
                    </p>
                @else
                    @if ($dernierRefus)
                        <p class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-900">
                            Votre déclaration précédente a été refusée : {{ $dernierRefus->motif_refus }}
                        </p>
                    @endif

                    <ol class="mt-4 space-y-4 text-sm">
                        <li>
                            <p class="font-semibold text-emerald-900">1. Payez {{ $montant }} FCFA avec Wave</p>
                            @if ($lienWave)
                                <a href="{{ $lienWave }}" target="_blank" rel="noopener"
                                   class="mt-2 inline-block rounded-lg bg-[#1dc8ff] px-5 py-3 font-semibold text-white hover:opacity-90">
                                    Payer avec Wave
                                </a>
                            @else
                                <p class="mt-1 text-stone-600">Le lien de paiement Wave sera bientôt disponible. Vous pouvez payer en espèces auprès du Jewrine finance.</p>
                            @endif
                        </li>
                        <li>
                            <p class="font-semibold text-emerald-900">2. Indiquez la référence de votre reçu Wave</p>
                            <p class="mt-1 text-xs text-stone-500">Dans l'application Wave : historique, puis le détail de ce paiement.</p>
                            <form method="POST" action="{{ route('espace.paiement') }}" class="mt-2 flex flex-col gap-2 sm:flex-row">
                                @csrf
                                <input type="text" name="reference" value="{{ old('reference') }}" required autocomplete="off"
                                       placeholder="Référence de la transaction"
                                       class="flex-1 rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-base">
                                <button type="submit" class="rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white hover:bg-emerald-900">
                                    J'ai payé
                                </button>
                            </form>
                            @error('reference')
                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </li>
                    </ol>
                    <p class="mt-4 text-xs text-stone-500">Vous pouvez aussi régler en espèces auprès du Jewrine finance de la Daara.</p>
                @endif

            @elseif ($membre->statut === \App\Enums\StatutMembre::Expire)
                <p class="mt-2 text-sm text-stone-700">
                    Le délai de {{ \App\Models\Membre::DELAI_PAIEMENT_JOURS }} jours pour régler les frais de carte est dépassé.
                    Vous pouvez renvoyer votre demande à votre Jewrine de cellule.
                </p>
                <form method="POST" action="{{ route('espace.relancer') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900">
                        Refaire ma demande
                    </button>
                </form>

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

            {{-- Ma carte et mes cotisations --}}
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <a href="{{ route('carte') }}" class="block rounded-xl bg-emerald-800 p-5 text-sm text-white shadow-sm hover:bg-emerald-900">
                    <h2 class="font-semibold">Ma carte de membre</h2>
                    <p class="mt-2 text-emerald-100">Afficher ma carte avec son QR code.</p>
                </a>
                <a href="{{ route('cotisations') }}" class="block rounded-xl bg-white p-5 text-sm shadow-sm ring-1 ring-stone-200 hover:ring-emerald-700">
                    <h2 class="font-semibold text-emerald-900">Mes cotisations</h2>
                    <p class="mt-2 text-stone-500">Voir mes mois réglés et payer avec Wave.</p>
                </a>
            </div>

            <a href="{{ route('espace.profil') }}" class="mt-6 inline-block text-sm font-medium text-emerald-800 underline">Modifier mon profil</a>
        @endif
    @endif
@endsection
