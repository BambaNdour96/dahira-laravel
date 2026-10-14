<?php

use App\Models\ActionComite;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\ComiteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function membreTache(string $prenom): Membre
{
    $compte = User::factory()->create(['name' => $prenom]);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['prenom' => $prenom]);
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $super = User::factory()->create();
    $super->assignRole(['super_administrateur', 'administrateur']);

    $this->president = membreTache('Ousmane');
    $this->fatou = membreTache('Fatou');
    $this->awa = membreTache('Awa');
    app(ComiteService::class)->definirComiteDirecteur([$this->president->id], $super);

    $this->cdgmt = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026, 'president_id' => $this->president->id]);
    $this->cuisine = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->fatou->id]);
    app(ComiteService::class)->integrerResponsables($this->cdgmt);

    $this->compteFatou = User::query()->findOrFail($this->fatou->user_id);
    $this->compteAwa = User::query()->findOrFail($this->awa->user_id);
});

it('laisse la cheffe ajouter et retirer les membres de sa commission, par matricule', function () {
    $this->actingAs($this->compteFatou)->post(route('comites.membres.ajouter', $this->cuisine), ['membre' => $this->awa->telephone])
        ->assertSessionHas('comite_succes');

    expect($this->cuisine->membres()->whereKey($this->awa->id)->exists())->toBeTrue()
        ->and($this->cdgmt->membres()->whereKey($this->awa->id)->exists())->toBeTrue();

    $this->actingAs($this->compteFatou)->post(route('comites.membres.ajouter', $this->cuisine), ['membre' => 'NDK-INCONNU'])->assertSessionHas('comite_erreur');
    $this->actingAs($this->compteAwa)->post(route('comites.membres.ajouter', $this->cuisine), ['membre' => $this->president->telephone])->assertSessionHas('comite_erreur');

    $this->actingAs($this->compteFatou)->get(route('comites.index'))->assertSee('Membres de la commission (1)')->assertSee('Confier une tâche');
    $this->actingAs($this->compteFatou)->post(route('comites.membres.retirer', [$this->cuisine, $this->awa]))->assertSessionHas('comite_succes');
    expect($this->cuisine->membres()->count())->toBe(0);
});

it('fait confier une tâche par la cheffe, la fait avancer par le membre, et signale son retard à la cheffe', function () {
    Notification::fake();
    $this->cuisine->membres()->attach($this->awa->id);

    $this->actingAs($this->compteFatou)->post(route('comites.taches.store', $this->cuisine), [
        'description' => 'Acheter le riz', 'membre_id' => $this->awa->id, 'echeance' => now()->addDays(2)->format('Y-m-d'),
    ])->assertSessionHas('comite_succes');
    Notification::assertSentTo($this->compteAwa, NotificationMembre::class);

    $etranger = membreTache('Ibou');
    $this->actingAs($this->compteFatou)->post(route('comites.taches.store', $this->cuisine), [
        'description' => 'Hors commission', 'membre_id' => $etranger->id,
    ])->assertSessionHas('comite_erreur');

    $tache = ActionComite::query()->firstOrFail();
    $this->actingAs($this->compteAwa)->get(route('comites.index'))->assertSee('Acheter le riz');
    $this->actingAs($this->compteAwa)->post(route('comites.actions.statut', $tache), ['statut' => 'en_cours'])->assertSessionHas('comite_succes');
    expect(ComiteService::peutSuivre($this->compteFatou, $tache))->toBeTrue();

    $tache->update(['echeance' => now()->subDay()]);
    expect(app(ComiteService::class)->routineDuMatin())->toBe(['rappels' => 0, 'retards' => 1]);
    Notification::assertSentTo($this->compteFatou, NotificationMembre::class);
});
