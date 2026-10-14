<?php

use App\Enums\ModeCollecte;
use App\Enums\MoyenPaiement;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\ParticipationSympathisant;
use App\Models\Rappel;
use App\Models\RemiseFonds;
use App\Models\Sympathisant;
use App\Models\User;
use App\Services\SympathisantService;
use App\Support\Numerotation;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Défauts qui n'apparaissaient que sous PostgreSQL (la base de production).
 * Ces tests tournent aussi sous PostgreSQL : voir « Vérification obligatoire » dans CLAUDE.md.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');
    $this->cellule = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal de Touba 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
    ]);
    $this->sympathisant = Sympathisant::query()->create([
        'prenom' => 'Ali', 'nom' => 'Sarr', 'sexe' => 'H', 'cellule_id' => $this->cellule->id, 'consentement_le' => now(),
    ]);
    $this->annee = now()->format('Y');
});

function remiseDeTest(string $numero, User $representant, User $receveur, int $celluleId): RemiseFonds
{
    return RemiseFonds::query()->create([
        'numero' => $numero, 'cellule_id' => $celluleId, 'representant_id' => $representant->id,
        'recue_par' => $receveur->id, 'montant' => 1000, 'nombre_operations' => 1,
    ]);
}

it('numérote les reçus des sympathisants à la suite, sans verrou sur un comptage', function () {
    $service = app(SympathisantService::class);

    $premier = $service->participer($this->collecte, $this->sympathisant, 2000, MoyenPaiement::Especes, $this->tresorier);
    $second = $service->participer($this->collecte, $this->sympathisant, 3000, MoyenPaiement::Wave, $this->tresorier);

    expect($premier->numero_recu)->toBe("SYM-{$this->annee}-0001")
        ->and($second->numero_recu)->toBe("SYM-{$this->annee}-0002");
});

it('continue la série après 9999 reçus', function () {
    ParticipationSympathisant::query()->create([
        'collecte_id' => $this->collecte->id, 'sympathisant_id' => $this->sympathisant->id, 'montant' => 500,
        'moyen' => MoyenPaiement::Especes, 'numero_recu' => "SYM-{$this->annee}-9999", 'enregistre_par' => $this->tresorier->id,
    ]);

    $suivant = app(SympathisantService::class)->participer($this->collecte, $this->sympathisant, 1000, MoyenPaiement::Especes, $this->tresorier);

    expect($suivant->numero_recu)->toBe("SYM-{$this->annee}-10000")
        ->and(Numerotation::suivant(ParticipationSympathisant::class, 'numero_recu', "SYM-{$this->annee}-"))->toBe("SYM-{$this->annee}-10001");
});

it('numérote les remises de fonds à la suite', function () {
    $representant = User::factory()->create();
    $prefixe = "REM-{$this->annee}-";

    $premiere = Numerotation::creer(RemiseFonds::class, 'numero', $prefixe, fn (string $numero): RemiseFonds => remiseDeTest($numero, $representant, $this->tresorier, $this->cellule->id));
    $seconde = Numerotation::creer(RemiseFonds::class, 'numero', $prefixe, fn (string $numero): RemiseFonds => remiseDeTest($numero, $representant, $this->tresorier, $this->cellule->id));

    expect($premiere->numero)->toBe($prefixe.'0001')
        ->and($seconde->numero)->toBe($prefixe.'0002');
});

it('retente avec le numéro suivant si un autre enregistrement a pris le même au même instant', function () {
    $representant = User::factory()->create();
    $prefixe = "REM-{$this->annee}-";
    remiseDeTest($prefixe.'0001', $representant, $this->tresorier, $this->cellule->id);
    $essais = 0;

    // Au premier essai, le numéro est déjà pris (comme si quelqu'un venait de l'enregistrer) : refusé par la base, puis retenté.
    $remise = Numerotation::creer(RemiseFonds::class, 'numero', $prefixe, function (string $numero) use (&$essais, $prefixe, $representant): RemiseFonds {
        $essais++;

        return remiseDeTest($essais === 1 ? $prefixe.'0001' : $numero, $representant, $this->tresorier, $this->cellule->id);
    });

    expect($essais)->toBe(2)
        ->and($remise->numero)->toBe($prefixe.'0002')
        ->and(RemiseFonds::query()->count())->toBe(2);
});

it('abandonne après plusieurs conflits au lieu de boucler sans fin', function () {
    $representant = User::factory()->create();
    remiseDeTest('REM-X-0001', $representant, $this->tresorier, $this->cellule->id);

    expect(fn () => Numerotation::creer(RemiseFonds::class, 'numero', 'REM-X-', fn (): RemiseFonds => remiseDeTest('REM-X-0001', $representant, $this->tresorier, $this->cellule->id)))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('enregistre un rappel envoyé par notification (canal de 12 caractères)', function () {
    $membre = Membre::factory()->for($this->cellule)->valide()->create();

    $rappel = Rappel::query()->create([
        'collecte_id' => $this->collecte->id, 'membre_id' => $membre->id, 'canal' => 'notification', 'type' => 'ouverture', 'envoye_le' => now(),
    ]);

    expect($rappel->refresh()->canal)->toBe('notification');
});

it('refuse une colonne de numérotation inconnue', function () {
    expect(fn () => Numerotation::suivant(RemiseFonds::class, 'montant', 'X-'))->toThrow(InvalidArgumentException::class);
});
