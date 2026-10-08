@extends('layouts.public')

@section('titre', 'Annonces')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Annonces de la Daara</h1>

    @if ($annonces->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">
            Aucune annonce pour le moment.
        </div>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($annonces as $annonce)
                @include('espace.partials.annonce', ['annonce' => $annonce, 'complet' => true])
            @endforeach
        </ul>

        <div class="mt-6">{{ $annonces->links() }}</div>
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
