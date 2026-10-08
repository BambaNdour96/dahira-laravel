<div>
    <label for="indicatif" class="block text-sm font-medium">Pays du numéro</label>
    <select id="indicatif" name="indicatif" autocomplete="tel-country-code"
            class="mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base">
        @foreach (\App\Support\Indicatifs::LISTE as $code => $libelle)
            <option value="{{ $code }}" @selected((string) old('indicatif', \App\Support\Indicatifs::PAR_DEFAUT) === (string) $code)>{{ $libelle }}</option>
        @endforeach
    </select>
</div>
