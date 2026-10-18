<?php

use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CarteService;
use App\Services\ComiteService;
use App\Services\CommissionService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\ReglesValidationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tests dans un vrai navigateur (Chrome) : pages, JavaScript, affichage sur téléphone.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    // Dans un vrai navigateur, on charge le vrai style et le vrai JavaScript.
    $this->withVite();

    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class, ReglesValidationSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    Cellule::factory()->create(['nom' => 'Kaolack']);
    Commission::factory()->create(['nom' => 'Organisation']);

    $this->compte = User::factory()->create(['password' => 'motdepasse1']);
    $this->compte->assignRole('membre');
    $this->membre = Membre::factory()->for($this->compte)->valide()->create(['valide_le' => now()]);
    $this->carte = app(CarteService::class)->emettre($this->membre);

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
});

it('ouvre toutes les pages publiques sans erreur dans la console', function () {
    visit(['/', '/inscription', '/connexion', '/mot-de-passe-oublie', '/confidentialite', '/hors-ligne'])
        ->assertNoJavascriptErrors();
});

it('ouvre la page de vérification d\'une carte sans erreur', function () {
    visit('/v/'.$this->carte->jeton)
        ->assertSee('Membre actif')
        ->assertNoJavascriptErrors();
});

it('ouvre toutes les pages du membre sans erreur dans la console', function () {
    $this->actingAs($this->compte);

    visit([
        '/mon-espace', '/ma-carte', '/mes-cotisations', '/mes-recus', '/mes-notifications', '/ma-famille', '/ma-famille/ajouter',
        '/evenements', '/mon-compte/mot-de-passe', '/mon-compte/suppression',
    ])->assertNoJavascriptErrors();
});

it('affiche « Mes responsabilités » sur le téléphone, et le poste principal sur la carte', function () {
    nommerPourLeTest($this->compte, 'jewrine_cellule', $this->membre->cellule);
    nommerPourLeTest($this->compte, 'secretaire_general');
    $this->actingAs($this->compte->fresh());

    visit('/mon-espace')
        ->on()->mobile()
        ->assertSee('Mes responsabilités')
        ->assertSee('Secrétaire général')
        ->assertSee('Jewrine de cellule ('.$this->membre->cellule->nom.')')
        ->assertNoJavascriptErrors();

    visit('/ma-carte')->on()->mobile()->assertSee('Secrétaire général')->assertNoJavascriptErrors();
});

it('laisse le Jewrine d\'une commission accepter un souhait et ajouter un membre depuis son téléphone', function () {
    $commission = Commission::query()->where('nom', 'Organisation')->firstOrFail();
    nommerPourLeTest($this->compte, 'jewrine_commission', null, $commission);
    $candidate = Membre::factory()->valide()->create(['prenom' => 'Aminata']);
    $nouveau = Membre::factory()->valide()->create(['prenom' => 'Babacar']);
    app(CommissionService::class)->souhaiter($candidate, $commission);
    $this->actingAs($this->compte->fresh());

    visit('/mes-comites')
        ->on()->mobile()
        ->assertSee('Je suis Jewrine')
        ->assertSee('Souhaits à traiter (1)')
        ->press('Accepter')
        ->assertSee('Aminata '.$candidate->nom.' rejoint la commission')
        ->fill('membre', $nouveau->telephone)
        ->press('Ajouter')
        ->assertSee('Babacar '.$nouveau->nom.' rejoint la commission Organisation')
        ->assertNoJavascriptErrors();

    expect($commission->membres()->count())->toBe(2);
});

it('change le montant du bouton Wave selon le nombre de mois choisi', function () {
    foreach ([1 => 'UNMOIS', 2 => 'DEUXMOIS', 3 => 'TROISMOIS'] as $nombre => $code) {
        Parametre::query()->where('cle', 'wave_lien_'.$nombre.'_mois')->update(['valeur' => 'https://pay.wave.com/m/'.$code]);
    }

    $this->actingAs($this->compte);

    visit('/mes-cotisations')
        ->assertSee('Payer 1 000 FCFA avec Wave')
        ->click('2 mois')
        ->assertSee('Payer 2 000 FCFA avec Wave')
        ->click('3 mois')
        ->assertSee('Payer 3 000 FCFA avec Wave')
        ->assertNoJavascriptErrors();
});

it('fait avancer l\'inscription avec le bouton Continuer, sans erreur', function () {
    visit('/inscription')
        ->assertSee('Continuer')
        ->click('Continuer')
        ->assertNoJavascriptErrors();
});

it('n\'oblige jamais à faire défiler la page de côté sur un téléphone', function (string $adresse, bool $connecte) {
    if ($connecte) {
        $this->actingAs($this->compte);
    }

    $page = visit($adresse)->on()->mobile();

    $mesure = $page->script('JSON.stringify({ origine: location.origin, styles: [...document.querySelectorAll("link[rel=stylesheet]")].map((l) => l.href + (l.sheet ? " CHARGEE" : " NON CHARGEE")), ecran: document.documentElement.clientWidth, page: document.documentElement.scrollWidth, depassent: [...document.querySelectorAll("body *")].filter((e) => !e.closest("[aria-hidden=true]") && e.getBoundingClientRect().right > document.documentElement.clientWidth + 1).slice(0, 6).map((e) => e.tagName.toLowerCase() + " " + String(e.className).slice(0, 70) + " => " + Math.round(e.getBoundingClientRect().right)) })');

    expect($mesure)->toContain('"depassent":[]');
})->with([
    'accueil' => ['/', false],
    'inscription' => ['/inscription', false],
    'connexion' => ['/connexion', false],
    'mon compte' => ['/mon-espace', true],
    'ma carte' => ['/ma-carte', true],
    'mes cotisations' => ['/mes-cotisations', true],
    'ma famille' => ['/ma-famille', true],
    'ajouter un proche' => ['/ma-famille/ajouter', true],
]);

it('ouvre les écrans du panneau sans erreur dans la console', function () {
    $this->actingAs($this->super);

    visit([
        '/admin', '/admin/membres', '/admin/paiements', '/admin/relances', '/admin/evenements',
        '/admin/validation-bureau', '/admin/connectes', '/admin/demandes-suppression',
    ])->assertNoJavascriptErrors();
});

it('suit le thème sombre du téléphone, sans erreur', function () {
    $page = visit('/')->inDarkMode();

    expect($page->script('document.documentElement.classList.contains("dark")'))->toBeTrue()
        ->and($page->script('getComputedStyle(document.body).backgroundColor'))->toBe('rgb(12, 18, 16)');

    $page->assertNoJavascriptErrors();
});

it('laisse le membre choisir le thème avec le bouton', function () {
    visit('/connexion')
        ->assertSee('Thème : automatique')
        ->click('#bouton-theme')
        ->assertSee('Thème : clair')
        ->click('#bouton-theme')
        ->assertSee('Thème : sombre')
        ->assertNoJavascriptErrors();
});

it('déploie le tiroir des notifications, puis le replie en touchant la page', function () {
    $this->compte->notify(new NotificationMembre('Avis de test', 'Contenu du test.'));
    $this->actingAs($this->compte);

    $page = visit('/mon-espace');
    $ferme = 'document.getElementById("tiroir-notifications").classList.contains("translate-x-full")';

    expect($page->script($ferme))->toBeTrue();

    $page->click('#ouvrir-notifications')->assertSee('Avis de test');
    expect($page->script($ferme))->toBeFalse();

    $page->click('#voile-notifications');
    expect($page->script($ferme))->toBeTrue();

    $page->assertNoJavascriptErrors();
});

it('déploie le menu depuis la gauche, puis le replie en touchant la page', function () {
    $this->actingAs($this->compte);

    $page = visit('/mon-espace');
    $ferme = 'document.getElementById("tiroir-menu").classList.contains("-translate-x-full")';

    expect($page->script($ferme))->toBeTrue();

    $page->click('#ouvrir-menu')->assertSee('Paramètres');
    expect($page->script($ferme))->toBeFalse();

    $page->click('#voile-menu');
    expect($page->script($ferme))->toBeTrue();

    $page->assertNoJavascriptErrors();
});

it('garde « Se connecter » grisé tant que le mot de passe est vide, et sait l\'afficher', function () {
    $page = visit('/connexion');
    $bloque = 'document.querySelector(\'form[action$="/connexion"] button[type="submit"]\').disabled';

    expect($page->script($bloque))->toBeTrue();

    $page->fill('telephone', '77 123 45 67')->fill('password', 'secret12');
    expect($page->script($bloque))->toBeFalse();

    $page->click('#afficher-mdp-0');
    expect($page->script('document.querySelector(\'input[name="password"]\').type'))->toBe('text');

    $page->assertNoJavascriptErrors();
});

it('met la référence Wave en majuscules et contrôle son format', function () {
    $this->actingAs($this->compte);

    $page = visit('/mes-cotisations');
    $bouton = 'document.querySelector(\'input[name="reference"]\').form.querySelector(\'button[type="submit"]\').disabled';

    expect($page->script($bouton))->toBeTrue();

    $page->fill('reference', 't_cn535jqdn4vapi5r');
    expect($page->script('document.querySelector(\'input[name="reference"]\').value'))->toBe('T_CN535JQDN4VAPI5R')
        ->and($page->script($bouton))->toBeFalse();

    $page->assertNoJavascriptErrors();
});

it('grise « Continuer » et affiche les règles du mot de passe à l\'inscription', function () {
    $page = visit('/inscription');

    expect($page->script('document.querySelector("[data-suivant]").dataset.incomplet'))->toBe('oui')
        ->and($page->script('document.querySelector("[data-regles-mdp]") !== null'))->toBeTrue();

    $page->assertNoJavascriptErrors();
});

it('ouvre « Modifier la convocation » d\'une réunion convoquée, sans erreur', function () {
    $reunion = Reunion::query()->create([
        'instance_id' => Instance::comiteDirecteur()->id, 'titre' => 'Réunion du bureau', 'debut' => now()->addDays(2),
        'lieu' => 'Siège', 'secretaire_id' => $this->membre->id,
    ]);
    $reunion->points()->create(['titre' => 'Préparation du Magal', 'ordre' => 1]);
    app(ComiteService::class)->convoquer($reunion);
    $this->actingAs($this->super);

    visit('/admin/reunions/'.$reunion->id)
        ->click('Modifier la convocation')
        ->assertSee('Envoyer la convocation modifiée')
        ->assertValue('[id="mountedActionSchema0.lieu"]', 'Siège')
        ->assertNoJavascriptErrors();
});

it('ouvre « Ma famille », le formulaire d\'un proche, puis la fiche et la carte d\'un enfant, sur le téléphone', function () {
    $awa = Membre::factory()->valide()->create([
        'prenom' => 'Awa', 'telephone' => null, 'date_naissance' => now()->subYears(9), 'cellule_id' => $this->membre->cellule_id,
        'responsable_id' => $this->membre->id, 'lien_famille' => 'enfant', 'valide_le' => now(),
    ]);
    app(CarteService::class)->emettre($awa);
    $this->actingAs($this->compte);

    // Le formulaire se remplit au doigt ; son envoi (avec la photo) est vérifié par les tests Feature.
    visit('/ma-famille/ajouter')->on()->mobile()
        ->fill('prenom', 'Ibrahima')
        ->click('Goor Yalla')
        ->select('date_naissance_annee', (string) (now()->year - 5))
        ->select('lien_famille', 'enfant')
        ->assertSee('À laisser vide pour un enfant de moins de 18 ans.')
        ->assertNoJavascriptErrors();

    visit('/ma-famille')->on()->mobile()->assertSee('Awa')->assertSee('Son enfant')->assertNoJavascriptErrors();
    visit('/ma-famille/'.$awa->id)->on()->mobile()->assertSee('Sa carte')->assertSee('Ses cotisations')->assertNoJavascriptErrors();
    visit('/ma-famille/'.$awa->id.'/carte')->on()->mobile()
        ->assertSee('Carte de Awa')
        ->assertSee('Contact (famille)')
        ->assertNoJavascriptErrors();
});
