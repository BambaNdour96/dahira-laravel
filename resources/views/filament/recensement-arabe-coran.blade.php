<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Informations confidentielles : seuls le membre, la commission Culturelle et le super administrateur les voient.
        Le Jewrine de cellule voit seulement, pour sa cellule, qui souhaite apprendre. Aucun export.
    </p>

    @include('filament.partials.chiffres-arabe-coran', ['chiffres' => $chiffres])

    {{ $this->table }}
</x-filament-panels::page>
