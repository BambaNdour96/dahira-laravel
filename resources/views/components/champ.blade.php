@props(['nom', 'libelle', 'type' => 'text', 'requis' => false, 'aide' => null])

<div>
    <label for="{{ $nom }}" class="block text-sm font-medium">
        {{ $libelle }}@if ($requis) <span class="text-red-700">*</span>@endif
    </label>
    <input
        id="{{ $nom }}"
        name="{{ $nom }}"
        type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($nom, $attributes->get('value')) }}"
        @required($requis)
        {{ $attributes->except('value')->merge(['class' => 'mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20']) }}
    >
    @if ($aide)
        <p class="mt-1 text-xs text-stone-500">{{ $aide }}</p>
    @endif
    @error($nom)
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
