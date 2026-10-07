<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1e5223">
    <title>Vérification de carte – Daara Nouroud Daareyni Kaolack</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-ndk.png') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    <main class="mx-auto max-w-md px-4 py-8">
        <div class="flex items-center justify-center gap-3">
            <img src="{{ asset('images/logo-ndk.png') }}" alt="" class="h-12 w-12 rounded-full bg-white object-cover p-0.5 ring-2 ring-or-400">
            <div class="leading-tight">
                <p class="font-semibold text-emerald-900">Daara Nouroud Daareyni</p>
                <p class="text-xs tracking-widest text-or-600 uppercase">Kaolack · Vérification</p>
            </div>
        </div>

        @if ($membre)
            @if ($retard > 0)
                <div class="mt-6 rounded-2xl bg-amber-600 p-5 text-center text-white shadow-lg">
                    <p class="text-4xl">!</p>
                    <p class="mt-1 text-2xl font-bold">Membre actif</p>
                    <p class="mt-1 font-semibold">Cotisation en retard : {{ $retard }} mois</p>
                </div>
            @else
                <div class="mt-6 rounded-2xl bg-emerald-700 p-5 text-center text-white shadow-lg">
                    <p class="text-4xl">✓</p>
                    <p class="mt-1 text-2xl font-bold">Membre actif</p>
                    <p class="mt-1 font-semibold">Cotisation à jour</p>
                </div>
            @endif

            <div class="mt-4 flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
                @if ($photo)
                    <img src="{{ $photo }}" alt="" class="h-24 w-20 shrink-0 rounded-lg object-cover ring-2 ring-or-400">
                @endif
                <div>
                    <p class="text-lg font-bold text-emerald-950">{{ $membre->prenom }} {{ $membre->nom }}</p>
                    @if ($membre->poste)
                        <p class="text-sm text-or-600">{{ $membre->poste->nom }}</p>
                    @endif
                    <p class="mt-2 text-sm text-stone-600">Cellule {{ $membre->cellule->nom }}</p>
                    <p class="font-mono text-sm">{{ $membre->matricule }}</p>
                </div>
            </div>
        @else
            <div class="mt-6 rounded-2xl bg-red-700 p-6 text-center text-white shadow-lg">
                <p class="text-4xl">✕</p>
                <p class="mt-1 text-2xl font-bold">Carte non valide</p>
                <p class="mt-2 text-sm text-red-100">Cette carte n'est pas reconnue par la Daara.</p>
            </div>
        @endif

        <p class="mt-6 text-center text-xs text-stone-500">Vérifié le {{ now()->format('d/m/Y à H:i') }}</p>
    </main>
</body>
</html>
