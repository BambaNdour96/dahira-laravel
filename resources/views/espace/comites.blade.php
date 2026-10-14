@extends('layouts.public')

@section('titre', 'Mes comités')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Mes comités</h1>

    @if (session('comite_succes'))
        <p class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-900">{{ session('comite_succes') }}</p>
    @endif
    @if (session('comite_erreur'))
        <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-900">{{ session('comite_erreur') }}</p>
    @endif

    @if ($actions->isNotEmpty())
        <section class="mt-4 rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
            <h2 class="font-semibold text-amber-900">Mes actions à mener</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($actions as $action)
                    <li class="rounded-lg bg-white p-3 ring-1 {{ $action->estEnRetard() ? 'ring-red-300' : 'ring-amber-200' }}">
                        <span class="font-medium text-stone-900">{{ $action->description }}</span>
                        <span class="block text-xs text-stone-600">
                            {{ $action->instance->nom }}{{ $action->commission ? ' · Commission '.$action->commission->nom : '' }}{{ $action->echeance ? ' · pour le '.$action->echeance->format('d/m/Y') : '' }}
                            · <span class="font-semibold {{ $action->estEnRetard() ? 'text-red-700' : 'text-stone-700' }}">{{ $action->estEnRetard() ? 'En retard' : \App\Models\ActionComite::STATUTS[$action->statut] }}</span>
                        </span>
                        <div class="mt-2 flex gap-2">
                            @if ($action->statut === 'a_faire')
                                <form method="POST" action="{{ route('comites.actions.statut', $action) }}">
                                    @csrf
                                    <input type="hidden" name="statut" value="en_cours">
                                    <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-300">Commencée</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('comites.actions.statut', $action) }}">
                                @csrf
                                <input type="hidden" name="statut" value="faite">
                                <button type="submit" class="rounded-lg bg-emerald-800 px-3 py-1.5 text-xs font-semibold text-white">Faite ✓</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @foreach ($mesCommissions as $commission)
        <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-emerald-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Je dirige</p>
            <h2 class="text-lg font-semibold text-emerald-950">Commission {{ $commission->nom }}</h2>
            <p class="text-xs text-stone-500">{{ $commission->instance->nom }}</p>
            <p class="mt-2 text-sm text-stone-700">
                Budget retenu : <span class="font-semibold">{{ number_format($commission->budget(), 0, ',', ' ') }} FCFA</span>
                · dépensé : <span class="font-semibold">{{ number_format($commission->depense(), 0, ',', ' ') }} FCFA</span>
            </p>

            <h3 class="mt-4 font-semibold text-emerald-900">Membres de la commission ({{ $commission->membres->count() }})</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($commission->membres as $membreCommission)
                    <li class="flex items-center justify-between gap-2">
                        <span>{{ $membreCommission->prenom }} {{ $membreCommission->nom }} <span class="text-xs text-stone-500">{{ $membreCommission->matricule }}</span></span>
                        <form method="POST" action="{{ route('comites.membres.retirer', [$commission, $membreCommission]) }}">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-red-700">Retirer</button>
                        </form>
                    </li>
                @empty
                    <li class="text-stone-500">Aucun membre pour le moment.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('comites.membres.ajouter', $commission) }}" class="mt-2 flex gap-2 text-sm">
                @csrf
                <input type="text" name="membre" required maxlength="30" placeholder="Matricule ou téléphone du membre"
                       class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                <button type="submit" class="rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white">Ajouter</button>
            </form>

            <h3 class="mt-4 font-semibold text-emerald-900">Tâches de la commission</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($commission->actions as $tache)
                    <li class="flex justify-between gap-2">
                        <span>{{ $tache->description }} <span class="text-xs text-stone-500">· {{ $tache->responsable ? $tache->responsable->prenom : 'Commission' }}{{ $tache->echeance ? ' · '.$tache->echeance->format('d/m') : '' }}</span></span>
                        <span class="whitespace-nowrap text-xs font-semibold {{ $tache->statut === 'faite' ? 'text-emerald-800' : ($tache->estEnRetard() ? 'text-red-700' : 'text-stone-600') }}">
                            {{ $tache->estEnRetard() ? 'En retard' : \App\Models\ActionComite::STATUTS[$tache->statut] }}
                        </span>
                    </li>
                @empty
                    <li class="text-stone-500">Aucune tâche.</li>
                @endforelse
            </ul>
            @if ($commission->membres->isNotEmpty())
                <details class="mt-2">
                    <summary class="cursor-pointer text-sm font-semibold text-emerald-800">+ Confier une tâche</summary>
                    <form method="POST" action="{{ route('comites.taches.store', $commission) }}" class="mt-2 space-y-2 text-sm">
                        @csrf
                        <input type="text" name="description" required maxlength="255" placeholder="Tâche : acheter le riz au marché central"
                               class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                        <div class="grid grid-cols-2 gap-2">
                            <select name="membre_id" required class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                                @foreach ($commission->membres as $membreCommission)
                                    <option value="{{ $membreCommission->id }}">{{ $membreCommission->prenom }} {{ $membreCommission->nom }}</option>
                                @endforeach
                            </select>
                            <input type="date" name="echeance" min="{{ now()->format('Y-m-d') }}"
                                   class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white">Confier la tâche</button>
                    </form>
                </details>
            @endif

            <h3 class="mt-4 font-semibold text-emerald-900">Nos besoins</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($commission->besoins as $besoin)
                    <li class="flex justify-between gap-2">
                        <span>{{ $besoin->quantite }} × {{ $besoin->designation }}</span>
                        <span class="whitespace-nowrap {{ $besoin->statut === 'retenu' ? 'text-emerald-800' : ($besoin->statut === 'ecarte' ? 'text-red-700' : 'text-amber-700') }}">
                            {{ number_format($besoin->montant(), 0, ',', ' ') }} · {{ \App\Models\BesoinCommission::STATUTS[$besoin->statut] }}
                        </span>
                    </li>
                @empty
                    <li class="text-stone-500">Aucun besoin exprimé.</li>
                @endforelse
            </ul>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm font-semibold text-emerald-800">+ Exprimer un besoin</summary>
                <form method="POST" action="{{ route('comites.besoins.store', $commission) }}" class="mt-2 space-y-2 text-sm">
                    @csrf
                    <input type="text" name="designation" required maxlength="150" placeholder="Article : sacs de riz de 50 kg"
                           class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" name="quantite" required min="1" value="1" placeholder="Quantité"
                               class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                        <input type="number" name="prix_unitaire" required min="1" placeholder="Prix unitaire (FCFA)"
                               class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white">Envoyer au président</button>
                </form>
            </details>

            <h3 class="mt-4 font-semibold text-emerald-900">Nos dépenses</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @forelse ($commission->depenses as $depense)
                    <li class="flex justify-between gap-2">
                        <span>{{ $depense->date_depense->format('d/m') }} · {{ $depense->objet }}</span>
                        <span class="whitespace-nowrap {{ $depense->statut === 'validee' ? 'text-emerald-800' : ($depense->statut === 'refusee' ? 'text-red-700' : 'text-amber-700') }}">
                            {{ number_format($depense->montant, 0, ',', ' ') }} · {{ \App\Models\DepenseCommission::STATUTS[$depense->statut] }}
                        </span>
                    </li>
                @empty
                    <li class="text-stone-500">Aucune dépense déclarée.</li>
                @endforelse
            </ul>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm font-semibold text-emerald-800">+ Déclarer une dépense</summary>
                <form method="POST" action="{{ route('comites.depenses.store', $commission) }}" enctype="multipart/form-data" class="mt-2 space-y-2 text-sm">
                    @csrf
                    <input type="text" name="objet" required maxlength="150" placeholder="Objet : 10 sacs de riz"
                           class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" name="montant" required min="1" placeholder="Montant (FCFA)"
                               class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                        <input type="date" name="date_depense" required value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}"
                               class="rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                    </div>
                    <input type="text" name="fournisseur" maxlength="100" placeholder="Fournisseur (facultatif)"
                           class="block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-base">
                    <label class="block text-xs text-stone-600">Photo de la facture ou du reçu (facultatif)
                        <input type="file" name="justificatif" accept="image/*,application/pdf" capture="environment" class="mt-1 block w-full text-sm">
                    </label>
                    <button type="submit" class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white">Déclarer au Jewrine finance</button>
                </form>
            </details>
        </section>
    @endforeach

    @forelse ($instances as $instance)
        <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <h2 class="text-lg font-semibold text-emerald-950">{{ $instance->nom }}</h2>
            @if ($instance->commissions->isNotEmpty())
                <p class="mt-1 text-xs text-stone-500">
                    Commissions : {{ $instance->commissions->map(fn ($commission) => $commission->nom.($commission->chef ? ' ('.$commission->chef->prenom.' '.$commission->chef->nom.')' : ''))->implode(' · ') }}
                </p>
            @endif

            <ul class="mt-3 space-y-3">
                @forelse ($instance->reunions as $reunion)
                    <li class="rounded-lg bg-stone-50 p-3 text-sm">
                        <p class="font-semibold text-emerald-900">{{ $reunion->titre }}</p>
                        <p class="text-xs text-stone-600">{{ $reunion->debut->translatedFormat('l j F à H\hi') }} · {{ $reunion->lieu }}</p>
                        @if ($reunion->estPubliee())
                            <a href="{{ route('reunions.pv', $reunion) }}" class="mt-2 inline-block font-semibold text-emerald-800">📄 Lire le PV</a>
                        @elseif ($reunion->points->isNotEmpty())
                            <ol class="mt-2 list-decimal pl-5 text-xs text-stone-700">
                                @foreach ($reunion->points as $point)
                                    <li>{{ $point->titre }}</li>
                                @endforeach
                            </ol>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-stone-600">Aucune réunion pour le moment.</li>
                @endforelse
            </ul>
        </section>
    @empty
        <p class="mt-4 rounded-xl bg-white p-6 text-center text-sm text-stone-600 ring-1 ring-stone-200">Vous ne faites partie d'aucun comité pour le moment.</p>
    @endforelse
@endsection
