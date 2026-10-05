<?php

use App\Services\TelephoneNormalizer;

it('normalise les numéros sénégalais', function (string $brut, string $attendu) {
    expect(TelephoneNormalizer::normaliser($brut))->toBe($attendu);
})->with([
    ['77 123 45 67', '+221771234567'],
    ['771234567', '+221771234567'],
    ['+221 77 123 45 67', '+221771234567'],
    ['00221771234567', '+221771234567'],
    ['33-823-45-67', '+221338234567'],
]);

it('accepte un numéro étranger au format international', function () {
    expect(TelephoneNormalizer::normaliser('+33 6 12 34 56 78'))->toBe('+33612345678');
});

it('refuse les numéros invalides', function (string $brut) {
    expect(TelephoneNormalizer::normaliser($brut))->toBeNull();
})->with(['12345', '+221 55 123 45 67', 'abc', '']);
