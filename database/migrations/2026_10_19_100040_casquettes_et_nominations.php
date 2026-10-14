<?php

use App\Support\Capacites;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot R1a « Capacités cumulables ».
 *
 * - Les postes portent leur casquette (code), leur portée (nationale ou cellule) et le fait qu'un seul titulaire
 *   les occupe à la fois. Les postes sans droits sont archivés.
 * - Les nominations deviennent la seule vérité : qui occupe quel poste, depuis quand, désigné par qui,
 *   jusqu'à quand. Elles remplacent membres.poste_id et cellules.responsable_id.
 * - Les rôles « tresorier » et « responsable_section » prennent leur vrai nom ; le rôle « administrateur »
 *   disparaît : les droits viennent de la matrice des capacités (App\Support\Capacites).
 */
return new class extends Migration
{
    /**
     * Les postes avec droits : ancien nom => [nouveau nom, code, portée, titulaire unique, ordre].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: bool, 4: int}>
     */
    private const POSTES = [
        'Jewrine national' => ['Jewrine national', 'jewrine_national', 'national', true, 1],
        'Secrétaire général' => ['Secrétaire général', 'secretaire_general', 'national', true, 2],
        'Jewrine finance' => ['Jewrine finance national', 'jewrine_finance', 'national', true, 3],
        'Jewrine cellule' => ['Jewrine de cellule', 'jewrine_cellule', 'cellule', true, 4],
        'Membre de Suivi et Évaluation' => ['Membre de Suivi et Évaluation', 'suivi_evaluation', 'national', false, 5],
    ];

    private const RENOMMAGES = ['tresorier' => 'jewrine_finance', 'responsable_section' => 'jewrine_cellule'];

    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->unique();
            $table->string('perimetre', 10)->default('national');
            $table->boolean('titulaire_unique')->default(true);
        });

        Schema::create('nominations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('poste_id')->constrained('postes')->restrictOnDelete();
            $table->foreignId('cellule_id')->nullable()->constrained('cellules')->restrictOnDelete();
            $table->date('debut_le');
            $table->foreignId('designe_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('fin_le')->nullable()->index();
            $table->foreignId('fin_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif_fin', 255)->nullable();
            $table->timestamps();
        });

        // Une même personne n'occupe pas deux fois le même poste au même moment.
        DB::statement('CREATE UNIQUE INDEX nominations_en_cours ON nominations (user_id, poste_id, COALESCE(cellule_id, 0)) WHERE fin_le IS NULL');

        $this->preparerPostes();

        foreach (self::RENOMMAGES as $ancien => $nouveau) {
            DB::table('roles')->where('name', $ancien)->where('guard_name', 'web')->update(['name' => $nouveau]);
        }

        $this->reprendreLesNominations();

        Schema::table('membres', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('poste_id');
        });

        Schema::table('cellules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsable_id');
        });

        $administrateur = DB::table('roles')->where('name', 'administrateur')->value('id');

        if ($administrateur !== null) {
            DB::table('model_has_roles')->where('role_id', $administrateur)->delete();
            DB::table('role_has_permissions')->where('role_id', $administrateur)->delete();
            DB::table('roles')->where('id', $administrateur)->delete();
        }

        Capacites::synchroniser();
    }

    public function down(): void
    {
        Schema::table('cellules', function (Blueprint $table): void {
            $table->foreignId('responsable_id')->nullable()->constrained('users')->restrictOnDelete();
        });

        Schema::table('membres', function (Blueprint $table): void {
            $table->foreignId('poste_id')->nullable()->constrained('postes')->restrictOnDelete();
        });

        $enCours = DB::table('nominations')
            ->join('postes', 'postes.id', '=', 'nominations.poste_id')
            ->whereNull('nominations.fin_le')
            ->orderBy('postes.ordre')
            ->get(['nominations.user_id', 'nominations.poste_id', 'nominations.cellule_id', 'postes.code']);

        foreach ($enCours as $nomination) {
            if ($nomination->code === 'jewrine_cellule' && $nomination->cellule_id !== null) {
                DB::table('cellules')->where('id', $nomination->cellule_id)->update(['responsable_id' => $nomination->user_id]);
            }

            DB::table('membres')->where('user_id', $nomination->user_id)->whereNull('poste_id')->update(['poste_id' => $nomination->poste_id]);
        }

        foreach (self::RENOMMAGES as $ancien => $nouveau) {
            DB::table('roles')->where('name', $nouveau)->where('guard_name', 'web')->update(['name' => $ancien]);
        }

        // Le socle « administrateur » revient pour la direction et le super administrateur.
        $administrateur = DB::table('roles')->insertGetId(['name' => 'administrateur', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $direction = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['super_administrateur', 'jewrine_national', 'secretaire_general'])
            ->distinct()
            ->pluck('model_has_roles.model_id');

        foreach ($direction as $userId) {
            DB::table('model_has_roles')->insertOrIgnore(['role_id' => $administrateur, 'model_type' => 'App\\Models\\User', 'model_id' => $userId]);
        }

        DB::table('role_has_permissions')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('permissions')->delete();

        DB::statement('DROP INDEX IF EXISTS nominations_en_cours');
        Schema::dropIfExists('nominations');

        DB::table('postes')->where('code', 'suivi_evaluation')->whereNotExists(
            fn ($query) => $query->select(DB::raw(1))->from('membres')->whereColumn('membres.poste_id', 'postes.id'),
        )->delete();

        foreach (self::POSTES as $ancien => [$nom]) {
            DB::table('postes')->where('nom', $nom)->update(['nom' => $ancien]);
        }

        DB::table('postes')->update(['actif' => true]);

        Schema::table('postes', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'perimetre', 'titulaire_unique']);
        });
    }

    /** Les postes avec droits reçoivent leur code ; les autres sont archivés, jamais effacés. */
    private function preparerPostes(): void
    {
        DB::table('postes')->whereNotIn('nom', array_keys(self::POSTES))->update(['actif' => false, 'updated_at' => now()]);

        foreach (self::POSTES as $ancien => [$nom, $code, $perimetre, $unique, $ordre]) {
            $valeurs = ['nom' => $nom, 'code' => $code, 'perimetre' => $perimetre, 'titulaire_unique' => $unique, 'ordre' => $ordre, 'actif' => true, 'updated_at' => now()];

            if (DB::table('postes')->where('nom', $ancien)->exists()) {
                DB::table('postes')->where('nom', $ancien)->update($valeurs);
            } else {
                DB::table('postes')->insert([...$valeurs, 'created_at' => now()]);
            }
        }
    }

    /**
     * Chaque titulaire actuel d'un poste reçoit sa nomination, datée du jour (désigné par : reprise).
     * Sources : les rôles de poste, les Jewrine de cellule (cellules.responsable_id) et membres.poste_id.
     */
    private function reprendreLesNominations(): void
    {
        $postes = DB::table('postes')->whereNotNull('code')->pluck('id', 'code');
        $maintenant = now();
        $nominations = [];

        $titulaires = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->whereIn('roles.name', ['jewrine_national', 'secretaire_general', 'jewrine_finance', 'suivi_evaluation'])
            ->get(['model_has_roles.model_id', 'roles.name']);

        foreach ($titulaires as $titulaire) {
            $nominations[$titulaire->model_id.'-'.$titulaire->name.'-0'] = [(int) $titulaire->model_id, (int) $postes[$titulaire->name], null];
        }

        foreach (DB::table('cellules')->whereNotNull('responsable_id')->get(['id', 'responsable_id']) as $cellule) {
            $nominations[$cellule->responsable_id.'-jewrine_cellule-'.$cellule->id] = [(int) $cellule->responsable_id, (int) $postes['jewrine_cellule'], (int) $cellule->id];
        }

        $parPoste = DB::table('membres')
            ->join('postes', 'postes.id', '=', 'membres.poste_id')
            ->whereNotNull('membres.user_id')
            ->whereNotNull('postes.code')
            ->where('postes.perimetre', 'national')
            ->get(['membres.user_id', 'postes.code']);

        foreach ($parPoste as $membre) {
            $nominations[$membre->user_id.'-'.$membre->code.'-0'] = [(int) $membre->user_id, (int) $postes[$membre->code], null];
        }

        foreach ($nominations as [$userId, $posteId, $celluleId]) {
            DB::table('nominations')->insert([
                'user_id' => $userId,
                'poste_id' => $posteId,
                'cellule_id' => $celluleId,
                'debut_le' => $maintenant->toDateString(),
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);

            $role = DB::table('postes')->where('id', $posteId)->value('code');
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');

            if ($roleId !== null) {
                DB::table('model_has_roles')->insertOrIgnore(['role_id' => $roleId, 'model_type' => 'App\\Models\\User', 'model_id' => $userId]);
            }
        }
    }
};
