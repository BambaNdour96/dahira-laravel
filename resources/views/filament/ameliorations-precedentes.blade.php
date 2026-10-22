<x-filament-widgets::widget>
    @if ($ameliorations !== [])
        <x-filament::section heading="💡 Les améliorations notées la dernière fois" description="À revoir pour la mise en place de ce comité.">
            <ul class="space-y-3 text-sm">
                @foreach ($ameliorations as $amelioration)
                    <li>
                        <p class="text-xs font-semibold text-gray-500">{{ $amelioration['source'] }}</p>
                        <p class="whitespace-pre-line">{{ $amelioration['texte'] }}</p>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
