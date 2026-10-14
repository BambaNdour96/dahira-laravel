<?php

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Connexion;
use App\Models\Exercice;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\Poste;
use App\Models\Tarif;
use App\Models\User;
use App\Support\Capacites;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Matrice des droits : chaque règle d'accès est vérifiée pour chaque rôle.
 * Si une règle change par erreur, un test échoue.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function compteAvecRoles(array $roles): User
{
    $compte = User::factory()->create();

    if ($roles !== []) {
        $compte->assignRole($roles);
    }

    return $compte;
}

dataset('roles', [
    'super administrateur' => [['super_administrateur'], 'super'],
    'Jewrine national' => [['jewrine_national'], 'national'],
    'Secrétaire général' => [['secretaire_general'], 'secretaire'],
    'Jewrine finance' => [['jewrine_finance'], 'jewrine_finance'],
    'Jewrine de cellule' => [['jewrine_cellule'], 'jewrine'],
    'membre' => [['membre'], 'membre'],
]);

it('réserve les données de référence au Secrétaire général', function (array $roles, string $profil) {
    $compte = compteAvecRoles($roles);
    $attendu = in_array($profil, ['super', 'secretaire'], true);

    foreach ([Cellule::class, Commission::class, Exercice::class, Poste::class, Tarif::class] as $modele) {
        $objet = new $modele;

        expect($compte->can('viewAny', $modele))->toBe($attendu, "viewAny {$modele}")
            ->and($compte->can('view', $objet))->toBe($attendu, "view {$modele}")
            ->and($compte->can('create', $modele))->toBe($attendu, "create {$modele}")
            ->and($compte->can('update', $objet))->toBe($attendu, "update {$modele}")
            ->and($compte->can('delete', $objet))->toBeFalse("delete {$modele} : rien ne se supprime");
    }
})->with('roles');

it('réserve le journal et les connexions au super administrateur, en lecture seule', function (array $roles, string $profil) {
    $compte = compteAvecRoles($roles);
    $super = $profil === 'super';

    foreach ([JournalActivite::class, Connexion::class] as $modele) {
        $objet = new $modele;

        expect($compte->can('viewAny', $modele))->toBe($super)
            ->and($compte->can('view', $objet))->toBe($super)
            ->and($compte->can('create', $modele))->toBeFalse()
            ->and($compte->can('update', $objet))->toBeFalse()
            ->and($compte->can('delete', $objet))->toBeFalse();
    }
})->with('roles');

it('réserve les réglages au super administrateur, sans création ni suppression', function (array $roles, string $profil) {
    $compte = compteAvecRoles($roles);
    $super = $profil === 'super';
    $reglage = new Parametre;

    expect($compte->can('viewAny', Parametre::class))->toBe($super)
        ->and($compte->can('view', $reglage))->toBe($super)
        ->and($compte->can('update', $reglage))->toBe($super)
        ->and($compte->can('create', Parametre::class))->toBeFalse()
        ->and($compte->can('delete', $reglage))->toBeFalse();
})->with('roles');

it('réserve les comptes du bureau au super administrateur, sans suppression', function (array $roles, string $profil) {
    $compte = compteAvecRoles($roles);
    $super = $profil === 'super';
    $autre = User::factory()->create();

    expect($compte->can('viewAny', User::class))->toBe($super)
        ->and($compte->can('view', $autre))->toBe($super)
        ->and($compte->can('create', User::class))->toBe($super)
        ->and($compte->can('update', $autre))->toBe($super)
        ->and($compte->can('delete', $autre))->toBeFalse()
        ->and($compte->can('deleteAny', User::class))->toBeFalse();
})->with('roles');

it('confie les paiements au Jewrine finance, la direction les consulte, sans suppression', function (array $roles, string $profil) {
    $compte = compteAvecRoles($roles);
    $consulte = in_array($profil, ['super', 'national', 'secretaire', 'jewrine_finance'], true);
    $gere = in_array($profil, ['super', 'jewrine_finance'], true);
    $paiement = new Paiement;

    expect($compte->can('viewAny', Paiement::class))->toBe($consulte)
        ->and($compte->can('view', $paiement))->toBe($consulte)
        ->and($compte->can('create', Paiement::class))->toBe($gere)
        ->and($compte->can('update', $paiement))->toBe($gere)
        ->and($compte->can('delete', $paiement))->toBeFalse();
})->with('roles');

describe('les membres', function () {
    beforeEach(function () {
        $this->jewrine = compteAvecRoles(['jewrine_cellule']);
        $this->saCellule = celluleDirigeePar($this->jewrine);
        $this->autreCellule = Cellule::factory()->create();

        $this->membreDeSaCellule = Membre::factory()->for($this->saCellule)->create(['statut' => StatutMembre::EnAttente]);
        $this->membreAilleurs = Membre::factory()->for($this->autreCellule)->create(['statut' => StatutMembre::EnAttente]);

        $this->admin = compteAvecRoles(['secretaire_general']);
        $this->tresorier = compteAvecRoles(['jewrine_finance']);
        $this->membre = compteAvecRoles(['membre']);
    });

    it('laisse le Jewrine voir et modifier les membres de sa seule cellule', function () {
        expect($this->jewrine->can('view', $this->membreDeSaCellule))->toBeTrue()
            ->and($this->jewrine->can('update', $this->membreDeSaCellule))->toBeTrue()
            ->and($this->jewrine->can('view', $this->membreAilleurs))->toBeFalse()
            ->and($this->jewrine->can('update', $this->membreAilleurs))->toBeFalse();
    });

    it('laisse le Jewrine finance consulter sans modifier', function () {
        expect($this->tresorier->can('viewAny', Membre::class))->toBeTrue()
            ->and($this->tresorier->can('view', $this->membreAilleurs))->toBeTrue()
            ->and($this->tresorier->can('update', $this->membreAilleurs))->toBeFalse()
            ->and($this->tresorier->can('create', Membre::class))->toBeFalse();
    });

    it('refuse tout accès au registre à un simple membre', function () {
        expect($this->membre->can('viewAny', Membre::class))->toBeFalse()
            ->and($this->membre->can('view', $this->membreAilleurs))->toBeFalse()
            ->and($this->membre->can('update', $this->membreAilleurs))->toBeFalse();
    });

    it('ne permet à personne de supprimer une fiche, même rejetée : elle sera anonymisée', function () {
        $rejete = Membre::factory()->for($this->saCellule)->create(['statut' => StatutMembre::Rejete]);

        expect($this->admin->can('delete', $rejete))->toBeFalse()
            ->and($this->admin->can('delete', $this->membreDeSaCellule))->toBeFalse()
            ->and($this->jewrine->can('delete', $rejete))->toBeFalse()
            ->and($this->admin->can('deleteAny', Membre::class))->toBeFalse()
            ->and($this->jewrine->can('deleteAny', Membre::class))->toBeFalse();
    });

    it('réserve la révocation des cartes à l\'administrateur', function () {
        expect($this->admin->can('revoquerCarte', $this->membreDeSaCellule))->toBeTrue()
            ->and($this->jewrine->can('revoquerCarte', $this->membreDeSaCellule))->toBeFalse()
            ->and($this->tresorier->can('revoquerCarte', $this->membreDeSaCellule))->toBeFalse();
    });

    it('laisse chacun voir sa propre photo, mais pas celle des autres', function () {
        $proprietaire = compteAvecRoles(['membre']);
        $saFiche = Membre::factory()->for($proprietaire)->for($this->autreCellule)->create();

        expect($proprietaire->can('voirPhoto', $saFiche))->toBeTrue()
            ->and($this->membre->can('voirPhoto', $saFiche))->toBeFalse()
            ->and($this->jewrine->can('voirPhoto', $saFiche))->toBeFalse()
            ->and($this->admin->can('voirPhoto', $saFiche))->toBeTrue();
    });

    it('limite le Jewrine à accepter, faire corriger ou rejeter les demandes en attente de sa cellule', function () {
        foreach ([StatutMembre::Accepte, StatutMembre::ACorriger, StatutMembre::Rejete] as $cible) {
            expect($this->jewrine->can('changerStatut', [$this->membreDeSaCellule, $cible]))->toBeTrue();
        }

        expect($this->jewrine->can('changerStatut', [$this->membreDeSaCellule, StatutMembre::Valide]))->toBeFalse()
            ->and($this->jewrine->can('changerStatut', [$this->membreAilleurs, StatutMembre::Accepte]))->toBeFalse()
            ->and($this->admin->can('changerStatut', [$this->membreAilleurs, StatutMembre::Valide]))->toBeTrue()
            ->and($this->tresorier->can('changerStatut', [$this->membreAilleurs, StatutMembre::Accepte]))->toBeFalse();
    });
});

it('donne à chaque casquette exactement les capacités de la matrice, et rien d\'autre', function (string $casquette) {
    $compte = compteAvecRoles([$casquette]);
    $attendues = Capacites::de($casquette);

    foreach (Capacites::toutes() as $capacite) {
        expect($compte->can($capacite))->toBe(in_array($capacite, $attendues, true), "{$casquette} : {$capacite}");
    }
})->with(array_keys(Capacites::CASQUETTES));

it('additionne les capacités de plusieurs casquettes', function () {
    $compte = compteAvecRoles(['jewrine_finance', 'jewrine_cellule']);

    expect($compte->can('paiements.valider'))->toBeTrue()
        ->and($compte->can('dahiras.gerer.cellule'))->toBeTrue()
        ->and($compte->can('referentiel.gerer'))->toBeFalse();
});

it('garde le tableau du guide identique à la matrice du code', function () {
    expect(file_get_contents(base_path('docs/guide/nominations.md')))->toContain(Capacites::tableauMarkdown());
});
