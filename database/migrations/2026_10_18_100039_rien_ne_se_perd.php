<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot R0 « Rien ne se perd ».
 *
 * - Plus aucun effacement en cascade : la base refuse de supprimer une ligne dont dépendent
 *   des paiements, des présences, des décisions ou toute autre trace.
 * - Ce qui n'a plus cours est annulé (programmes, événements, dahiras, réunions), archivé
 *   (cellules, étapes, indicateurs, commissions d'un comité) ou clos (pointeurs, représentants finance).
 * - Un sympathisant retiré du registre est anonymisé : ses participations restent.
 */
return new class extends Migration
{
    /**
     * Liens qui effaçaient ou vidaient en cascade : table, colonne, table visée, comportement d'origine.
     * Les tables techniques (imports, exports, clés d'empreinte, rôles) gardent leur cascade.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const LIENS = [
        ['actions_comite', 'commission_id', 'commissions_comite', 'set null'],
        ['actions_comite', 'instance_id', 'instances', 'cascade'],
        ['actions_comite', 'responsable_id', 'membres', 'set null'],
        ['actions_comite', 'reunion_id', 'reunions', 'cascade'],
        ['besoins_commission', 'commission_id', 'commissions_comite', 'cascade'],
        ['besoins_commission', 'propose_par', 'users', 'set null'],
        ['besoins_commission', 'traite_par', 'users', 'set null'],
        ['cartes', 'membre_id', 'membres', 'cascade'],
        ['cartes', 'revoquee_par', 'users', 'set null'],
        ['cellule_pointeurs', 'cellule_id', 'cellules', 'cascade'],
        ['cellule_pointeurs', 'user_id', 'users', 'cascade'],
        ['cellules', 'responsable_id', 'users', 'set null'],
        ['collectes', 'cellule_id', 'cellules', 'set null'],
        ['collectes', 'cree_par', 'users', 'set null'],
        ['collectes', 'evenement_id', 'evenements', 'set null'],
        ['commission_comite_membre', 'commission_comite_id', 'commissions_comite', 'cascade'],
        ['commission_comite_membre', 'membre_id', 'membres', 'cascade'],
        ['commission_membre', 'commission_id', 'commissions', 'cascade'],
        ['commission_membre', 'membre_id', 'membres', 'cascade'],
        ['commissions_comite', 'chef_id', 'membres', 'set null'],
        ['commissions_comite', 'instance_id', 'instances', 'cascade'],
        ['connexions', 'user_id', 'users', 'set null'],
        ['cotisations', 'membre_id', 'membres', 'cascade'],
        ['cotisations', 'paiement_id', 'paiements', 'set null'],
        ['decisions', 'instance_id', 'instances', 'cascade'],
        ['decisions', 'reunion_id', 'reunions', 'cascade'],
        ['demandes_suppression', 'membre_id', 'membres', 'set null'],
        ['demandes_suppression', 'traitee_par', 'users', 'set null'],
        ['demandes_suppression', 'user_id', 'users', 'set null'],
        ['depenses_commission', 'commission_id', 'commissions_comite', 'cascade'],
        ['depenses_commission', 'declaree_par', 'users', 'set null'],
        ['depenses_commission', 'traitee_par', 'users', 'set null'],
        ['engagements', 'collecte_id', 'collectes', 'cascade'],
        ['engagements', 'membre_id', 'membres', 'cascade'],
        ['evenement_controleur', 'evenement_id', 'evenements', 'cascade'],
        ['evenement_controleur', 'user_id', 'users', 'cascade'],
        ['evenements', 'auteur_id', 'users', 'set null'],
        ['evenements', 'cellule_id', 'cellules', 'set null'],
        ['historique_engagements', 'auteur_id', 'users', 'set null'],
        ['historique_engagements', 'engagement_id', 'engagements', 'cascade'],
        ['indicateurs', 'projet_id', 'projets', 'cascade'],
        ['instance_membre', 'instance_id', 'instances', 'cascade'],
        ['instance_membre', 'membre_id', 'membres', 'cascade'],
        ['instances', 'collecte_id', 'collectes', 'set null'],
        ['instances', 'cree_par', 'users', 'set null'],
        ['instances', 'president_id', 'membres', 'set null'],
        ['jalons', 'projet_id', 'projets', 'cascade'],
        ['journal_activites', 'user_id', 'users', 'set null'],
        ['journal_exports', 'user_id', 'users', 'set null'],
        ['membres', 'poste_id', 'postes', 'set null'],
        ['membres', 'relance_par', 'users', 'set null'],
        ['membres', 'user_id', 'users', 'set null'],
        ['membres', 'valide_par', 'users', 'set null'],
        ['paiements', 'collecte_id', 'collectes', 'set null'],
        ['paiements', 'membre_id', 'membres', 'cascade'],
        ['paiements', 'remise_id', 'remises_fonds', 'set null'],
        ['paiements', 'traite_par', 'users', 'set null'],
        ['participations_sympathisants', 'collecte_id', 'collectes', 'cascade'],
        ['participations_sympathisants', 'enregistre_par', 'users', 'set null'],
        ['participations_sympathisants', 'sympathisant_id', 'sympathisants', 'cascade'],
        ['presences', 'enregistree_par', 'users', 'set null'],
        ['presences', 'evenement_id', 'evenements', 'cascade'],
        ['presences', 'membre_id', 'membres', 'cascade'],
        ['projets', 'collecte_id', 'collectes', 'set null'],
        ['projets', 'commission_id', 'commissions', 'set null'],
        ['projets', 'cree_par', 'users', 'set null'],
        ['projets', 'responsable_id', 'membres', 'set null'],
        ['rappels', 'collecte_id', 'collectes', 'cascade'],
        ['rappels', 'membre_id', 'membres', 'cascade'],
        ['rapports_suivi', 'auteur_id', 'users', 'set null'],
        ['rapports_suivi', 'collecte_id', 'collectes', 'set null'],
        ['rapports_suivi', 'projet_id', 'projets', 'set null'],
        ['regles_validation', 'poste_id', 'postes', 'cascade'],
        ['representants_finance', 'cellule_id', 'cellules', 'cascade'],
        ['representants_finance', 'designe_par', 'users', 'set null'],
        ['representants_finance', 'user_id', 'users', 'cascade'],
        ['reunion_points', 'reunion_id', 'reunions', 'cascade'],
        ['reunion_presences', 'membre_id', 'membres', 'cascade'],
        ['reunion_presences', 'reunion_id', 'reunions', 'cascade'],
        ['reunions', 'instance_id', 'instances', 'cascade'],
        ['reunions', 'publiee_par', 'users', 'set null'],
        ['reunions', 'secretaire_id', 'membres', 'set null'],
        ['suivis_projet', 'auteur_id', 'users', 'set null'],
        ['suivis_projet', 'projet_id', 'projets', 'cascade'],
        ['sympathisants', 'cree_par', 'users', 'set null'],
        ['sympathisants', 'invite_par_id', 'membres', 'set null'],
        ['sympathisants', 'membre_id', 'membres', 'set null'],
        ['visites_sympathisants', 'enregistree_par', 'users', 'set null'],
        ['visites_sympathisants', 'evenement_id', 'evenements', 'cascade'],
        ['visites_sympathisants', 'sympathisant_id', 'sympathisants', 'cascade'],
    ];

    /** Ce qui s'annule : un motif, une date, un auteur. */
    private const ANNULABLES = ['collectes', 'evenements', 'reunions'];

    /** Ce qui s'archive : masqué des listes, jamais effacé. */
    private const ARCHIVABLES = ['cellules', 'jalons', 'indicateurs', 'commissions_comite'];

    /** Les désignations qui prennent fin : l'historique reste. */
    private const CLOTURABLES = [
        'cellule_pointeurs' => ['cellule_id', 'user_id'],
        'representants_finance' => ['cellule_id', 'perimetre'],
    ];

    public function up(): void
    {
        foreach ($this->parTable() as $table => $liens) {
            $this->modifier($table, function (Blueprint $blueprint) use ($liens): void {
                foreach ($liens as [$colonne, $cible]) {
                    $blueprint->dropForeign([$colonne]);
                    $blueprint->foreign($colonne)->references('id')->on($cible)->restrictOnDelete();
                }
            });
        }

        foreach (self::ANNULABLES as $table) {
            $this->modifier($table, function (Blueprint $blueprint): void {
                $blueprint->timestamp('annule_le')->nullable();
                $blueprint->foreignId('annule_par')->nullable()->constrained('users')->restrictOnDelete();
                $blueprint->string('motif_annulation', 255)->nullable();
            });
        }

        foreach (self::ARCHIVABLES as $table) {
            $this->modifier($table, function (Blueprint $blueprint): void {
                $blueprint->timestamp('archive_le')->nullable();
                $blueprint->foreignId('archive_par')->nullable()->constrained('users')->restrictOnDelete();
            });
        }

        foreach (self::CLOTURABLES as $table => $unique) {
            $this->modifier($table, function (Blueprint $blueprint) use ($unique): void {
                $blueprint->dropUnique($unique);
                $blueprint->timestamp('fin_le')->nullable();
                $blueprint->foreignId('fin_par')->nullable()->constrained('users')->restrictOnDelete();
            });

            // Une seule désignation en cours à la fois ; les anciennes restent.
            DB::statement(sprintf(
                'CREATE UNIQUE INDEX %s_en_cours ON %s (%s) WHERE fin_le IS NULL',
                $table, $table, implode(', ', $unique),
            ));
        }

        Schema::table('sympathisants', function (Blueprint $blueprint): void {
            $blueprint->timestamp('anonymise_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sympathisants', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('anonymise_le');
        });

        foreach (self::CLOTURABLES as $table => $unique) {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s_en_cours', $table));

            $this->modifier($table, function (Blueprint $blueprint) use ($unique): void {
                $blueprint->dropConstrainedForeignId('fin_par');
                $blueprint->dropColumn('fin_le');
                $blueprint->unique($unique);
            });
        }

        foreach (self::ARCHIVABLES as $table) {
            $this->modifier($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('archive_par');
                $blueprint->dropColumn('archive_le');
            });
        }

        foreach (self::ANNULABLES as $table) {
            $this->modifier($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('annule_par');
                $blueprint->dropColumn(['annule_le', 'motif_annulation']);
            });
        }

        foreach ($this->parTable() as $table => $liens) {
            $this->modifier($table, function (Blueprint $blueprint) use ($liens): void {
                foreach ($liens as [$colonne, $cible, $origine]) {
                    $blueprint->dropForeign([$colonne]);
                    $blueprint->foreign($colonne)->references('id')->on($cible)->onDelete($origine);
                }
            });
        }
    }

    /**
     * Les liens regroupés par table, pour ne reconstruire chaque table qu'une fois sous SQLite.
     *
     * @return array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private function parTable(): array
    {
        $tables = [];

        foreach (self::LIENS as [$table, $colonne, $cible, $origine]) {
            $tables[$table][] = [$colonne, $cible, $origine];
        }

        return $tables;
    }

    /**
     * Modifie une table. Sous SQLite, Laravel la reconstruit et perd la clause WHERE des index
     * partiels (par exemple « une seule carte active par membre ») : on les recrée à l'identique.
     */
    private function modifier(string $table, Closure $modification): void
    {
        $partiels = DB::getDriverName() === 'sqlite'
            ? DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql LIKE '% WHERE %'", [$table])
            : [];

        Schema::table($table, $modification);

        foreach ($partiels as $index) {
            DB::statement('DROP INDEX IF EXISTS '.$index->name);
            DB::statement($index->sql);
        }
    }
};
