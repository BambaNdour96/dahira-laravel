{{-- La rubrique « Arabe et Coran », dans un formulaire : $profil (ou null), $legende. --}}
@php
    $champ = 'mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2.5 text-base';
    $valeur = fn (string $nom) => old($nom, $profil?->getAttribute($nom) instanceof \BackedEnum ? $profil->getAttribute($nom)->value : $profil?->getAttribute($nom));
    $souhait = old('souhaite_apprendre', $profil?->souhaite_apprendre === null ? null : ($profil->souhaite_apprendre ? '1' : '0'));
@endphp

<fieldset class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-200" data-test="arabe-coran">
    <legend class="px-1 font-semibold text-emerald-900">{{ $legende }}</legend>
    <p class="text-xs text-stone-500">Ces informations ne sont vues que par vous, la commission Culturelle et le super administrateur.</p>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="lecture" class="block text-sm font-medium">Lecture de l'arabe</label>
            <select id="lecture" name="lecture" class="{{ $champ }}">
                <option value="">Choisir</option>
                @foreach (\App\Enums\NiveauLecture::cases() as $niveau)
                    <option value="{{ $niveau->value }}" @selected($valeur('lecture') === $niveau->value)>{{ $niveau->libelle() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ecriture" class="block text-sm font-medium">Écriture</label>
            <select id="ecriture" name="ecriture" class="{{ $champ }}">
                <option value="">Choisir</option>
                @foreach (\App\Enums\NiveauLecture::cases() as $niveau)
                    <option value="{{ $niveau->value }}" @selected($valeur('ecriture') === $niveau->value)>{{ $niveau->libelle(true) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="comprehension" class="block text-sm font-medium">Compréhension</label>
            <select id="comprehension" name="comprehension" class="{{ $champ }}">
                <option value="">Choisir</option>
                @foreach (\App\Enums\NiveauComprehension::cases() as $niveau)
                    <option value="{{ $niveau->value }}" @selected($valeur('comprehension') === $niveau->value)>{{ $niveau->getLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="khassaides" class="block text-sm font-medium">Khassaides</label>
            <select id="khassaides" name="khassaides" class="{{ $champ }}">
                <option value="">Choisir</option>
                @foreach (\App\Enums\NiveauKhassaides::cases() as $niveau)
                    <option value="{{ $niveau->value }}" @selected($valeur('khassaides') === $niveau->value)>{{ $niveau->getLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="coran" class="block text-sm font-medium">Coran mémorisé</label>
            <select id="coran" name="coran" class="{{ $champ }}">
                <option value="">Choisir</option>
                @foreach (\App\Enums\NiveauCoran::cases() as $niveau)
                    <option value="{{ $niveau->value }}" @selected($valeur('coran') === $niveau->value)>{{ $niveau->getLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="nombre_hizb" class="block text-sm font-medium">Nombre de hizb</label>
            <input id="nombre_hizb" name="nombre_hizb" type="number" min="1" max="{{ \App\Enums\NiveauCoran::HIZB_MAX }}" inputmode="numeric"
                   value="{{ $valeur('nombre_hizb') }}" class="{{ $champ }}">
            <p class="mt-1 text-xs text-stone-500">Si vous avez choisi « un nombre de hizb » (60 hizb : hafiz).</p>
            @error('nombre_hizb')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <p class="block text-sm font-medium">Souhaite apprendre (ou se perfectionner)</p>
        <div class="mt-1 grid grid-cols-2 gap-2">
            @foreach (['1' => 'Oui', '0' => 'Non'] as $code => $libelle)
                <label class="flex cursor-pointer items-center justify-center rounded-lg border border-stone-300 px-3 py-2.5 text-sm has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                    <input type="radio" name="souhaite_apprendre" value="{{ $code }}" class="sr-only" @checked($souhait === $code)>
                    {{ $libelle }}
                </label>
            @endforeach
        </div>
    </div>
</fieldset>
