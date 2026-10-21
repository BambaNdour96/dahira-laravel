<?php

use App\Http\Requests\InscriptionRequest;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use App\Services\ActivationCompteService;
use App\Services\FamilleService;
use App\Services\ImportMembresService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Les erreurs de la date de naissance à l'inscription, pour un âge donné.
 *
 * @return list<string>
 */
function erreursDAge(int $ans): array
{
    $regles = new InscriptionRequest;
    $validation = Validator::make(['date_naissance' => now()->subYears($ans)->subDay()->toDateString()], ['date_naissance' => $regles->rules()['date_naissance']], $regles->messages());

    return array_values($validation->errors()->get('date_naissance'));
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    Storage::fake('local');
    $this->cellule = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->familles = app(FamilleService::class);

    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $this->parent = Membre::factory()->for($compte)->valide()->create(['cellule_id' => $this->cellule->id, 'telephone' => '+221770000001']);
});

it('demande 12 ans pour s\'inscrire soi-même, un âge que le super administrateur règle', function () {
    expect(Membre::ageMinimumInscription())->toBe(12)
        ->and(erreursDAge(11))->toBe(['Il faut avoir au moins 12 ans pour s\'inscrire soi-même. Un enfant plus jeune est inscrit par son parent, depuis « Ma famille ».'])
        ->and(erreursDAge(12))->toBe([]);

    $this->get(route('inscription'))->assertSee('Il faut avoir au moins 12 ans pour s\'inscrire soi-même.');

    Parametre::query()->where('cle', 'age_minimum_inscription')->update(['valeur' => '10']);
    expect(Membre::ageMinimumInscription())->toBe(10)->and(erreursDAge(11))->toBe([]);

    Parametre::query()->where('cle', 'age_minimum_inscription')->update(['valeur' => 'douze']);
    expect(Membre::ageMinimumInscription())->toBe(12);
});

it('laisse un jeune de 12 à 17 ans avoir son propre compte s\'il a son téléphone ; sinon son parent gère sa fiche', function () {
    $ajouter = fn (string $prenom, int $ans, ?string $telephone): Membre => $this->familles->ajouterProche($this->parent, [
        'prenom' => $prenom, 'nom' => 'Ba', 'sexe' => 'H', 'date_naissance' => now()->subYears($ans)->subDay()->toDateString(), 'lien_famille' => 'enfant', 'telephone' => $telephone,
    ], UploadedFile::fake()->image('p.jpg'));

    // Un parent inscrit toujours un enfant plus jeune, sans téléphone.
    $petit = $ajouter('Bara', 8, null);
    expect($petit->peutAvoirSonCompte())->toBeFalse()
        ->and(fn () => $ajouter('Saliou', 8, '77 222 33 44'))->toThrow(DomainException::class, 'moins de 12 ans')
        ->and(fn () => $this->familles->donnerSonCompte($petit, '77 222 33 44', $this->parent->user))->toThrow(DomainException::class, 'moins de 12 ans');

    // À 14 ans, avec son téléphone, son parent lui donne son compte.
    $jeune = $ajouter('Modou', 14, '77 555 66 77');
    expect($jeune->estEnfant())->toBeTrue()->and($jeune->peutAvoirSonCompte())->toBeTrue();
    $this->familles->donnerSonCompte($jeune, '77 555 66 77', $this->parent->user);
    expect($jeune->fresh()->user_id)->not->toBeNull()->and($jeune->fresh()->responsable_id)->toBeNull();

    // Dans le panneau : sans parent, il faut 12 ans et son propre téléphone ; un jeune qui a son compte le garde.
    $sansParent = ['cellule_id' => $this->cellule->id, 'date_naissance' => now()->subYears(15)->toDateString()];
    expect($this->familles->rattacher([...$sansParent, 'telephone' => '+221770000777'], null)['responsable_id'])->toBeNull()
        ->and(fn () => $this->familles->rattacher($sansParent, null))->toThrow(DomainException::class, 'sans son propre téléphone')
        ->and(fn () => $this->familles->rattacher([...$sansParent, 'date_naissance' => now()->subYears(9)->toDateString(), 'telephone' => '+221770000778'], null))->toThrow(DomainException::class, 'moins de 12 ans')
        ->and($this->familles->rattacher(['cellule_id' => $this->cellule->id, 'date_naissance' => $jeune->date_naissance->toDateString()], $jeune->fresh())['responsable_id'])->toBeNull();
});

it('applique l\'âge minimum à la reprise des listes et à l\'activation d\'un compte', function () {
    $secretaire = nommerPourLeTest(User::factory()->create(), 'secretaire_general');
    $ligne = fn (string $prenom, int $ans, string $telephone): string => implode(';', array_map(fn (string $valeur): string => '"'.$valeur.'"',
        ['', $prenom, 'Diop', 'H', $telephone, '', 'Kaolack', now()->subYears($ans)->subDay()->format('d/m/Y'), '', '', '', '', '', '', '', '']));
    $chemin = 'imports/ages.csv';
    Storage::disk('local')->put($chemin, "\xEF\xBB\xBF".implode("\r\n", [implode(';', ImportMembresService::MODELE), $ligne('Jeune', 15, '77 101 01 01'), $ligne('Petit', 10, '77 101 01 02')]));

    $rapport = app(ImportMembresService::class)->analyser($chemin, 'ages.csv', $secretaire)->rapport ?? [];
    expect(implode(' ', $rapport[0]['messages']))->not->toContain('moins de')
        ->and(implode(' ', $rapport[1]['messages']))->toContain('Un enfant de moins de 12 ans n\'a pas de téléphone');

    // Un membre trop jeune ne s'active pas un compte.
    $petit = Membre::factory()->valide()->create(['cellule_id' => $this->cellule->id, 'telephone' => '+221770101099', 'date_naissance' => now()->subYears(10)->toDateString()]);
    expect(fn () => app(ActivationCompteService::class)->demander('+221770101099', $petit->date_naissance->toDateString(), null, 'motdepasse1'))
        ->toThrow(DomainException::class, 'au moins 12 ans');
});
