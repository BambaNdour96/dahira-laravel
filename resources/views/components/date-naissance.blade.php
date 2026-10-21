@props(['value' => null, 'aide' => null])

@php
    $date = old('date_naissance', $value);
    [$annee, $mois, $jour] = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
        ? array_map('intval', explode('-', $date))
        : [null, null, null];
    $annee = (int) old('date_naissance_annee', $annee) ?: null;
    $mois = (int) old('date_naissance_mois', $mois) ?: null;
    $jour = (int) old('date_naissance_jour', $jour) ?: null;
    $nomsMois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $classe = 'block w-full rounded-lg border border-stone-300 bg-white px-2 py-2.5 text-base focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20';
@endphp

<fieldset>
    <legend class="block text-sm font-medium">Date de naissance <span class="text-red-700">*</span></legend>
    <div class="mt-1 grid grid-cols-[1fr_1.6fr_1.3fr] gap-2">
        <select name="date_naissance_jour" aria-label="Jour de naissance" required class="{{ $classe }}">
            <option value="">Jour</option>
            @for ($j = 1; $j <= 31; $j++)
                <option value="{{ $j }}" @selected($jour === $j)>{{ $j }}</option>
            @endfor
        </select>
        <select name="date_naissance_mois" aria-label="Mois de naissance" required class="{{ $classe }}">
            <option value="">Mois</option>
            @foreach ($nomsMois as $index => $nom)
                <option value="{{ $index + 1 }}" @selected($mois === $index + 1)>{{ $nom }}</option>
            @endforeach
        </select>
        <select name="date_naissance_annee" aria-label="Année de naissance" required class="{{ $classe }}">
            <option value="">Année</option>
            @for ($a = (int) now()->format('Y'); $a >= 1920; $a--)
                <option value="{{ $a }}" @selected($annee === $a)>{{ $a }}</option>
            @endfor
        </select>
    </div>
    @if ($aide)
        <p class="mt-1 text-xs text-stone-500">{{ $aide }}</p>
    @endif
    @error('date_naissance')
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</fieldset>
