<li class="overflow-hidden rounded-xl bg-white ring-1 ring-stone-200">
    @if ($evenement->urlAffiche())
        <img src="{{ $evenement->urlAffiche() }}" alt="Affiche : {{ $evenement->titre }}" loading="lazy" class="w-full">
    @endif
    <div class="p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-or-700">
            {{ ucfirst($evenement->debut->translatedFormat('l j F Y · H\hi')) }}
        </p>
        <p class="mt-1 font-semibold text-emerald-950">{{ $evenement->titre }}</p>
        <p class="text-sm text-stone-600">{{ $evenement->lieu }} · {{ $evenement->libelleCible() }}</p>
        @if (($complet ?? false) && $evenement->description)
            <p class="mt-2 text-sm leading-relaxed text-stone-700">{!! nl2br(e($evenement->description)) !!}</p>
        @endif
    </div>
</li>
