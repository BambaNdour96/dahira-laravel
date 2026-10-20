<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * La matrice des droits : pour chaque casquette, ce qu'elle permet de faire.
 *
 * Une personne cumule ses casquettes (« une personne, plusieurs casquettes ») : ses droits sont
 * l'addition des capacités de chacune. Une capacité vaut pour toute la Daara ; sa variante
 * « .cellule » ne vaut que pour les cellules que la personne dirige (vérifié dans les services).
 *
 * Le super administrateur reçoit toutes les capacités par cette matrice, et non par un passe-droit :
 * les règles comme « personne ne valide son propre paiement » s'appliquent donc aussi à lui.
 *
 * Toute modification se répercute dans la base avec : php artisan droits:synchroniser
 */
final class Capacites
{
    /** Les casquettes, dans l'ordre d'affichage de la matrice. */
    public const CASQUETTES = [
        'super_administrateur' => 'Super administrateur',
        'jewrine_national' => 'Jewrine national',
        'secretaire_general' => 'Secrétaire général',
        'jewrine_finance' => 'Jewrine finance national',
        'jewrine_cellule' => 'Jewrine de cellule',
        'jewrine_commission' => 'Jewrine de commission',
        'suivi_evaluation' => 'Membre de la commission Suivi et Évaluation',
        'convocateur_comite_directeur' => 'Convocateur du Comité directeur',
        'representant_finance' => 'Représentant finance',
        'comite_directeur' => 'Membre du Comité directeur',
        'membre' => 'Membre',
    ];

    /** Capacités liées à la personne elle-même, que le super administrateur ne reçoit pas d'office. */
    public const PERSONNELLES = ['caisse.tenir'];

    /** Les casquettes qui viennent d'un poste (nomination). */
    public const POSTES = ['jewrine_national', 'secretaire_general', 'jewrine_finance', 'jewrine_cellule', 'jewrine_commission', 'convocateur_comite_directeur'];

    /**
     * Les capacités, par domaine : code => libellé.
     *
     * @var array<string, array<string, string>>
     */
    public const CATALOGUE = [
        'Plateforme' => [
            'plateforme.administrer' => 'Réglages, journaux, sessions, comptes, règles de validation, « voir en tant que »',
            'panneau.acceder' => 'Accéder au panneau du bureau (/admin)',
            'panneau.accueil' => 'Arriver sur le panneau du bureau après la connexion',
            'espace.responsable' => 'Charte de confidentialité et déconnexion rapide des responsables',
            'nominations.voir' => 'Voir les nominations',
            'nominations.gerer' => 'Nommer et mettre fin à une nomination (Jewrine national, Jewrine de commission, convocateur : seul le super administrateur)',
            'referentiel.gerer' => 'Cellules, commissions permanentes, exercices, postes, tarifs',
            'commissions.gerer' => 'Gérer les membres de toutes les commissions permanentes',
        ],
        'Membres et cartes' => [
            'membres.voir' => 'Voir les fiches des membres',
            'membres.inscrire' => 'Inscrire un membre',
            'membres.modifier' => 'Modifier une fiche',
            'membres.valider' => 'Accepter, faire corriger ou rejeter une inscription (toute la Daara : tout changement de statut hors suspension)',
            'membres.suspendre' => 'Suspendre un membre, ou le réactiver',
            'membres.statut_responsables' => 'Changer le statut d\'un responsable',
            'cartes.revoquer' => 'Révoquer une carte',
            'cartes.imprimer' => 'Préparer les lots de cartes en plastique pour l\'imprimeur',
            'membres.importer' => 'Reprendre les listes existantes (import de membres ; le Jewrine pour sa cellule)',
            'membres.exporter' => 'Exporter les membres',
            'membres.changer_cellule' => 'Valider ou refuser un changement de cellule (seul le super administrateur)',
            'renouvellement.ouvrir' => 'Ouvrir la campagne de renouvellement annuel (seul le super administrateur)',
            'arabe_coran.voir' => 'Voir le recensement Arabe et Coran (la commission Culturelle, par son appartenance ; le super administrateur)',
            'arabe_coran.apprenants' => 'Voir les membres qui souhaitent apprendre l\'arabe et le Coran (nom et téléphone seulement)',
        ],
        'Paiements et cotisations' => [
            'paiements.voir' => 'Voir les paiements',
            'paiements.encaisser' => 'Enregistrer un paiement en espèces',
            'paiements.valider' => 'Confirmer ou refuser un paiement déclaré',
            'paiements.valider_bureau' => 'Valider le paiement d\'un responsable, selon les règles de validation croisée',
            'paiements.secours' => 'Valider en secours, hors règles de validation croisée (jamais son propre paiement)',
            'cotisations.dispenser' => 'Dispenser un membre de cotisation',
            'cotisations.suivre' => 'Suivre les cotisations (tableaux de bord)',
            'finances.voir' => 'Voir les rapports financiers',
            'relances.envoyer' => 'Relancer les membres en retard',
            'representants.voir' => 'Voir les représentants finance',
            'representants.designer' => 'Désigner les représentants finance',
            'caisses.recevoir' => 'Recevoir la caisse d\'un représentant',
            'caisse.tenir' => 'Tenir sa caisse de représentant',
        ],
        'Programmes' => [
            'programmes.voir' => 'Voir les programmes et les engagements',
            'programmes.parametrer' => 'Créer, paramétrer et annuler un programme',
            'programmes.cloturer' => 'Clôturer et rouvrir un programme',
            'programmes.attribuer' => 'Attribuer un montant à un membre',
            'programmes.piloter' => 'Modifier un engagement, fiche de paramétrage',
            'programmes.exporter' => 'Exporter un programme et son rapport',
        ],
        'Dahiras et événements' => [
            'evenements.voir' => 'Voir les événements',
            'evenements.gerer' => 'Créer, modifier et annuler un événement',
            'dahiras.voir' => 'Voir les dahiras et l\'assiduité',
            'dahiras.gerer' => 'Créer, modifier et annuler une dahira',
            'pointage.partout' => 'Pointer partout, et corriger un pointage après coup',
            'pointeurs.designer' => 'Désigner les pointeurs',
            'sympathisants.voir' => 'Voir les sympathisants',
            'sympathisants.gerer' => 'Modifier et retirer un sympathisant du registre',
            'sympathisants.participations' => 'Enregistrer la participation d\'un sympathisant',
            'statistiques.voir' => 'Statistiques des événements et des sympathisants',
        ],
        'Comités' => [
            'comites.diriger' => 'Créer un comité d\'organisation, en désigner le président, le gérer',
            'comite_directeur.convoquer' => 'Convoquer le Comité directeur et préparer ses réunions',
            'comites.consulter' => 'Consulter les comités, réunions, décisions et actions',
            'comites.budget_voir' => 'Voir les besoins et les dépenses des commissions',
            'comites.depenses_valider' => 'Valider les dépenses des commissions',
            'comite_directeur.composer' => 'Tenir la liste du Comité directeur',
        ],
        'Projets et Suivi-Évaluation' => [
            'projets.voir' => 'Voir les projets',
            'projets.gerer' => 'Créer et piloter les projets',
            'rapports.rediger' => 'Rédiger des rapports de suivi',
            'rapports.tout_lire' => 'Lire les rapports en brouillon',
            'rapports.corriger' => 'Modifier le brouillon d\'un autre auteur',
        ],
    ];

    /**
     * La matrice : casquette => liste des capacités. « x.cellule » : seulement dans ses cellules.
     *
     * @var array<string, list<string>>
     */
    public const MATRICE = [
        'jewrine_national' => [
            'panneau.acceder', 'panneau.accueil', 'espace.responsable', 'nominations.voir', 'nominations.gerer',
            'membres.voir', 'membres.inscrire', 'membres.modifier', 'membres.valider', 'membres.suspendre',
            'cartes.revoquer', 'membres.importer', 'membres.exporter',
            'paiements.voir', 'paiements.valider_bureau', 'cotisations.dispenser', 'cotisations.suivre', 'finances.voir',
            'representants.voir',
            'programmes.voir', 'programmes.cloturer', 'programmes.attribuer', 'programmes.piloter', 'programmes.exporter',
            'evenements.voir', 'evenements.gerer', 'dahiras.voir', 'pointage.partout', 'sympathisants.voir', 'statistiques.voir',
            'comites.diriger', 'comite_directeur.convoquer', 'comites.consulter', 'comites.budget_voir',
            'projets.voir', 'projets.gerer', 'rapports.rediger',
        ],
        'secretaire_general' => [
            'panneau.acceder', 'panneau.accueil', 'espace.responsable',
            'membres.voir', 'membres.inscrire', 'membres.modifier', 'membres.valider',
            'cartes.revoquer', 'cartes.imprimer', 'membres.importer', 'membres.exporter',
            'paiements.voir', 'paiements.valider_bureau', 'cotisations.suivre', 'finances.voir',
            'programmes.voir', 'programmes.attribuer', 'programmes.piloter', 'programmes.exporter',
            'evenements.voir', 'evenements.gerer', 'dahiras.voir', 'sympathisants.voir', 'statistiques.voir',
            'comite_directeur.convoquer', 'comites.consulter', 'comites.budget_voir',
            'projets.voir', 'rapports.rediger',
        ],
        'jewrine_finance' => [
            'panneau.acceder', 'panneau.accueil', 'espace.responsable',
            'membres.voir', 'membres.exporter',
            'paiements.voir', 'paiements.encaisser', 'paiements.valider', 'cotisations.suivre', 'finances.voir',
            'relances.envoyer', 'representants.voir', 'representants.designer', 'caisses.recevoir',
            'programmes.voir', 'programmes.piloter', 'programmes.exporter',
            'sympathisants.voir', 'sympathisants.participations',
            'comites.budget_voir', 'comites.depenses_valider',
            'projets.voir',
        ],
        'jewrine_cellule' => [
            'panneau.acceder', 'panneau.accueil', 'espace.responsable',
            'membres.voir.cellule', 'membres.inscrire.cellule', 'membres.modifier.cellule', 'membres.valider.cellule', 'membres.importer.cellule', 'arabe_coran.apprenants.cellule',
            'cotisations.suivre.cellule', 'relances.envoyer.cellule', 'programmes.voir.cellule',
            'dahiras.voir.cellule', 'dahiras.gerer.cellule', 'pointeurs.designer.cellule',
            'sympathisants.voir.cellule', 'sympathisants.gerer.cellule',
        ],
        'jewrine_commission' => ['panneau.acceder', 'espace.responsable'],
        'suivi_evaluation' => [
            'panneau.acceder', 'espace.responsable',
            'cotisations.suivre', 'finances.voir',
            'programmes.voir', 'programmes.exporter',
            'evenements.voir', 'dahiras.voir', 'sympathisants.voir', 'statistiques.voir',
            'projets.voir', 'projets.gerer', 'rapports.rediger', 'rapports.tout_lire',
        ],
        'convocateur_comite_directeur' => ['panneau.acceder', 'espace.responsable', 'comites.consulter', 'comite_directeur.convoquer'],
        'representant_finance' => ['espace.responsable', 'caisse.tenir'],
        'comite_directeur' => ['panneau.acceder', 'espace.responsable', 'comites.consulter', 'comites.budget_voir'],
        'membre' => [],
    ];

    /**
     * Toutes les capacités, avec leur variante « .cellule » quand une casquette l'utilise.
     *
     * @return list<string>
     */
    public static function toutes(): array
    {
        $codes = [];

        foreach (self::CATALOGUE as $capacites) {
            foreach (array_keys($capacites) as $code) {
                $codes[] = $code;
            }
        }

        foreach (self::MATRICE as $capacites) {
            foreach ($capacites as $code) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Les capacités d'une casquette : la matrice, ou toutes pour le super administrateur.
     *
     * @return list<string>
     */
    public static function de(string $casquette): array
    {
        return $casquette === 'super_administrateur'
            ? array_values(array_filter(
                self::toutes(),
                fn (string $code): bool => ! str_ends_with($code, '.cellule') && ! in_array($code, self::PERSONNELLES, true),
            ))
            : self::MATRICE[$casquette] ?? [];
    }

    /** Portée d'une capacité pour une casquette : « Daara », « Cellule » ou null. */
    public static function portee(string $casquette, string $capacite): ?string
    {
        $capacites = self::de($casquette);

        return match (true) {
            in_array($capacite, $capacites, true) => 'Daara',
            in_array($capacite.'.cellule', $capacites, true) => 'Cellule',
            default => null,
        };
    }

    /** Abréviations des casquettes, pour le tableau du guide. */
    public const ABREVIATIONS = [
        'super_administrateur' => 'SA', 'jewrine_national' => 'JN', 'secretaire_general' => 'SG', 'jewrine_finance' => 'JF',
        'jewrine_cellule' => 'JC', 'jewrine_commission' => 'JCo', 'suivi_evaluation' => 'SE',
        'convocateur_comite_directeur' => 'CCD', 'representant_finance' => 'RF', 'comite_directeur' => 'CD', 'membre' => 'M',
    ];

    /** Le tableau de la matrice, tel qu'il figure dans docs/guide/nominations.md (un test vérifie qu'ils concordent). */
    public static function tableauMarkdown(): string
    {
        $texte = '';

        foreach (self::CATALOGUE as $domaine => $capacites) {
            $texte .= "\n### {$domaine}\n\n| Capacité | ".implode(' | ', self::ABREVIATIONS)." |\n| --- |".str_repeat(' :-: |', count(self::ABREVIATIONS))."\n";

            foreach ($capacites as $code => $libelle) {
                $cases = array_map(fn (string $casquette): string => match (self::portee($casquette, $code)) {
                    'Daara' => '✔',
                    'Cellule' => 'cellule',
                    default => '',
                }, array_keys(self::ABREVIATIONS));

                $texte .= "| {$libelle} <br>`{$code}` | ".implode(' | ', $cases)." |\n";
            }
        }

        return $texte;
    }

    /** Crée les casquettes et les capacités dans la base, puis attribue à chaque casquette les siennes. */
    public static function synchroniser(): void
    {
        $registre = app(PermissionRegistrar::class);
        $registre->forgetCachedPermissions();

        foreach (self::toutes() as $code) {
            Permission::findOrCreate($code, 'web');
        }

        foreach (array_keys(self::CASQUETTES) as $casquette) {
            Role::findOrCreate($casquette, 'web')->syncPermissions(self::de($casquette));
        }

        // Les capacités qui ne figurent plus dans la matrice sont retirées.
        Permission::query()->where('guard_name', 'web')->whereNotIn('name', self::toutes())->get()->each->delete();

        $registre->forgetCachedPermissions();
    }
}
