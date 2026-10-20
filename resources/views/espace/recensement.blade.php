@extends('layouts.public')

@section('titre', 'Recensement Arabe et Coran')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Recensement Arabe et Coran</h1>
    <p class="mt-1 text-sm text-stone-600">Commission Culturelle · informations confidentielles, à ne pas partager hors de la commission.</p>

    <div class="mt-5 overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-stone-200">
        <table class="w-full text-left text-xs">
            <thead class="bg-stone-50 text-stone-500">
                <tr>
                    <th class="px-3 py-2">Cellule</th>
                    <th class="px-2 py-2">Remplie</th>
                    <th class="px-2 py-2">Lisent</th>
                    <th class="px-2 py-2">Hafiz</th>
                    <th class="px-2 py-2">Hizb</th>
                    <th class="px-2 py-2">Kourel</th>
                    <th class="px-2 py-2">Apprendre</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($chiffres as $ligne)
                    <tr>
                        <td class="px-3 py-2 font-semibold text-emerald-950">{{ $ligne['cellule'] }}</td>
                        <td class="px-2 py-2">{{ $ligne['remplies'] }}/{{ $ligne['membres'] }}</td>
                        <td class="px-2 py-2">{{ $ligne['lisent'] }}</td>
                        <td class="px-2 py-2">{{ $ligne['hafiz'] }}</td>
                        <td class="px-2 py-2">{{ $ligne['hizb'] }}</td>
                        <td class="px-2 py-2">{{ $ligne['kourel'] }}</td>
                        <td class="px-2 py-2">{{ $ligne['apprendre'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="GET" action="{{ route('recensement.index') }}" class="mt-5 grid grid-cols-2 gap-2 text-sm">
        <select name="cellule" aria-label="Cellule" class="rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
            <option value="">Toutes les cellules</option>
            @foreach ($cellules as $id => $nom)
                <option value="{{ $id }}" @selected($celluleId === $id)>{{ $nom }}</option>
            @endforeach
        </select>
        <select name="critere" aria-label="Critère" class="rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
            <option value="">Tous les membres</option>
            @foreach (\App\Services\ArabeCoranService::CRITERES as $code => $libelle)
                <option value="{{ $code }}" @selected($critere === $code)>{{ $libelle }}</option>
            @endforeach
        </select>
        <button type="submit" class="col-span-2 rounded-lg bg-emerald-800 px-4 py-2.5 font-semibold text-white">Filtrer</button>
    </form>

    <ul class="mt-4 space-y-3">
        @forelse ($membres as $membre)
            @php
                $profil = $membre->profilArabeCoran;
                $numero = $membre->telephone ?? $membre->responsable?->telephone;
            @endphp
            <li class="rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-stone-200">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-emerald-950">{{ $membre->prenom }} {{ $membre->nom }}</p>
                        <p class="text-xs text-stone-500">{{ $membre->cellule->nom }}</p>
                    </div>
                    @if ($numero)
                        <a href="tel:{{ $numero }}" class="shrink-0 text-xs font-semibold text-emerald-800 underline">{{ $numero }}</a>
                    @endif
                </div>
                @if ($profil && $profil->estRemplie())
                    <p class="mt-2 text-xs text-stone-700">
                        {{ $profil->lecture?->libelle() }} · {{ $profil->ecriture?->libelle(true) ?? 'Écriture : —' }} · {{ $profil->comprehension?->getLabel() ?? 'Compréhension : —' }}
                    </p>
                    <p class="text-xs text-stone-700">
                        Coran : {{ $profil->libelleCoran() ?? '—' }} · {{ $profil->khassaides?->getLabel() ?? 'Khassaides : —' }}
                        {{ $profil->souhaite_apprendre ? '· souhaite apprendre' : '' }}
                    </p>
                @else
                    <p class="mt-2 text-xs text-stone-500">Rubrique non remplie.</p>
                @endif
            </li>
        @empty
            <li class="rounded-xl bg-white p-4 text-sm text-stone-600 ring-1 ring-stone-200">Aucun membre pour ce filtre.</li>
        @endforelse
    </ul>

    <div class="mt-4">{{ $membres->links() }}</div>

    <a href="{{ route('comites.index') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mes comités</a>
@endsection
