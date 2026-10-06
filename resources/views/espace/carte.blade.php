@extends('layouts.public')

@section('titre', 'Ma carte de membre')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Ma carte de membre</h1>

    <div class="mx-auto mt-6 w-full max-w-md">
        <div class="aspect-[85.6/54] overflow-hidden rounded-2xl bg-linear-to-br from-emerald-800 to-emerald-950 p-4 text-white shadow-xl ring-2 ring-or-400">
            <div class="flex items-center gap-2 border-b border-or-400/60 pb-2">
                <img src="{{ asset('images/logo-ndk.png') }}" alt="" class="h-9 w-9 rounded-full bg-white object-cover p-0.5">
                <div class="leading-tight">
                    <p class="text-xs font-bold tracking-wide uppercase">Daara Nouroud Daareyni</p>
                    <p class="text-[10px] tracking-widest text-or-200 uppercase">Kaolack · Carte de membre</p>
                </div>
            </div>

            <div class="mt-3 flex gap-3">
                @if ($membre->photo_path)
                    <img src="{{ route('photos.show', $membre) }}" alt="" class="h-24 w-20 shrink-0 rounded-lg object-cover ring-2 ring-white">
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate text-base leading-tight font-bold">{{ $membre->prenom }}</p>
                    <p class="truncate text-base leading-tight font-bold">{{ $membre->nom }}</p>
                    @if ($membre->poste)
                        <p class="mt-1 truncate text-xs text-or-200">{{ $membre->poste->nom }}</p>
                    @endif
                    <p class="mt-2 font-mono text-sm">{{ $membre->matricule }}</p>
                    <p class="text-xs text-emerald-100">Cellule {{ $membre->cellule->nom }}</p>
                </div>
                <div class="h-24 w-24 shrink-0 rounded-lg bg-white p-1 [&>svg]:h-full [&>svg]:w-full">
                    {!! $qr !!}
                </div>
            </div>
        </div>
    </div>

    <p class="mt-6 text-center text-sm text-stone-600">
        Présentez cette carte lors des événements. Le QR code permet de vérifier votre adhésion.
    </p>
    <p class="mt-2 text-center text-xs text-stone-500">
        Carte perdue ? Contactez le bureau : l'ancienne carte sera désactivée et une nouvelle vous sera remise.
    </p>

    <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-emerald-800 underline">Retour à mon compte</a>
@endsection
