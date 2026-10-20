<?php

use App\Filament\Resources\Dispenses\DispenseResource;
use App\Filament\Resources\Dispenses\Pages\ListDispenses;
use App\Filament\Resources\Membres\Pages\ListMembres;
use App\Models\Cotisation;
use App\Models\DemandeDispense;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\DispenseService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->jewrine = User::factory()->create(['name' => 'Jewrine Médina']);
    $this->medina = celluleDirigeePar($this->jewrine);
    $this->national = nommerPourLeTest(User::factory()->create(['name' => 'Jewrine national']), 'jewrine_national');
    $this->membre = Membre::factory()->for(User::factory())->valide()->create(['cellule_id' => $this->medina->id, 'valide_le' => now()->startOfMonth()->subMonths(4)]);
    $this->ailleurs = Membre::factory()->valide()->create(['valide_le' => now()->subMonths(4)]);
    $this->service = app(DispenseService::class);
});

it('fait demander la dispense par le Jewrine de cellule et décider par le Jewrine national', function () {
    Notification::fake();

    $this->actingAs($this->jewrine->fresh());
    expect($this->service->peutDemander($this->jewrine->fresh(), $this->ailleurs))->toBeFalse();
    Livewire::test(ListMembres::class)
        ->callAction(TestAction::make('exonerer')->table($this->membre), data: ['nombre_mois' => 2, 'motif' => 'Maladie longue'])
        ->assertHasNoActionErrors();

    $demande = DemandeDispense::query()->firstOrFail();
    expect(Cotisation::query()->where('membre_id', $this->membre->id)->count())->toBe(0)
        ->and(fn () => $this->service->demander($this->membre, 1, 'Encore', $this->jewrine->fresh()))->toThrow(DomainException::class, 'attend déjà')
        ->and(fn () => $this->service->valider($demande, $this->jewrine->fresh()))->toThrow(DomainException::class, 'Le Jewrine national décide');
    Notification::assertSentTo($this->national, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->national)['message'], 'Maladie longue'));

    $this->actingAs($this->national);
    expect(DispenseResource::getNavigationBadge())->toBe('1');
    Livewire::test(ListDispenses::class)->callAction(TestAction::make('valider')->table($demande))->assertHasNoActionErrors();

    expect($demande->fresh()->statut)->toBe(DemandeDispense::VALIDEE)
        ->and(Cotisation::query()->where('membre_id', $this->membre->id)->where('exoneree', true)->count())->toBe(2);
    Notification::assertSentTo($this->jewrine, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->jewrine)['titre'] === 'Dispense validée');
});

it('laisse le Jewrine national refuser avec un motif, jamais décider sur sa propre demande', function () {
    $demande = $this->service->demander($this->membre, 3, 'Étudiant', $this->jewrine->fresh());

    expect(fn () => $this->service->refuser($demande, ' ', $this->national))->toThrow(DomainException::class, 'motif');
    $this->service->refuser($demande, 'Cotisations déjà réduites cette année', $this->national);
    expect($demande->fresh()->statut)->toBe(DemandeDispense::REFUSEE)
        ->and($demande->fresh()->motif_refus)->toBe('Cotisations déjà réduites cette année');

    // Le super administrateur demande en secours ; il ne décide pas de sa propre demande.
    $super = User::factory()->create();
    $super->assignRole('super_administrateur');
    $sienne = $this->service->demander($this->ailleurs, 1, 'Secours', $super);
    expect($this->service->peutDecider($super, $sienne))->toBeFalse()
        ->and($this->service->peutDecider($this->national, $sienne))->toBeTrue();

    $this->actingAs($this->membre->user);
    expect(DispenseResource::canViewAny())->toBeFalse();
});
