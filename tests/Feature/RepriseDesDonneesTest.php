<?php

use App\Enums\LienFamille;
use App\Enums\StatutMembre;
use App\Filament\Resources\ActivationsCompte\Pages\ListActivationsCompte;
use App\Filament\Resources\ImportsMembres\ImportMembresResource;
use App\Filament\Resources\ImportsMembres\Pages\ViewImportMembres;
use App\Models\ActivationCompte;
use App\Models\Cellule;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\ActivationCompteService;
use App\Services\ImportMembresService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Dépose un fichier CSV (séparateur « ; ») et renvoie son chemin. */
function deposerCsv(array $lignes): string
{
    // Comme Excel : chaque valeur entre guillemets, pour qu'un « ; » dans une valeur ne coupe pas la colonne.
    $contenu = implode("\r\n", array_map(fn (array $ligne): string => implode(';', array_map(fn (string $valeur): string => '"'.$valeur.'"', $ligne)), [ImportMembresService::MODELE, ...$lignes]));
    $chemin = 'imports/liste-'.uniqid().'.csv';
    Storage::disk('local')->put($chemin, "\xEF\xBB\xBF".$contenu);

    return $chemin;
}

/** Une ligne du modèle, à partir des valeurs utiles. */
function ligneCsv(array $valeurs): array
{
    $colonnes = ['matricule', 'prenom', 'nom', 'sexe', 'telephone', 'email', 'cellule', 'naissance', 'lieu', 'adhesion', 'adresse', 'ville', 'savoir', 'responsable', 'lien', 'cotisations'];

    return array_map(fn (string $colonne): string => $valeurs[$colonne] ?? '', $colonnes);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo(Carbon::parse('2026-11-15 10:00'));

    $this->secretaire = nommerPourLeTest(User::factory()->create(), 'secretaire_general');
    $this->jewrine = User::factory()->create(['name' => 'Jewrine Médina']);
    $this->medina = celluleDirigeePar($this->jewrine, ['nom' => 'Médina Baye']);
    $this->sam = Cellule::factory()->create(['nom' => 'Sam']);
    $this->service = app(ImportMembresService::class);

    $compte = User::factory()->create(['telephone' => '+221770000099']);
    $compte->assignRole('membre');
    $this->existant = Membre::factory()->for($compte)->valide()->create([
        'prenom' => 'Ousmane', 'nom' => 'Sow', 'telephone' => '+221770000099', 'date_naissance' => '1975-01-01', 'cellule_id' => $this->sam->id,
    ]);
});

it('analyse le fichier sans rien enregistrer, puis importe les lignes prêtes, familles et reprise des cotisations comprises', function () {
    $chemin = deposerCsv([
        ligneCsv(['matricule' => 'NDK-2019-0007', 'prenom' => 'Cheikh', 'nom' => 'Ndour', 'sexe' => 'Goor Yalla', 'telephone' => '77 123 45 67', 'cellule' => 'medina baye', 'naissance' => '15/03/1980', 'adhesion' => '01/01/2019', 'savoir' => 'Cuisine; Pilotage de drone', 'cotisations' => '01/2027']),
        ligneCsv(['prenom' => 'Fallou', 'nom' => 'Ndour', 'sexe' => 'H', 'naissance' => '02/05/2016', 'responsable' => 'NDK-2019-0007', 'lien' => 'fils']),
        ligneCsv(['prenom' => 'Awa', 'nom' => 'Diop', 'sexe' => 'F', 'telephone' => '78 111 22 33', 'cellule' => 'Thiès Nord']),
        ligneCsv(['prenom' => 'Binta', 'nom' => 'Fall', 'sexe' => 'X', 'cellule' => 'Sam']),
        ligneCsv(['prenom' => 'Modou', 'nom' => 'Diallo', 'sexe' => 'H', 'telephone' => '77 000 00 99', 'cellule' => 'Sam']),
        ligneCsv(['prenom' => 'ousmane', 'nom' => 'sow', 'sexe' => 'H', 'cellule' => 'Sam', 'naissance' => '1975-01-01']),
        ligneCsv(['prenom' => 'Petit', 'nom' => 'Seul', 'sexe' => 'H', 'cellule' => 'Sam', 'naissance' => '01/01/2018']),
        ligneCsv(['prenom' => 'Petite', 'nom' => 'Tel', 'sexe' => 'F', 'telephone' => '76 555 44 33', 'cellule' => 'Sam', 'naissance' => '01/01/2017', 'responsable' => '77 000 00 99']),
    ]);

    $import = $this->service->analyser($chemin, 'liste-medina.csv', $this->secretaire);
    $etats = collect($import->rapport)->mapWithKeys(fn (array $ligne): array => [$ligne['numero'] => $ligne['etat']])->all();
    $messages = collect($import->rapport)->mapWithKeys(fn (array $ligne): array => [$ligne['numero'] => implode(' ', $ligne['messages'])])->all();

    expect(Membre::query()->count())->toBe(1)
        ->and($import->lignes_total)->toBe(8)
        ->and($import->lignes_pretes)->toBe(2)
        ->and($etats)->toBe([2 => 'prete', 3 => 'prete', 4 => 'erreur', 5 => 'erreur', 6 => 'doublon', 7 => 'doublon', 8 => 'erreur', 9 => 'erreur'])
        ->and($messages[2])->toContain('Savoir-faire inconnu, ignoré : « Pilotage de drone »')
        ->and($messages[4])->toContain('Cellule inconnue : « Thiès Nord »')
        ->and($messages[5])->toContain('Sexe à indiquer')
        ->and($messages[6])->toContain('Ousmane')
        ->and($messages[7])->toContain('mêmes prénom, nom et date de naissance')
        ->and($messages[8])->toContain('rattaché à un parent ou tuteur')
        ->and($messages[9])->toContain('n\'a pas de téléphone');

    $this->actingAs($this->secretaire);
    Livewire::test(ViewImportMembres::class, ['record' => $import->getRouteKey()])
        ->assertSee('Cellule inconnue')
        ->callAction('importer')
        ->assertHasNoActionErrors()
        ->assertActionHidden('importer');

    $cheikh = Membre::query()->where('telephone', '+221771234567')->firstOrFail();
    $fallou = Membre::query()->where('prenom', 'Fallou')->firstOrFail();
    expect($cheikh->statut)->toBe(StatutMembre::Valide)
        ->and($cheikh->matricule)->toBe('NDK-2019-0007')
        ->and($cheikh->origine)->toBe(Membre::ORIGINE_REPRISE)
        ->and($cheikh->cellule_id)->toBe($this->medina->id)
        ->and($cheikh->user_id)->toBeNull()
        ->and($cheikh->carteActive)->not->toBeNull()
        ->and($cheikh->competences()->pluck('nom')->all())->toBe(['Cuisine'])
        ->and(Cotisation::query()->where('membre_id', $cheikh->id)->orderBy('mois')->get()->map(fn (Cotisation $c): string => $c->mois->format('Y-m').($c->reprise ? ' reprise' : '').' '.$c->montant)->all())
        ->toBe(['2026-12 reprise 0', '2027-01 reprise 0'])
        ->and($fallou->responsable_id)->toBe($cheikh->id)
        ->and($fallou->lien_famille)->toBe(LienFamille::Enfant)
        ->and($fallou->cellule_id)->toBe($this->medina->id)
        ->and($fallou->matricule)->toStartWith('NDK-2026-')
        ->and($import->fresh()->lignes_importees)->toBe(2)
        ->and(fn () => $this->service->importer($import->fresh(), $this->secretaire))->toThrow(DomainException::class, 'déjà été importé');

    $this->get(route('reprise.rapport', $import))->assertOk()->assertSee('Importée')->assertSee('Cellule inconnue');
    $this->get(route('reprise.modele'))->assertOk()->assertSee('Cotisations à jour jusqu', false);
});

it('limite le Jewrine de cellule à sa cellule, et réserve la reprise aux responsables', function () {
    $chemin = deposerCsv([
        ligneCsv(['prenom' => 'Aïda', 'nom' => 'Ba', 'sexe' => 'F', 'cellule' => 'Médina Baye']),
        ligneCsv(['prenom' => 'Ibou', 'nom' => 'Ka', 'sexe' => 'H', 'cellule' => 'Sam']),
    ]);

    $import = $this->service->analyser($chemin, 'liste.csv', $this->jewrine->fresh());
    expect(collect($import->rapport)->pluck('etat')->all())->toBe(['prete', 'erreur'])
        ->and(implode(' ', $import->rapport[1]['messages']))->toContain('pas dans votre cellule');

    $membre = User::factory()->create();
    $membre->assignRole('membre');
    $this->actingAs($membre);
    expect(ImportMembresResource::canViewAny())->toBeFalse()
        ->and(fn () => $this->service->analyser($chemin, 'liste.csv', $membre))->toThrow(DomainException::class);
    $this->get(route('reprise.modele'))->assertForbidden();
});

it('fait activer son compte au membre importé : aussitôt avec le matricule, sinon après validation du Jewrine', function () {
    Notification::fake();
    $this->service->importer($this->service->analyser(deposerCsv([
        ligneCsv(['matricule' => 'NDK-2019-0007', 'prenom' => 'Cheikh', 'nom' => 'Ndour', 'sexe' => 'H', 'telephone' => '77 123 45 67', 'cellule' => 'Médina Baye', 'naissance' => '15/03/1980']),
        ligneCsv(['prenom' => 'Rokhaya', 'nom' => 'Sy', 'sexe' => 'F', 'telephone' => '78 765 43 21', 'cellule' => 'Médina Baye', 'naissance' => '20/08/1990']),
    ]), 'liste.csv', $this->secretaire), $this->secretaire);

    $naissance = ['date_naissance_jour' => '15', 'date_naissance_mois' => '3', 'date_naissance_annee' => '1980'];
    $this->get(route('login'))->assertSee('Activer mon compte');
    $this->get(route('activation'))->assertOk();

    // Mauvaise date : le même message que pour une fiche inconnue.
    $this->post(route('activation.store'), ['telephone' => '77 123 45 67', 'date_naissance_jour' => '16', 'date_naissance_mois' => '3', 'date_naissance_annee' => '1980', 'matricule' => 'NDK-2019-0007', 'password' => 'Baraka2026', 'password_confirmation' => 'Baraka2026'])
        ->assertSessionHasErrors(['telephone' => ActivationCompteService::INTROUVABLE]);

    $this->post(route('activation.store'), ['telephone' => '77 123 45 67', ...$naissance, 'matricule' => 'ndk-2019-0007', 'password' => 'Baraka2026', 'password_confirmation' => 'Baraka2026'])
        ->assertRedirect(route('login'));
    expect(Membre::query()->where('matricule', 'NDK-2019-0007')->value('user_id'))->not->toBeNull()
        ->and(Auth::attempt(['telephone' => '+221771234567', 'password' => 'Baraka2026']))->toBeTrue();
    Auth::logout();

    // Sans matricule : le Jewrine de cellule valide.
    $this->post(route('activation.store'), ['telephone' => '78 765 43 21', 'date_naissance_jour' => '20', 'date_naissance_mois' => '8', 'date_naissance_annee' => '1990', 'password' => 'Rokhaya2026', 'password_confirmation' => 'Rokhaya2026'])
        ->assertRedirect(route('login'));
    $this->post(route('activation.store'), ['telephone' => '78 765 43 21', 'date_naissance_jour' => '20', 'date_naissance_mois' => '8', 'date_naissance_annee' => '1990', 'password' => 'Rokhaya2026', 'password_confirmation' => 'Rokhaya2026'])
        ->assertSessionHasErrors(['telephone' => 'Votre demande attend déjà la validation de votre Jewrine de cellule.']);
    Notification::assertSentTo($this->jewrine, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->jewrine)['titre'], 'Activation de compte'));

    $activation = ActivationCompte::query()->firstOrFail();
    expect(Membre::query()->where('prenom', 'Rokhaya')->value('user_id'))->toBeNull();

    $this->actingAs($this->jewrine->fresh());
    Livewire::test(ListActivationsCompte::class)->assertSee('Rokhaya')->callAction(TestAction::make('valider')->table($activation))->assertHasNoActionErrors();
    Auth::logout();

    expect($activation->fresh()->statut)->toBe(ActivationCompte::VALIDEE)
        ->and(Auth::attempt(['telephone' => '+221787654321', 'password' => 'Rokhaya2026']))->toBeTrue();
});
