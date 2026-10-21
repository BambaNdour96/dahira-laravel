<?php

use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\TypeProgramme;
use App\Models\User;
use App\Services\CasquettesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Nomme directement une personne à un poste, sans les contrôles de NominationService (préparation d'un test).
 * Le compte reçoit aussitôt les droits du poste.
 */
function nommerPourLeTest(User $personne, string $code, ?Cellule $cellule = null, ?Commission $commission = null): User
{
    Nomination::query()->create([
        'user_id' => $personne->id,
        'poste_id' => Poste::query()->where('code', $code)->firstOrFail()->id,
        'cellule_id' => $cellule?->id,
        'commission_id' => $commission?->id,
        'debut_le' => now(),
    ]);
    app(CasquettesService::class)->synchroniser($personne);

    return $personne->fresh() ?? $personne;
}

/**
 * Une cellule dont cette personne est le Jewrine.
 *
 * @param  array<string, mixed>  $attributs
 */
function celluleDirigeePar(User $jewrine, array $attributs = []): Cellule
{
    $cellule = Cellule::factory()->create($attributs);
    nommerPourLeTest($jewrine, 'jewrine_cellule', $cellule);

    return $cellule;
}

/** L'identifiant d'un type d'événement (magal, ziar, prokhane, gamou, laylatoul_qadr), créé par la migration. */
function typeProgramme(string $code): int
{
    return TypeProgramme::parCode($code)->id;
}
