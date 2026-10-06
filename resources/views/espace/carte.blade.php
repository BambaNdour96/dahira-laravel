@extends('layouts.public')

@section('titre', 'Ma carte de membre')

@section('contenu')
    <h1 class="text-2xl font-semibold text-emerald-900">Ma carte de membre</h1>

    <div class="mx-auto mt-6 w-full max-w-md">
        <div class="flex aspect-[85.6/54] flex-col overflow-hidden rounded-2xl bg-linear-to-br from-emerald-800 to-emerald-950 text-white shadow-xl ring-2 ring-or-400">

            {{-- En-tête --}}
            <div class="flex items-center gap-2 border-b border-or-400/60 px-[4%] py-[2.5%]">
                <img src="{{ asset('images/logo-ndk.png') }}" alt="" class="aspect-square w-[9%] rounded-full bg-white object-cover p-0.5">
                <div class="leading-tight">
                    <p class="text-[clamp(9px,2.8vw,13px)] font-bold tracking-wide uppercase">Daara Nouroud Daareyni Kaolack</p>
                    <p class="text-[clamp(7px,2.1vw,10px)] tracking-[0.2em] text-or-200 uppercase">Carte de membre</p>
                </div>
            </div>

            {{-- Corps : photo, informations, QR --}}
            <div class="flex flex-1 items-center gap-[3%] px-[4%] py-[3%]">
                <div class="aspect-[3/4] w-[24%] shrink-0 overflow-hidden rounded-lg bg-emerald-950 ring-2 ring-white">
                    @if ($membre->photo_path)
                        <img src="{{ route('photos.show', $membre) }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>

                <div class="min-w-0 flex-1 space-y-[4%]">
                    <div>
                        <p class="truncate text-[clamp(10px,3vw,14px)] leading-tight font-medium">{{ $membre->prenom }}</p>
                        <p class="truncate text-[clamp(13px,4.2vw,20px)] leading-tight font-bold tracking-wide">{{ $membre->nom }}</p>
                    </div>

                    @if ($membre->poste)
                        <p class="inline-block rounded bg-or-500 px-1.5 py-0.5 text-[clamp(8px,2.3vw,11px)] font-semibold text-emerald-950">
                            {{ $membre->poste->nom }}
                        </p>
                    @endif

                    <dl class="grid grid-cols-2 gap-x-2 text-[clamp(8px,2.4vw,11px)]">
                        <div>
                            <dt class="text-or-200 uppercase">Matricule</dt>
                            <dd class="font-mono font-semibold">{{ $membre->matricule }}</dd>
                        </div>
                        <div>
                            <dt class="text-or-200 uppercase">Cellule</dt>
                            <dd class="truncate font-semibold">{{ $membre->cellule->nom }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="aspect-square w-[28%] shrink-0 rounded-lg bg-white p-[1.5%] [&>svg]:h-full [&>svg]:w-full">
                    {!! $qr !!}
                </div>
            </div>

            {{-- Bandeau du bas --}}
            <div class="flex items-center justify-between bg-or-500 px-[4%] py-[1.5%] text-[clamp(8px,2.3vw,11px)] font-semibold text-emerald-950">
                <span>Membre depuis {{ $membre->date_adhesion?->format('Y') ?? now()->format('Y') }}</span>
                <span>Vérifiable par QR code</span>
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
