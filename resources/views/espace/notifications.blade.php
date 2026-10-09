@extends('layouts.public')

@section('titre', 'Mes notifications')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Mes notifications</h1>

    @if ($notifications->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">
            Aucune notification pour le moment.
        </div>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($notifications as $notification)
                @php($nouvelle = in_array($notification->id, $nouvelles, true))
                <li class="rounded-xl p-4 shadow-sm {{ $nouvelle ? 'bg-or-100 ring-1 ring-or-400' : 'bg-white ring-1 ring-stone-200' }}">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-emerald-950">{{ $notification->data['titre'] ?? '' }}</p>
                        <span class="shrink-0 text-xs text-stone-500">{{ $notification->created_at?->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1 text-sm text-stone-700">{{ $notification->data['message'] ?? '' }}</p>
                    @if (! empty($notification->data['lien']))
                        <a href="{{ \App\Notifications\NotificationMembre::chemin($notification->data['lien']) }}"
                           class="mt-3 inline-block rounded-lg bg-emerald-800 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-900">
                            {{ $notification->data['libelle_lien'] ?? 'Ouvrir' }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
