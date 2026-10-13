<div class="space-y-2 text-sm">
    @forelse ($remises as $remise)
        <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-700">
            <span>{{ $remise->numero }} · {{ $remise->created_at->format('d/m/Y') }} · {{ number_format($remise->montant, 0, ',', ' ') }} FCFA</span>
            <a href="{{ route('remises.bordereau', $remise) }}" class="font-semibold text-primary-600">Bordereau</a>
        </div>
    @empty
        <p class="text-gray-500">Aucune remise pour le moment.</p>
    @endforelse
</div>
