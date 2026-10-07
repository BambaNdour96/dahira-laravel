<?php

use App\Notifications\RappelCotisation;
use App\Support\AffichageMotDePasse;
use App\Support\BlocageCompte;
use App\Support\LienWave;
use Illuminate\Notifications\AnonymousNotifiable;

it('rédige l\'email de rappel de cotisation en français, avec le montant', function () {
    $rappel = new RappelCotisation('Awa', ['novembre 2026', 'décembre 2026'], 2000);
    $email = $rappel->toMail(new AnonymousNotifiable);

    expect($rappel->via(new AnonymousNotifiable))->toBe(['mail'])
        ->and($email->subject)->toContain('Rappel de cotisation')
        ->and($email->greeting)->toBe('Assalamou aleykoum Awa,')
        ->and(implode(' ', $email->introLines))->toContain('novembre 2026, décembre 2026')
        ->and(implode(' ', $email->introLines))->toContain('2 000 FCFA')
        ->and($email->actionUrl)->toBe(route('cotisations'));
});

it('affiche le mot de passe provisoire en grand, en protégeant le texte', function () {
    $html = AffichageMotDePasse::corps('<b>ABCD2345</b>')->toHtml();

    expect($html)->toContain('&lt;b&gt;ABCD2345&lt;/b&gt;')
        ->and($html)->not->toContain('<b>ABCD2345</b>');
});

it('génère des mots de passe provisoires sans caractères ambigus', function () {
    foreach (range(1, 50) as $essai) {
        expect(AffichageMotDePasse::generer())->toMatch('/^[A-HJ-NP-Z2-9]{8}$/');
    }
});

it('identifie le compte à bloquer à partir du numéro, quel que soit son format', function () {
    expect(BlocageCompte::cle(['telephone' => '77 123 45 67']))->toBe('blocage-compte:+221771234567')
        ->and(BlocageCompte::cle(['telephone' => '']))->toBeNull()
        ->and(BlocageCompte::cle([]))->toBeNull();
});

it('gère les liens Wave vides ou mal formés sans planter', function () {
    expect(LienWave::avecMontant(null, 1000))->toBeNull()
        ->and(LienWave::avecMontant('', 1000))->toBeNull()
        ->and(LienWave::avecMontant('pas-une-adresse', 1000))->toBe('pas-une-adresse');
});
