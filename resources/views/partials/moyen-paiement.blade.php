{{-- Le moyen de paiement déclaré : Wave ou Orange Money. Le contrôle de la référence suit le choix. --}}
@php
    $moyenChoisi = old('moyen', 'wave');
    $numeroOrange = \App\Models\Parametre::valeur('orange_money_numero');
@endphp
<fieldset class="text-sm" data-moyen-paiement>
    <legend class="sr-only">Payé avec</legend>
    <div class="grid grid-cols-2 gap-2">
        @foreach (['wave' => 'Wave', 'orange_money' => 'Orange Money'] as $code => $libelle)
            <label class="flex cursor-pointer items-center justify-center rounded-lg border border-stone-300 px-3 py-2 font-medium has-[:checked]:border-emerald-700 has-[:checked]:bg-emerald-50">
                <input type="radio" name="moyen" value="{{ $code }}" class="sr-only" @checked($moyenChoisi === $code)>
                Payé avec {{ $libelle }}
            </label>
        @endforeach
    </div>
    @if ($numeroOrange)
        <p class="mt-1 text-xs text-stone-500">Orange Money : envoyez au {{ $numeroOrange }}, puis recopiez la référence du SMS.</p>
    @endif
</fieldset>
