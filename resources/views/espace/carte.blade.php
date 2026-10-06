@extends('layouts.public')

@section('titre', 'Ma carte de membre')

@section('contenu')
    @php
        $telephone = preg_match('/^\+221(\d{2})(\d{3})(\d{2})(\d{2})$/', $membre->telephone, $morceaux)
            ? "+221 {$morceaux[1]} {$morceaux[2]} {$morceaux[3]} {$morceaux[4]}"
            : $membre->telephone;
    @endphp

    <h1 class="text-2xl font-semibold text-emerald-900">Ma carte de membre</h1>

    <div class="mx-auto mt-6 w-full max-w-md">
        <div class="relative flex aspect-[85.6/54] flex-col overflow-hidden rounded-2xl bg-white text-stone-900 shadow-xl ring-1 ring-stone-300">

            {{-- Grand logo en filigrane, comme sur les affiches --}}
            <img src="{{ asset('images/logo-ndk.png') }}" alt=""
                 class="pointer-events-none absolute top-1/2 left-1/2 w-[55%] -translate-x-1/2 -translate-y-1/2 opacity-[0.07]">

            {{-- En-tête --}}
            <div class="relative flex items-center gap-[3%] px-[4%] pt-[3%] pb-[2%]">
                <img src="{{ asset('images/logo-ndk.png') }}" alt="" class="aspect-square w-[12%] rounded-full object-cover">
                <div class="leading-tight">
                    <p class="text-[clamp(9px,2.9vw,14px)] font-extrabold tracking-wide uppercase">Daara Nouroud Daareyni Kaolack</p>
                    <p class="text-[clamp(7px,2.2vw,10px)] font-semibold tracking-[0.2em] text-emerald-700 uppercase">Carte de membre · NDK</p>
                </div>
            </div>
            <div class="relative flex h-1.5">
                <div class="w-2/3 bg-emerald-700"></div>
                <div class="w-1/3 bg-or-500"></div>
            </div>

            {{-- Corps : photo, informations, QR --}}
            <div class="relative flex flex-1 items-center gap-[3%] px-[4%] py-[2.5%]">
                <div class="aspect-[3/4] w-[24%] shrink-0 overflow-hidden rounded-lg bg-stone-100 ring-2 ring-emerald-700">
                    @if ($membre->photo_path)
                        <img src="{{ route('photos.show', $membre) }}" alt="" class="h-full w-full object-cover">
                    @endif
                </div>

                <div class="min-w-0 flex-1 space-y-[3%]">
                    <div>
                        <p class="truncate text-[clamp(10px,3vw,14px)] leading-tight font-semibold text-stone-700">{{ $membre->prenom }}</p>
                        <p class="truncate text-[clamp(13px,4.2vw,20px)] leading-tight font-extrabold tracking-wide text-emerald-900">{{ $membre->nom }}</p>
                    </div>

                    @if ($membre->poste)
                        <p class="inline-block rounded bg-or-500 px-1.5 py-0.5 text-[clamp(8px,2.2vw,10px)] font-semibold text-emerald-950">
                            {{ $membre->poste->nom }}
                        </p>
                    @endif

                    <dl class="grid grid-cols-2 gap-x-2 gap-y-[2%] text-[clamp(8px,2.4vw,11px)]">
                        <div>
                            <dt class="font-semibold text-emerald-700 uppercase">Matricule</dt>
                            <dd class="font-mono font-bold">{{ $membre->matricule }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-emerald-700 uppercase">Cellule</dt>
                            <dd class="truncate font-bold">{{ $membre->cellule->nom }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="font-semibold text-emerald-700 uppercase">Téléphone</dt>
                            <dd class="font-bold">{{ $telephone }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="aspect-square w-[27%] shrink-0 rounded-lg bg-white p-[1.5%] ring-1 ring-stone-300 [&>svg]:h-full [&>svg]:w-full">
                    {!! $qr !!}
                </div>
            </div>

            {{-- Bandeau du bas --}}
            <div class="relative flex items-center justify-between bg-emerald-800 px-[4%] py-[1.8%] text-[clamp(8px,2.3vw,11px)] font-semibold text-white">
                <span>Membre depuis {{ $membre->date_adhesion?->format('Y') ?? now()->format('Y') }}</span>
                <span class="text-or-200">Vérifiable par QR code</span>
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
