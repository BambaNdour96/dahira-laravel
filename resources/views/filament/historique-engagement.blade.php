<div class="space-y-3 text-sm">
    @forelse ($lignes as $ligne)
        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
            <p class="font-semibold">
                {{ $ligne->ancien === null ? 'Engagement fixé' : number_format($ligne->ancien, 0, ',', ' ').' → ' }}
                {{ number_format($ligne->nouveau, 0, ',', ' ') }} FCFA
            </p>
            <p class="text-gray-600 dark:text-gray-400">{{ $ligne->motif }}</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ $ligne->created_at->format('d/m/Y à H:i') }} · {{ $ligne->auteur?->name ?? 'Système' }}
            </p>
        </div>
    @empty
        <p class="text-gray-500">Aucune modification.</p>
    @endforelse
</div>
