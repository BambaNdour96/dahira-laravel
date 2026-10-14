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
            $etapes = ['Demande envoyée', 'Demande acceptée', 'Frais de carte', 'Membre validé'];
            $etapeActuelle = match ($membre->statut) {
                \App\Enums\StatutMembre::EnAttente, \App\Enums\StatutMembre::ACorriger => 1,
                \App\Enums\StatutMembre::Accepte => $paiementEnCours ? 3 : 2,
                default => 0,
            };
        @endphp

        {{-- Salutation --}}
        <div class="flex items-center gap-3">
            @if ($membre->photo_path)
                <a href="{{ route('espace.profil') }}" class="shrink-0" title="Modifier mon profil et ma photo">
                    <img src="{{ route('photos.show', $membre) }}" alt="Photo de {{ $membre->prenom }}"
                         class="h-14 w-14 rounded-full object-cover shadow ring-2 ring-or-400">
                </a>
            @endif
            <div class="min-w-0">
                <p class="text-sm text-stone-500">Assalamou aleykoum,</p>
                <h1 class="truncate text-2xl font-semibold text-emerald-950">{{ $membre->prenom }} {{ $membre->nom }}</h1>
                <span class="mt-1 inline-block rounded-full px-3 py-0.5 text-xs font-semibold {{ $classes }}">{{ $membre->statut->getLabel() }}</span>
            </div>
        </div>

        @php($compte = auth()->user())
        @if ($compte !== null && filled($compte->email) && $compte->email_verified_at === null)
            <div class="mt-5 rounded-xl bg-amber-50 p-4 text-sm ring-1 ring-amber-200">
                <p class="font-semibold text-amber-900">Confirmez votre adresse email</p>
                <p class="mt-1 text-amber-900">Un lien a été envoyé à {{ $compte->email }}. Tant qu'elle n'est pas confirmée, vous ne recevrez ni reçus ni lien de mot de passe oublié par email.</p>
                <form method="POST" action="{{ route('email.renvoyer') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Renvoyer le lien</button>
                </form>
            </div>
        @endif

        @if ($estValide)
            {{-- Carte de membre en miniature --}}
            <a href="{{ route('carte') }}" class="mt-5 flex items-center gap-4 rounded-2xl bg-emerald-900 p-4 text-white shadow-sm hover:bg-emerald-950">
                @if ($membre->photo_path)
                    <img src="{{ route('photos.show', $membre) }}" alt="" class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-or-400">
                @endif
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">Ma carte de membre</p>
                    <p class="truncate text-sm text-emerald-100">{{ $membre->matricule }} · {{ $membre->cellule->nom }}</p>
                </div>
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-white text-emerald-900" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-7 w-7" fill="currentColor"><path d="M3 3h7v7H3V3zm2 2v3h3V5H5zm9-2h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5zm9-2h2v2h-2v-2zm2 2h2v2h-2v-2zm2-2h3v2h-3v-2zm-4 4h2v3h-2v-3zm4 0h3v3h-3v-1h1v-1h-1v-1zm-2 2h2v1h-2v-1z"/></svg>
                </span>
            </a>
            <p class="mt-1 text-xs text-stone-500">Touchez la carte pour l'afficher en grand.</p>

            {{-- État des cotisations --}}
            @if ($retardCotisation > 0)
                <a href="{{ route('cotisations') }}" class="mt-4 block rounded-xl bg-red-50 p-4 ring-1 ring-red-200 hover:bg-red-100">
                    <p class="font-semibold text-red-900">Cotisation en retard de {{ $retardCotisation }} mois</p>
                    <p class="mt-1 text-sm text-red-800">Réglez vos mois en attente avec Wave : appuyez ici.</p>
                    <span class="mt-3 inline-block rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Payer maintenant</span>
                </a>
            @else
                <div class="mt-4 flex items-center gap-3 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-white" aria-hidden="true">✓</span>
                    <div>
                        <p class="font-semibold text-emerald-900">Cotisations à jour</p>
                        <p class="text-sm text-stone-600">Merci pour votre engagement envers la Daara.</p>
                    </div>
                </div>
            @endif

            {{-- Dahira de la cellule : prochaine dahira et assiduité --}}
            @php($prochaineDahira = app(\App\Services\AssiduiteService::class)->prochaineDahira($membre))
            @php($assiduite = app(\App\Services\AssiduiteService::class)->pour($membre))
            @if ($prochaineDahira || $assiduite['dahiras'] > 0)
                <div class="mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
                    <p class="font-semibold text-emerald-950">🕌 Dahira de ma cellule</p>
                    @if ($prochaineDahira)
                        <p class="mt-1 text-sm text-stone-700">
                            Prochaine dahira : <span class="font-semibold">{{ $prochaineDahira->debut->translatedFormat('l j F à H\hi') }}</span> · {{ $prochaineDahira->lieu }}
                        </p>
                        @if ($prochaineDahira->description)
                            <p class="text-xs text-stone-500">{{ $prochaineDahira->description }}</p>
                        @endif
                    @endif
                    @if ($assiduite['dahiras'] > 0)
                        <p class="mt-2 text-sm text-stone-700">
                            Mon assiduité : <span class="font-semibold text-emerald-900">{{ $assiduite['presents'] }} dahira(s) sur {{ $assiduite['dahiras'] }}</span> ces 3 derniers mois
                            {{ $assiduite['excuses'] > 0 ? '('.$assiduite['excuses'].' excusée(s))' : '' }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- Engagements en cours (Magal, Ziar, Prokhane, collectes) --}}
            @php($mesEngagements = app(\App\Services\CollecteService::class)->resumePour($membre))
            @if ($mesEngagements->isNotEmpty())
                <a href="{{ route('engagements.index') }}" class="mt-4 block rounded-xl bg-white p-4 shadow-sm ring-1 ring-or-400 hover:bg-stone-100">
                    <p class="font-semibold text-emerald-950">🤲 Mes Sass et Jàyante</p>
                    @foreach ($mesEngagements as $resume)
                        <div class="mt-3">
                            <div class="flex justify-between gap-2 text-sm">
                                <span class="truncate text-stone-700">{{ $resume['titre'] }}</span>
                                <span class="shrink-0 font-semibold text-emerald-900">
                                    {{ $resume['montant'] === null ? 'À fixer' : $resume['taux'].' %' }}
                                </span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-stone-200"><div class="h-2 rounded-full bg-emerald-700" style="width: {{ $resume['taux'] }}%"></div></div>
                        </div>
                    @endforeach
                </a>
            @endif

            {{-- Raccourcis --}}
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <a href="{{ route('cotisations') }}" class="rounded-xl bg-white p-4 text-sm font-medium text-emerald-950 ring-1 ring-stone-200 hover:bg-stone-100">
                    <span class="block text-2xl" aria-hidden="true">💳</span> Payer
                </a>
                <a href="{{ route('recus.index') }}" class="rounded-xl bg-white p-4 text-sm font-medium text-emerald-950 ring-1 ring-stone-200 hover:bg-stone-100">
                    <span class="block text-2xl" aria-hidden="true">🧾</span> Mes reçus
                </a>
                <a href="{{ route('evenements.index') }}" class="rounded-xl bg-white p-4 text-sm font-medium text-emerald-950 ring-1 ring-stone-200 hover:bg-stone-100">
                    <span class="block text-2xl" aria-hidden="true">📅</span> Participations
                </a>
                @if ($groupeCellule || $whatsappNational)
                    <a href="#groupes" class="rounded-xl bg-white p-4 text-sm font-medium text-emerald-950 ring-1 ring-stone-200 hover:bg-stone-100">
                        <span class="block text-2xl" aria-hidden="true">💬</span> Groupes
                    </a>
                @else
                    <a href="{{ route('notifications.index') }}" class="rounded-xl bg-white p-4 text-sm font-medium text-emerald-950 ring-1 ring-stone-200 hover:bg-stone-100">
                        <span class="block text-2xl" aria-hidden="true">🔔</span> Notifications
                    </a>
                @endif
            </div>

            {{-- Profil incomplet : la barre disparaît quand tout est rempli --}}
            @if ($membre->completude() < 100)
                <a href="{{ route('espace.profil') }}" class="mt-5 block rounded-xl bg-or-100 p-4 ring-1 ring-or-400 hover:bg-or-200">
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

            {{-- Groupes WhatsApp --}}
            @if ($groupeCellule || $whatsappNational)
                <div id="groupes" class="mt-5 scroll-mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
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
            <div class="mt-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                <h2 class="font-semibold text-emerald-900">Mes affectations</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-stone-500">Cellule</dt>
                        <dd class="font-medium">{{ $membre->cellule->nom }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Mes responsabilités</dt>
                        <dd class="font-medium" data-test="mes-responsabilites">
                            {{ $compte?->nominations()->with(['poste', 'cellule'])->get()->map(fn ($nomination) => $nomination->libelle())->join(', ') ?: '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Commissions</dt>
                        <dd class="font-medium">{{ $membre->commissions->pluck('nom')->join(', ') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Date d'adhésion</dt>
                        <dd class="font-medium">{{ $membre->date_adhesion?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-stone-500">Ces informations sont attribuées par le bureau.</p>
                @if ($recuFraisCarte)
                    <a href="{{ route('recus.show', $recuFraisCarte) }}" target="_blank" class="mt-2 inline-block text-sm font-medium text-emerald-800 underline">Mon reçu d'inscription</a>
                @endif
            </div>
        @else
            {{-- Suivi de la demande, étape par étape --}}
            @if ($etapeActuelle > 0)
                <ol class="mt-6 grid grid-cols-4 gap-2 text-center text-[11px] leading-tight">
                    @foreach ($etapes as $index => $etape)
                        @php($numero = $index + 1)
                        <li>
                            <span class="mx-auto flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold {{ $numero < $etapeActuelle ? 'bg-emerald-700 text-white' : ($numero === $etapeActuelle ? 'bg-or-100 text-emerald-950 ring-2 ring-or-400' : 'bg-stone-100 text-stone-500') }}">
                                {{ $numero < $etapeActuelle ? '✓' : $numero }}
                            </span>
                            <span class="mt-1 block text-stone-600">{{ $etape }}</span>
                        </li>
                    @endforeach
                </ol>
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
                                    <a href="{{ $lienWave }}"
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
                                    <input type="tel" name="numero_wave" value="{{ old('numero_wave', $membre->telephone) }}" inputmode="tel" autocomplete="tel"
                                           placeholder="Numéro Wave utilisé" title="Numéro Wave utilisé pour payer"
                                           class="rounded-lg border border-stone-300 px-3 py-2.5 text-base sm:w-44">
                                    <input type="text" name="reference" value="{{ old('reference') }}" required autocomplete="off"
                                           placeholder="Exemple : T_CN535JQDN4VAPI5R"
                                           class="flex-1 rounded-lg border border-stone-300 px-3 py-2.5 font-mono text-base">
                                    <button type="submit" class="rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white hover:bg-emerald-900">
                                        J'ai payé
                                    </button>
                                </form>
                                @error('numero_wave')
                                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                                @enderror
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

                @else
                    <p class="mt-2 text-sm text-stone-700">Pour toute question, contactez le bureau de la Daara.</p>
                @endif
            </div>
        @endif
    @endif
@endsection