<?php

namespace Database\Seeders;

use App\Enums\LienFamille;
use App\Enums\ModeCollecte;
use App\Enums\Sexe;
use App\Enums\StatutCollecte;
use App\Enums\StatutMembre;
use App\Models\ActionComite;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\Collecte;
use App\Models\Commission;
use App\Models\CommissionComite;
use App\Models\Decision;
use App\Models\Exercice;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\RepresentantFinance;
use App\Models\Reunion;
use App\Models\TypeProgramme;
use App\Models\User;
use App\Services\ArabeCoranService;
use App\Services\CollecteService;
use App\Services\ComiteService;
use App\Services\CommissionService;
use App\Services\CotisationService;
use App\Services\NominationService;
use App\Services\PaiementService;
use App\Services\ProgrammeService;
use App\Services\RenouvellementService;
use App\Services\RepresentantFinanceService;
use App\Services\StatutMembreService;
use App\Services\SuiviDepensesService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Les données de recette : un jeu réaliste pour vérifier tous les écrans (un compte par casquette, une famille, des
 * cotisations à jour et en retard, des paiements à valider, un programme, un CDGMT avec sa réunion, une campagne de
 * renouvellement, des réponses Arabe et Coran). Jamais en production. Les comptes sont listés dans
 * docs/guide/comptes-de-recette.md.
 */
class RecetteSeeder extends Seeder
{
    /** Le mot de passe de tous les comptes de recette. */
    /** Astou Diop, Secrétaire générale et membre du Comité directeur : présidente du CDGMT. */
    private const TELEPHONE_PRESIDENTE_CDGMT = '+221770100003';

    public const MOT_DE_PASSE = 'Recette2026';

    /** Le téléphone du super administrateur de recette : sa présence signale un jeu déjà chargé. */
    public const TELEPHONE_SUPER = '+221770100001';

    /** @var array<string, Cellule> */
    private array $cellules = [];

    private User $super;

    private User $tresorier;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Les données de recette ne se chargent jamais en production.');
        }

        if (User::query()->where('telephone', self::TELEPHONE_SUPER)->exists()) {
            throw new RuntimeException('Les données de recette sont déjà chargées. Pour repartir de zéro : php artisan recette:charger --fraiche');
        }

        $this->call([RolesSeeder::class, PostesSeeder::class, CellulesSeeder::class, ParametresSeeder::class, TarifsSeeder::class, CommissionsSeeder::class, ReglesValidationSeeder::class]);

        $annee = now()->year;
        Exercice::query()->firstOrCreate(['libelle' => (string) $annee], ['date_debut' => "{$annee}-01-01", 'date_fin' => "{$annee}-12-31"]);
        $suivant = Exercice::query()->firstOrCreate(['libelle' => (string) ($annee + 1)], ['date_debut' => ($annee + 1).'-01-01', 'date_fin' => ($annee + 1).'-12-31']);

        foreach (['Kaolack', 'Dakar', 'Médina Baye'] as $nom) {
            $this->cellules[$nom] = Cellule::query()->firstOrCreate(['nom' => $nom], ['ville' => $nom === 'Médina Baye' ? 'Kaolack' : $nom, 'pays' => 'Sénégal']);
        }

        $this->bureau();
        $simples = $this->membres();
        $this->commissions();
        $this->famille();
        $this->paiements($simples);
        $magal = $this->programme($simples);
        $this->prokhane();
        $this->cdgmt($magal);
        $this->arabeCoran();

        // La campagne de renouvellement en dernier : tous les membres validés sont concernés.
        app(RenouvellementService::class)->ouvrir($suivant, now()->addMonths(2)->endOfMonth(), $this->super);
    }

    /** Un membre validé depuis $moisDepuis mois, avec son compte (mot de passe de recette). */
    private function membre(string $telephone, string $prenom, string $nom, Sexe $sexe, string $cellule, int $moisDepuis = 12, ?string $naissance = null): Membre
    {
        $compte = User::query()->create([
            'name' => $prenom.' '.mb_strtoupper($nom), 'telephone' => $telephone, 'password' => self::MOT_DE_PASSE, 'actif' => true,
        ]);
        $compte->assignRole('membre');

        $membre = Membre::factory()->for($compte)->create([
            'prenom' => $prenom, 'nom' => $nom, 'sexe' => $sexe, 'telephone' => $telephone,
            'cellule_id' => $this->cellules[$cellule]->id, 'date_naissance' => $naissance ?? fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
        ]);

        app(StatutMembreService::class)->changer($membre, StatutMembre::Valide, null);
        $depuis = now()->startOfMonth()->subMonths($moisDepuis);
        $membre->forceFill(['valide_le' => $depuis, 'date_adhesion' => $depuis])->save();

        return $membre->fresh() ?? $membre;
    }

    private function nommer(Membre $membre, string $poste, ?string $cellule = null, ?Commission $commission = null): void
    {
        app(NominationService::class)->nommer(
            $membre->user ?? throw new RuntimeException('Compte manquant.'),
            Poste::query()->where('code', $poste)->firstOrFail(),
            $cellule !== null ? $this->cellules[$cellule] : null,
            $this->super,
            now()->subMonths(6),
            $commission,
        );
    }

    private function bureau(): void
    {
        $super = $this->membre(self::TELEPHONE_SUPER, 'Super', 'Administrateur', Sexe::Homme, 'Kaolack');
        $this->super = $super->user ?? throw new RuntimeException('Compte manquant.');
        $this->super->assignRole('super_administrateur');

        $national = $this->membre('+221770100002', 'Serigne', 'Ndiaye', Sexe::Homme, 'Kaolack');
        $this->nommer($national, 'jewrine_national');
        $secretaire = $this->membre('+221770100003', 'Astou', 'Diop', Sexe::Femme, 'Dakar');
        $this->nommer($secretaire, 'secretaire_general');
        $finance = $this->membre('+221770100004', 'Fatou', 'Ndiaye', Sexe::Femme, 'Kaolack');
        $this->nommer($finance, 'jewrine_finance');
        $this->tresorier = $finance->user ?? throw new RuntimeException('Compte manquant.');

        foreach (['Kaolack' => ['+221770100011', 'Modou', 'Fall'], 'Dakar' => ['+221770100012', 'Ibrahima', 'Sarr'], 'Médina Baye' => ['+221770100013', 'Cheikh', 'Niasse']] as $cellule => [$telephone, $prenom, $nom]) {
            $this->nommer($this->membre($telephone, $prenom, $nom, Sexe::Homme, $cellule), 'jewrine_cellule', $cellule);
        }

        // Les représentants finance : un Goor Yalla et une Sokhna par cellule.
        $representants = [
            'Kaolack' => [['+221770100021', 'Abdou', 'Sow', Sexe::Homme], ['+221770100022', 'Khady', 'Ba', Sexe::Femme]],
            'Dakar' => [['+221770100023', 'Pape', 'Gueye', Sexe::Homme], ['+221770100024', 'Mariama', 'Sy', Sexe::Femme]],
            'Médina Baye' => [['+221770100025', 'Ousmane', 'Cissé', Sexe::Homme], ['+221770100026', 'Ndèye', 'Kane', Sexe::Femme]],
        ];

        foreach ($representants as $cellule => $personnes) {
            foreach ($personnes as [$telephone, $prenom, $nom, $sexe]) {
                $representant = $this->membre($telephone, $prenom, $nom, $sexe, $cellule);
                app(RepresentantFinanceService::class)->designer($this->cellules[$cellule], $sexe === Sexe::Homme ? 'hommes' : 'femmes', $representant->user ?? throw new RuntimeException('Compte manquant.'), $this->tresorier);
            }
        }

        // Une pointeuse à Kaolack, désignée par son Jewrine.
        $pointeuse = $this->membre('+221770100027', 'Aminata', 'Diallo', Sexe::Femme, 'Kaolack');
        CellulePointeur::query()->create(['cellule_id' => $this->cellules['Kaolack']->id, 'user_id' => $pointeuse->user_id, 'perimetre' => 'femmes']);

        // Le Comité directeur : le Jewrine national, le Secrétaire général, la Jewrine finance et un membre sans poste.
        $sage = $this->membre('+221770100031', 'El Hadji', 'Mbaye', Sexe::Homme, 'Dakar');
        app(ComiteService::class)->definirComiteDirecteur([$national->id, $secretaire->id, $finance->id, $sage->id], $this->super);
    }

    /**
     * Les simples membres : à jour, en retard, récents, et des inscriptions en cours.
     *
     * @return list<Membre>
     */
    private function membres(): array
    {
        $liste = [
            ['+221770100101', 'Awa', 'Ndour', Sexe::Femme, 'Kaolack', 10], ['+221770100102', 'Moussa', 'Thiam', Sexe::Homme, 'Kaolack', 8],
            ['+221770100103', 'Binta', 'Faye', Sexe::Femme, 'Kaolack', 6], ['+221770100104', 'Lamine', 'Seck', Sexe::Homme, 'Dakar', 12],
            ['+221770100105', 'Coumba', 'Diouf', Sexe::Femme, 'Dakar', 9], ['+221770100106', 'Babacar', 'Mbengue', Sexe::Homme, 'Dakar', 4],
            ['+221770100107', 'Sokhna', 'Ka', Sexe::Femme, 'Médina Baye', 11], ['+221770100108', 'Mamadou', 'Ly', Sexe::Homme, 'Médina Baye', 7],
            ['+221770100109', 'Rokhaya', 'Gaye', Sexe::Femme, 'Médina Baye', 1], ['+221770100110', 'Saliou', 'Touré', Sexe::Homme, 'Kaolack', 2],
        ];

        $membres = [];

        foreach ($liste as [$telephone, $prenom, $nom, $sexe, $cellule, $mois]) {
            $membres[] = $this->membre($telephone, $prenom, $nom, $sexe, $cellule, $mois);
        }

        // Des inscriptions en cours : une en attente du Jewrine, une acceptée qui doit payer ses frais de carte.
        foreach ([['+221770100120', 'Daouda', 'Sène', Sexe::Homme, StatutMembre::EnAttente], ['+221770100121', 'Mame', 'Diagne', Sexe::Femme, StatutMembre::Accepte]] as [$telephone, $prenom, $nom, $sexe, $statut]) {
            $compte = User::query()->create(['name' => $prenom.' '.mb_strtoupper($nom), 'telephone' => $telephone, 'password' => self::MOT_DE_PASSE, 'actif' => true]);
            $compte->assignRole('membre');
            $membre = Membre::factory()->for($compte)->create(['prenom' => $prenom, 'nom' => $nom, 'sexe' => $sexe, 'telephone' => $telephone, 'cellule_id' => $this->cellules['Kaolack']->id]);

            if ($statut === StatutMembre::Accepte) {
                app(StatutMembreService::class)->changer($membre, StatutMembre::Accepte, null);
                app(PaiementService::class)->declarer($membre->fresh() ?? $membre, 'T_RECETTE0FRAIS01', '+221770100121', $compte);
            }
        }

        return $membres;
    }

    private function commissions(): void
    {
        $culturelle = Commission::query()->where('code', Commission::CULTURELLE)->firstOrFail();
        $suivi = Commission::query()->where('code', Commission::SUIVI_EVALUATION)->firstOrFail();
        $communication = Commission::query()->where('nom', 'Communication')->firstOrFail();

        $this->nommer($this->membre('+221770100041', 'Mouhamadou', 'Kebe', Sexe::Homme, 'Kaolack'), 'jewrine_commission', null, $culturelle);
        $this->nommer($this->membre('+221770100042', 'Adama', 'Wade', Sexe::Homme, 'Dakar'), 'jewrine_commission', null, $suivi);

        foreach ([[$culturelle, '+221770100043', 'Oumar', 'Samb', Sexe::Homme, 'Médina Baye'], [$culturelle, '+221770100044', 'Seynabou', 'Mané', Sexe::Femme, 'Kaolack'], [$suivi, '+221770100045', 'Aliou', 'Badji', Sexe::Homme, 'Dakar'], [$communication, '+221770100046', 'Ndeye Fatou', 'Gning', Sexe::Femme, 'Dakar']] as [$commission, $telephone, $prenom, $nom, $sexe, $cellule]) {
            app(CommissionService::class)->ajouter($commission, $this->membre($telephone, $prenom, $nom, $sexe, $cellule), $this->super);
        }
    }

    /** Une famille : le père (qui gère la fiche de son fils de 9 ans), et son épouse, qui a son propre compte. */
    private function famille(): void
    {
        $pere = $this->membre('+221770100051', 'Ibrahima', 'Ndour', Sexe::Homme, 'Kaolack');
        $this->membre('+221770100052', 'Aïssatou', 'Ndour', Sexe::Femme, 'Kaolack');

        $fils = Membre::factory()->create([
            'prenom' => 'Fallou', 'nom' => 'Ndour', 'sexe' => Sexe::Homme, 'telephone' => null, 'cellule_id' => $pere->cellule_id,
            'date_naissance' => now()->subYears(9)->format('Y-m-d'), 'responsable_id' => $pere->id, 'lien_famille' => LienFamille::Enfant, 'inscrit_par' => $pere->user_id,
        ]);
        app(StatutMembreService::class)->changer($fils, StatutMembre::Valide, null);
        $fils->forceFill(['valide_le' => now()->startOfMonth()->subMonths(5)])->save();
    }

    /**
     * Des cotisations à jour (espèces chez les représentants), d'autres en retard, et des paiements Wave à valider.
     *
     * @param  list<Membre>  $membres
     */
    private function paiements(array $membres): void
    {
        $cotisations = app(CotisationService::class);

        foreach (array_slice($membres, 0, 4) as $membre) {
            $representant = $this->representantDe($membre);

            for ($reste = count($cotisations->moisEnRetard($membre)); $reste > 0; $reste -= 3) {
                $cotisations->enregistrerEspeces($membre->fresh() ?? $membre, min(3, $reste), $representant);
            }
        }

        // Des déclarations Wave qui attendent la validation de la Finance ou du représentant.
        $cotisations->declarer($membres[4], 'T_RECETTE0COTIS1', 2, $membres[4]->telephone, $membres[4]->user);
        $cotisations->declarer($membres[6], 'T_RECETTE0COTIS2', 3, $membres[6]->telephone, $membres[6]->user);
    }

    private function representantDe(Membre $membre): User
    {
        return User::query()
            ->whereIn('id', RepresentantFinance::query()->where('cellule_id', $membre->cellule_id)->where('perimetre', $membre->sexe === Sexe::Homme ? 'hommes' : 'femmes')->select('user_id'))
            ->firstOrFail();
    }

    /**
     * Le Magal en cours (Jàyante, comité dédié) : des Jàyante fixés, des versements en espèces et un versement Wave à valider.
     *
     * @param  list<Membre>  $membres
     */
    private function programme(array $membres): Collecte
    {
        $collecte = app(ProgrammeService::class)->enregistrer([
            'titre' => 'Grand Magal de Touba '.now()->year, 'type_programme_id' => TypeProgramme::parCode(TypeProgramme::MAGAL)->id, 'mode' => ModeCollecte::Jayante->value,
            'debut' => now()->addMonths(3)->setTime(8, 0), 'fin' => now()->addMonths(3)->addDays(2)->setTime(20, 0), 'lieu' => 'Touba', 'avec_jour_j' => true,
            'itineraire_transport' => true, 'objectif' => 5000000, 'budget' => 3500000, 'organisation' => Collecte::COMITE,
            // La présidente du CDGMT : Astou Diop (Comité directeur), distincte du Jewrine national qui valide ses propositions.
            'president_id' => Membre::query()->where('telephone', self::TELEPHONE_PRESIDENTE_CDGMT)->value('id'),
            'ouverture' => now()->subMonth(), 'date_limite' => now()->addMonths(2), 'statut' => StatutCollecte::Ouverte->value,
            'description' => 'Participation de la Daara au Grand Magal : hébergement, repas et transport des pèlerins.',
        ], null, $this->super);
        $service = app(CollecteService::class);

        foreach (array_slice($membres, 0, 7) as $index => $membre) {
            $service->fixerParMembre($collecte, $membre, [25000, 50000, 10000, 100000, 15000, 30000, 20000][$index]);
        }

        foreach (array_slice($membres, 0, 3) as $membre) {
            $service->enregistrerEspeces($collecte, $membre, 10000, $this->representantDe($membre));
        }

        $service->declarer($collecte, $membres[3], 25000, 'T_RECETTE0MAGAL1', $membres[3]->telephone, $membres[3]->user);

        return $collecte;
    }

    /** Un Prokhane en Sass de 5 000 FCFA, organisé par deux commissions permanentes, avec une tâche confiée. */
    private function prokhane(): void
    {
        $culturelle = Commission::query()->where('code', Commission::CULTURELLE)->firstOrFail();
        $organisation = Commission::query()->where('nom', 'Organisation')->first();
        $programmes = app(ProgrammeService::class);

        $prokhane = $programmes->enregistrer([
            'titre' => 'Prokhane '.now()->year, 'type_programme_id' => TypeProgramme::parCode(TypeProgramme::PROKHANE)->id, 'mode' => ModeCollecte::Sass->value, 'montant' => 5000,
            'debut' => now()->addMonths(5)->setTime(17, 0), 'lieu' => 'Kaolack', 'avec_jour_j' => true, 'objectif' => 1500000, 'budget' => 600000,
            'organisation' => Collecte::COMMISSIONS, 'commissions' => array_values(array_filter([
                ['commission_id' => $culturelle->id, 'budget' => 350000], $organisation !== null ? ['commission_id' => $organisation->id, 'budget' => 250000] : null,
            ])),
            'ouverture' => now()->subWeek(), 'date_limite' => now()->addMonths(4), 'statut' => StatutCollecte::Ouverte->value,
            'description' => 'Le Prokhane de la Daara : chaque membre verse au moins le Sass de 5 000 FCFA.',
        ], null, $this->super);

        $programmes->confierTache($prokhane, $culturelle, 'Préparer le programme des khassaides de la nuit', now()->addMonths(4)->toDateString(), $this->super);
    }

    /**
     * Le CDGMT, créé avec le Magal (comité de suivi des dépenses actif) : commissions et chefs, une proposition de Jewrine
     * du suivi des dépenses qui attend le Jewrine national, une réunion convoquée, tenue et son PV publié.
     */
    private function cdgmt(Collecte $magal): void
    {
        $comiteDirecteur = Instance::comiteDirecteur()->membres()->orderBy('membres.id')->get();
        $presidente = Membre::query()->where('telephone', self::TELEPHONE_PRESIDENTE_CDGMT)->firstOrFail();
        $secretaire = $comiteDirecteur->last() ?? $presidente;

        $cdgmt = $magal->comite ?? throw new RuntimeException('Le Magal n\'a pas son comité.');
        $comites = app(ComiteService::class);

        $chefs = Membre::query()->whereIn('telephone', ['+221770100101', '+221770100104', '+221770100107'])->orderBy('telephone')->get();
        $commissions = [];

        foreach ([['Cuisine', 'Menus, achats et service des repas'], ['Hygiène et environnement', 'Bols, toilettes, propreté du site'], ['Transport', 'Cars, chefs de car et itinéraire']] as $index => [$nom, $mission]) {
            $commissions[] = CommissionComite::query()->create(['instance_id' => $cdgmt->id, 'nom' => $nom, 'mission' => $mission, 'chef_id' => $chefs[$index]->id, 'ordre' => $index + 1]);
        }

        $comites->integrerResponsables($cdgmt);
        app(SuiviDepensesService::class)->proposer($cdgmt, Membre::query()->where('telephone', '+221770100110')->firstOrFail(),
            $presidente->user ?? throw new RuntimeException('Compte manquant.'));
        $comites->accueillir($commissions[0], Membre::query()->where('telephone', '+221770100102')->firstOrFail());

        $reunion = Reunion::query()->create([
            'instance_id' => $cdgmt->id, 'titre' => 'Réunion de lancement', 'debut' => now()->subDays(3)->setTime(20, 0),
            'lieu' => 'Siège de la Daara, Kaolack', 'secretaire_id' => $secretaire->id,
        ]);
        $reunion->points()->createMany([
            ['titre' => 'Objectif de la collecte et enveloppe générale', 'ordre' => 1, 'resume' => 'Objectif présenté par le Jewrine national.'],
            ['titre' => 'Composition des commissions', 'ordre' => 2, 'resume' => 'Les chefs composent leurs équipes avant la prochaine réunion.'],
        ]);
        $comites->convoquer($reunion);
        $comites->enregistrerPresences($reunion, array_values(array_map('intval', $cdgmt->membres()->pluck('membres.id')->take(4)->all())), []);
        Decision::query()->create(['reunion_id' => $reunion->id, 'texte' => 'Budget de la cuisine : 1 500 000 FCFA.', 'issue' => 'jewrine_national']);
        Decision::query()->create(['reunion_id' => $reunion->id, 'texte' => 'Location de 10 toilettes mobiles.']);
        ActionComite::query()->create(['reunion_id' => $reunion->id, 'description' => 'Commander 40 bols', 'commission_id' => $commissions[0]->id, 'echeance' => now()->addWeek()]);
        $comites->publier($reunion->fresh() ?? $reunion, $secretaire->user ?? throw new RuntimeException('Compte manquant.'));
    }

    /** Des réponses Arabe et Coran variées, données par les membres eux-mêmes. */
    private function arabeCoran(): void
    {
        $reponses = [
            '+221770100101' => ['lecture' => 'couramment', 'ecriture' => 'couramment', 'comprehension' => 'essentiel', 'coran' => 'hizb', 'nombre_hizb' => 20, 'khassaides' => 'kourel', 'souhaite_apprendre' => false],
            '+221770100102' => ['lecture' => 'difficulte', 'ecriture' => 'ne_sait_pas', 'comprehension' => 'quelques_mots', 'coran' => 'sourates', 'nombre_hizb' => null, 'khassaides' => 'quelques', 'souhaite_apprendre' => true],
            '+221770100104' => ['lecture' => 'excellent', 'ecriture' => 'excellent', 'comprehension' => 'maitrise', 'coran' => 'hafiz', 'nombre_hizb' => null, 'khassaides' => 'kourel', 'souhaite_apprendre' => false],
            '+221770100105' => ['lecture' => 'ne_sait_pas', 'ecriture' => 'ne_sait_pas', 'comprehension' => 'ne_comprend_pas', 'coran' => 'aucun', 'nombre_hizb' => null, 'khassaides' => 'aucun', 'souhaite_apprendre' => true],
            '+221770100107' => ['lecture' => 'couramment', 'ecriture' => 'difficulte', 'comprehension' => 'quelques_mots', 'coran' => 'hizb', 'nombre_hizb' => 5, 'khassaides' => 'quelques', 'souhaite_apprendre' => true],
            '+221770100108' => ['lecture' => 'difficulte', 'ecriture' => 'difficulte', 'comprehension' => 'quelques_mots', 'coran' => 'sourates', 'nombre_hizb' => null, 'khassaides' => 'aucun', 'souhaite_apprendre' => true],
        ];

        foreach ($reponses as $telephone => $reponse) {
            $membre = Membre::query()->where('telephone', $telephone)->firstOrFail();
            app(ArabeCoranService::class)->enregistrer($membre, $reponse, $membre->user ?? throw new RuntimeException('Compte manquant.'));
        }

        $fils = Membre::query()->where('prenom', 'Fallou')->whereNotNull('responsable_id')->firstOrFail();
        app(ArabeCoranService::class)->enregistrer($fils, ['lecture' => 'difficulte', 'ecriture' => 'difficulte', 'comprehension' => 'ne_comprend_pas', 'coran' => 'sourates', 'nombre_hizb' => null, 'khassaides' => 'aucun', 'souhaite_apprendre' => true], $fils->responsable?->user ?? throw new RuntimeException('Compte manquant.'));
    }
}
