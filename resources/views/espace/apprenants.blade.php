@extends('layouts.public')

@section('titre', 'Souhaitent apprendre')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Souhaitent apprendre l'arabe et le Coran</h1>
    <p class="mt-1 text-sm text-stone-600">Les membres qui ont indiqué ce souhait, pour organiser des cours. Leur niveau reste confidentiel.</p>

    <ul class="mt-5 divide-y divide-stone-100 rounded-xl bg-white shadow-sm ring-1 ring-stone-200">
        @forelse ($apprenants as $membre)
            @php
                $numero = $membre->telephone ?? $membre->responsable?->telephone;
            @endphp
            <li class="flex items-center justify-between gap-2 px-4 py-3 text-sm">
                <span class="min-w-0">
                    <span class="block truncate font-semibold text-emerald-950">{{ $membre->prenom }} {{ $membre->nom }}</span>
                    <span class="text-xs text-stone-500">{{ $membre->cellule->nom }}</span>
                </span>
                @if ($numero)
                    <a href="tel:{{ $numero }}" class="shrink-0 font-semibold text-emerald-800 underline">{{ $numero }}</a>
                @endif
            </li>
        @empty
            <li class="px-4 py-3 text-sm text-stone-600">Personne pour le moment.</li>
        @endforelse
    </ul>

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
