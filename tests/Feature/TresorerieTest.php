<?php

use App\Enums\ModeCollecte;
use App\Enums\MoyenPaiement;
use App\Enums\ProgrammeCollecte;
use App\Enums\Sexe;
use App\Enums\StatutCollecte;
use App\Enums\TypeCaisse;
use App\Filament\Resources\Caisses\CaisseResource;
use App\Filament\Resources\Caisses\Pages\ListCaisses;
use App\Filament\Resources\Caisses\Pages\ViewCaisse;
use App\Models\Caisse;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Sympathisant;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Services\RepresentantFinanceService;
use App\Services\SympathisantService;
use App\Services\TresorerieService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->tresorier = User::factory()->create(['name' => 'Fatou Ndiaye']);
    $this->tresorier->assignRole('jewrine_finance');
    $this->cellule = Cellule::factory()->create(['nom' => 'Kaolack']);

    $compte = User::factory()->create(['name' => 'Khady Ba']);
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->valide()->create(['cellule_id' => $this->cellule->id, 'sexe' => Sexe::Femme]);
    $this->representante = $compte;
    app(RepresentantFinanceService::class)->designer($this->cellule, 'femmes', $this->representante, $this->tresorier);

    $awa = User::factory()->create();
    $awa->assignRole('membre');
    $this->awa = Membre::factory()->for($awa)->valide()->create(['cellule_id' => $this->cellule->id, 'sexe' => Sexe::Femme, 'valide_le' => now()->startOfMonth()->subMonths(4)]);
    $this->service = app(TresorerieService::class);
});

function solde(TypeCaisse|User $caisse): int
{
    $tresorerie = app(TresorerieService::class);

    return ($caisse instanceof User ? $tresorerie->caisseDuRepresentant($caisse) : $tresorerie->caisse($caisse))->solde();
}

it('fait entrer chaque paiement validé dans sa caisse, et la remise dans la caisse générale', function () {
    $cotisations = app(CotisationService::class);
    expect(Caisse::query()->where('user_id', $this->representante->id)->exists())->toBeTrue();

    $cotisations->enregistrerEspeces($this->awa, 2, $this->representante);
    expect(solde($this->representante))->toBe(2000)->and(solde(TypeCaisse::Especes))->toBe(0);

    app(PaiementService::class)->confirmer($cotisations->declarer($this->awa->fresh(), 'T_AWA000000000W1', 1, null, $this->awa->user), $this->tresorier);
    expect(solde(TypeCaisse::Wave))->toBe(1000)
        ->and(Paiement::query()->where('reference', 'T_AWA000000000W1')->value('caisse_id'))->toBe($this->service->caisse(TypeCaisse::Wave)->id);

    $cotisations->enregistrerEspeces($this->awa->fresh(), 1, $this->tresorier);
    expect(solde(TypeCaisse::Especes))->toBe(1000);

    app(RepresentantFinanceService::class)->recevoirRemise($this->representante, $this->tresorier);
    expect(solde($this->representante))->toBe(0)->and(solde(TypeCaisse::Especes))->toBe(3000);

    // La participation d'un sympathisant entre aussi en caisse.
    $collecte = Collecte::query()->create(['titre' => 'Magal', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre, 'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte]);
    $sympathisant = Sympathisant::query()->create(['prenom' => 'Ali', 'nom' => 'Sarr', 'sexe' => 'H', 'cellule_id' => $this->cellule->id, 'consentement_le' => now()]);
    app(SympathisantService::class)->participer($collecte, $sympathisant, 5000, MoyenPaiement::Wave, $this->tresorier);
    expect(solde(TypeCaisse::Wave))->toBe(6000);
});

it('accepte une déclaration Orange Money, avec un contrôle souple de la référence', function () {
    $compte = $this->awa->user;

    $this->actingAs($compte)->post(route('cotisations.declarer'), ['moyen' => 'orange_money', 'nombre_mois' => 1, 'reference' => 'ci!20'])
        ->assertSessionHasErrors(['reference' => 'Référence Orange Money invalide : des lettres, des chiffres et des points, comme dans le SMS de confirmation.']);
    $this->actingAs($compte)->post(route('cotisations.declarer'), ['moyen' => 'orange_money', 'nombre_mois' => 2, 'reference' => 'mp261020.1530.c12345'])
        ->assertRedirect(route('cotisations'));

    $paiement = Paiement::query()->where('reference', 'MP261020.1530.C12345')->firstOrFail();
    expect($paiement->moyen)->toBe(MoyenPaiement::OrangeMoney);

    $this->actingAs($compte)->post(route('cotisations.declarer'), ['moyen' => 'orange_money', 'nombre_mois' => 1, 'reference' => 'MP261020.1530.C12345'])
        ->assertSessionHasErrors(['reference' => 'Cette référence a déjà été utilisée. Une référence ne peut servir qu\'une seule fois.']);

    app(PaiementService::class)->confirmer($paiement, $this->tresorier);
    expect(solde(TypeCaisse::OrangeMoney))->toBe(2000);
    $this->actingAs($compte)->get(route('cotisations'))->assertSee('Payé avec Orange Money')->assertSee('Orange Money');
});

it('laisse le super administrateur tenir les caisses, et la Finance voir les soldes', function () {
    $this->actingAs($this->super);
    Livewire::test(ListCaisses::class)
        ->callAction('create', ['nom' => 'Banque Islamique du Sénégal', 'type' => 'banque', 'ordre' => 5])
        ->assertHasNoActionErrors();
    $banque = Caisse::query()->where('type', TypeCaisse::Banque->value)->firstOrFail();

    app(CotisationService::class)->enregistrerEspeces($this->awa, 1, $this->tresorier);
    $generale = $this->service->caisse(TypeCaisse::Especes);
    Livewire::test(ListCaisses::class)
        ->assertSee('Banque Islamique du Sénégal')->assertSee('1 000 FCFA')
        ->assertActionHidden(TestAction::make('archiver')->table($generale))
        ->callAction(TestAction::make('archiver')->table($banque))
        ->assertHasNoActionErrors();
    expect($banque->fresh()->estArchive())->toBeTrue();

    $this->actingAs($this->tresorier);
    expect(CaisseResource::canViewAny())->toBeTrue()->and(CaisseResource::canCreate())->toBeFalse();
    Livewire::test(ViewCaisse::class, ['record' => $generale->getRouteKey()])->assertOk()->assertSee('Solde : 1 000 FCFA');

    $this->actingAs($this->awa->user);
    expect(CaisseResource::canViewAny())->toBeFalse();
});
