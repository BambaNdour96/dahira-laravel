<?php

use App\Enums\NiveauComprehension;
use App\Enums\NiveauCoran;
use App\Enums\NiveauKhassaides;
use App\Enums\NiveauLecture;
use App\Enums\Sexe;
use App\Filament\Exports\MembreExporter;
use App\Filament\Pages\RecensementArabeCoran;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\ProfilArabeCoran;
use App\Models\User;
use App\Services\ArabeCoranService;
use App\Services\CommissionService;
use App\Services\FamilleService;
use Database\Seeders\CommissionsSeeder;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Un membre validé, avec son compte. */
function membreArabe(string $prenom, Cellule $cellule, string $telephone): Membre
{
    $compte = User::factory()->create(['name' => $prenom.' Sy', 'telephone' => $telephone]);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['prenom' => $prenom, 'cellule_id' => $cellule->id, 'telephone' => $telephone, 'sexe' => Sexe::Homme]);
}

/** La rubrique d'un membre, prête à enregistrer. */
function rubrique(array $valeurs = []): array
{
    return [
        'lecture' => null, 'ecriture' => null, 'comprehension' => null, 'coran' => null, 'nombre_hizb' => null,
        'khassaides' => null, 'souhaite_apprendre' => null, ...$valeurs,
    ];
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class, CommissionsSeeder::class]);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->jewrine = User::factory()->create();
    $this->medina = celluleDirigeePar($this->jewrine, ['nom' => 'Médina']);
    $this->sam = Cellule::factory()->create(['nom' => 'Sam']);
    $this->service = app(ArabeCoranService::class);

    $this->moussa = membreArabe('Moussa', $this->medina, '+221770001001');
    $this->ibou = membreArabe('Ibou', $this->sam, '+221770001002');
    $this->culturel = membreArabe('Serigne', $this->sam, '+221770001003');
    app(CommissionService::class)->ajouter(Commission::query()->where('code', Commission::CULTURELLE)->firstOrFail(), $this->culturel, $this->super);
});

it('reprend l\'ancien niveau d\'arabe dans la nouvelle rubrique, puis retire l\'ancien champ', function () {
    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
    $id = DB::table('membres')->insertGetId([
        'prenom' => 'Ancien', 'nom' => 'Membre', 'sexe' => 'H', 'telephone' => '+221770009999', 'cellule_id' => $this->medina->id,
        'statut' => 'valide', 'pays_residence' => 'Sénégal', 'niveau_arabe' => 'intermediaire', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->artisan('migrate')->assertSuccessful();

    $profil = ProfilArabeCoran::query()->where('membre_id', $id)->firstOrFail();
    expect($profil->lecture)->toBe(NiveauLecture::Couramment)
        ->and($profil->ecriture)->toBe(NiveauLecture::Couramment)
        ->and($profil->comprehension)->toBe(NiveauComprehension::Essentiel)
        ->and($profil->coran)->toBeNull()
        ->and(Schema::hasColumn('membres', 'niveau_arabe'))->toBeFalse();
});

it('fait remplir la rubrique au membre dans son profil, et au parent pour un proche', function () {
    $compte = $this->moussa->user;
    $base = ['adresse' => 'Médina', 'ville' => 'Kaolack', 'pays_residence' => 'Sénégal'];

    $this->actingAs($compte)->get(route('espace.profil'))->assertOk()->assertSee('Arabe et Coran')->assertDontSee('Niveau en arabe');
    $this->actingAs($compte)->put(route('espace.profil.update'), [...$base, 'lecture' => 'couramment', 'coran' => 'hizb'])
        ->assertSessionHasErrors(['nombre_hizb' => 'Indiquez le nombre de hizb mémorisés.']);
    $this->actingAs($compte)->put(route('espace.profil.update'), [
        ...$base, 'lecture' => 'couramment', 'ecriture' => 'difficulte', 'comprehension' => 'quelques_mots',
        'coran' => 'hizb', 'nombre_hizb' => '12', 'khassaides' => 'kourel', 'souhaite_apprendre' => '1',
    ])->assertRedirect(route('espace'));

    $profil = $this->moussa->profilArabeCoran()->firstOrFail();
    expect($profil->libelleCoran())->toBe('12 hizb')
        ->and($profil->khassaides)->toBe(NiveauKhassaides::Kourel)
        ->and($profil->souhaite_apprendre)->toBeTrue()
        ->and($profil->renseigne_par)->toBe($compte->id);

    $enfant = app(FamilleService::class)->ajouterProche($this->moussa, [
        'prenom' => 'Fallou', 'nom' => 'Sy', 'sexe' => 'H', 'date_naissance' => now()->subYears(9)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('f.jpg'));
    $this->actingAs($compte)->get(route('famille.show', $enfant))->assertSee('Son arabe et son Coran');
    $this->actingAs($compte)->post(route('famille.arabe_coran', $enfant), ['lecture' => 'difficulte', 'coran' => 'sourates', 'souhaite_apprendre' => '1'])
        ->assertRedirect(route('famille.show', $enfant));
    expect($enfant->profilArabeCoran()->firstOrFail()->coran)->toBe(NiveauCoran::Sourates);

    $this->actingAs($this->ibou->user)->post(route('famille.arabe_coran', $enfant), ['lecture' => 'excellent'])->assertForbidden();
    expect(fn () => $this->service->enregistrer($this->moussa, rubrique(), $this->ibou->user))->toThrow(DomainException::class);
});

it('réserve le recensement à la commission Culturelle et au super administrateur', function () {
    $this->service->enregistrer($this->moussa, rubrique(['lecture' => 'excellent', 'coran' => 'hafiz', 'souhaite_apprendre' => false]), $this->moussa->user);
    $this->service->enregistrer($this->ibou, rubrique(['lecture' => 'ne_sait_pas', 'coran' => 'hizb', 'nombre_hizb' => 5, 'souhaite_apprendre' => true]), $this->ibou->user);

    $this->actingAs($this->culturel->user)->get(route('comites.index'))->assertSee('Recensement Arabe et Coran');
    $this->actingAs($this->culturel->user)->get(route('recensement.index'))->assertOk()->assertSee('Moussa')->assertSee('Hafiz')->assertSee('+221770001001');
    $this->actingAs($this->culturel->user)->get(route('recensement.index', ['critere' => 'souhaite_apprendre']))->assertSee('Ibou')->assertDontSee('Moussa');

    $chiffres = collect($this->service->chiffresParCellule())->keyBy('cellule');
    expect($chiffres['Médina']['hafiz'])->toBe(1)->and($chiffres['Médina']['hizb'])->toBe(60)
        ->and($chiffres['Sam']['hizb'])->toBe(5)->and($chiffres['Sam']['apprendre'])->toBe(1);

    $this->actingAs($this->ibou->user)->get(route('recensement.index'))->assertForbidden();
    $this->actingAs($this->jewrine->fresh())->get(route('recensement.index'))->assertForbidden();
    $this->actingAs(nommerPourLeTest(membreArabe('Abdou', $this->sam, '+221770001004')->user, 'jewrine_national'))->get(route('recensement.index'))->assertForbidden();

    $this->actingAs($this->super);
    expect(RecensementArabeCoran::canAccess())->toBeTrue();
    Livewire::test(RecensementArabeCoran::class)->assertOk()->assertSee('Moussa')->assertSee('Hafiz');
    $this->actingAs($this->jewrine->fresh());
    expect(RecensementArabeCoran::canAccess())->toBeFalse();
});

it('montre au Jewrine de cellule seulement qui, dans sa cellule, souhaite apprendre : nom et téléphone', function () {
    $this->service->enregistrer($this->moussa, rubrique(['lecture' => 'difficulte', 'khassaides' => 'kourel', 'souhaite_apprendre' => true]), $this->moussa->user);
    $this->service->enregistrer($this->ibou, rubrique(['lecture' => 'ne_sait_pas', 'souhaite_apprendre' => true]), $this->ibou->user);

    $this->actingAs($this->jewrine->fresh())->get(route('recensement.apprenants'))
        ->assertOk()->assertSee('Moussa')->assertSee('+221770001001')
        ->assertDontSee('Ibou')->assertDontSee('Lit avec difficulté')->assertDontSee('kourel');
    $this->actingAs($this->ibou->user)->get(route('recensement.apprenants'))->assertForbidden();
    $this->actingAs($this->culturel->user)->get(route('recensement.apprenants'))->assertOk()->assertSee('Ibou')->assertSee('Moussa');
});

it('sort la rubrique de la fiche du panneau et de l\'export', function () {
    $colonnes = collect(MembreExporter::getColumns())->map(fn ($colonne): string => $colonne->getName())->all();

    expect($colonnes)->not->toContain('niveau_arabe')
        ->and(collect(Membre::CHAMPS_PROFIL))->toContain('arabe_coran');

    $this->actingAs($this->jewrine->fresh())->get(route('filament.admin.resources.membres.edit', $this->moussa))->assertOk()->assertDontSee('Niveau en arabe');
});
