<li class="overflow-hidden rounded-xl {{ $annonce->importante ? 'bg-or-100 ring-1 ring-or-400' : 'bg-white ring-1 ring-stone-200' }}">
    @if ($annonce->urlAffiche())
        <img src="{{ $annonce->urlAffiche() }}" alt="Affiche : {{ $annonce->titre }}" loading="lazy" class="w-full">
    @endif
    <div class="p-4">
        <div class="flex flex-wrap items-center gap-2">
            @if ($annonce->importante)
                <span class="rounded-full bg-emerald-800 px-2 py-0.5 text-xs font-semibold text-white">Important</span>
            @endif
            <span class="text-xs text-stone-500">{{ $annonce->created_at->translatedFormat('j F Y') }}</span>
        </div>
        <p class="mt-1 font-semibold text-emerald-950">{{ $annonce->titre }}</p>
        @if ($annonce->contenu)
            <p class="mt-1 text-sm leading-relaxed text-stone-700">{!! nl2br(e(($complet ?? false) ? $annonce->contenu : \Illuminate\Support\Str::limit($annonce->contenu, 180))) !!}</p>
        @endif
    </div>
</li>
