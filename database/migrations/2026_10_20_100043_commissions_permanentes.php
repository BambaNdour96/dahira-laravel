<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lot R1b : les commissions permanentes.
 *
 * - Liste : Administration, Suivi et Évaluation, Communication, Sociale, Culturelle, Finance, Organisation.
 * - Chaque commission a son Jewrine (nomination « Jewrine de commission ») et son espace (un comité lié).
 * - L'appartenance devient une donnée suivie : souhait à l'inscription, accepté ou refusé par le Jewrine,
 *   date d'arrivée et de départ. Les membres actuels restent membres (reprise).
 * - Suivi et Évaluation devient une commission : les titulaires du poste provisoire en deviennent membres.
 */
return new class extends Migration
{
    private const RENOMMAGES = ['Finances' => 'Finance', 'Culturelle et religieuse' => 'Culturelle'];

    private const SUIVI = 'Suivi et Évaluation';

    public function up(): void
    {
        $maintenant = now();

        Schema::table('commissions', function (Blueprint $table): void {
            $table->string('code', 40)->nullable()->unique();
        });

        foreach (self::RENOMMAGES as $ancien => $nouveau) {
            if (! DB::table('commissions')->where('nom', $nouveau)->exists()) {
                DB::table('commissions')->where('nom', $ancien)->update(['nom' => $nouveau, 'updated_at' => $maintenant]);
            }
        }

        if (DB::table('commissions')->where('nom', self::SUIVI)->exists()) {
            DB::table('commissions')->where('nom', self::SUIVI)->update(['code' => 'suivi_evaluation']);
        } else {
            DB::table('commissions')->insert([
                'nom' => self::SUIVI, 'code' => 'suivi_evaluation', 'actif' => true,
                'description' => 'Suit les projets et les programmes, évalue, alerte et rend compte au Comité directeur.',
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }

        Schema::create('appartenances_commission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->restrictOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->timestamp('souhaite_le')->nullable();
            $table->date('debut_le')->nullable()->index();
            $table->foreignId('ajoute_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('refuse_le')->nullable();
            $table->foreignId('refuse_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamp('fin_le')->nullable()->index();
            $table->foreignId('fin_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif_fin', 255)->nullable();
            $table->timestamps();
        });

        // Une seule demande ou appartenance en cours par membre et par commission.
        DB::statement('CREATE UNIQUE INDEX appartenances_en_cours ON appartenances_commission (commission_id, membre_id) WHERE fin_le IS NULL AND refuse_le IS NULL');

        foreach (DB::table('commission_membre')->get() as $ligne) {
            DB::table('appartenances_commission')->insert([
                'commission_id' => $ligne->commission_id,
                'membre_id' => $ligne->membre_id,
                'debut_le' => ($ligne->created_at !== null ? Carbon::parse($ligne->created_at) : $maintenant)->toDateString(),
                'created_at' => $ligne->created_at ?? $maintenant,
                'updated_at' => $maintenant,
            ]);
        }

        Schema::drop('commission_membre');

        // Le Jewrine de commission se nomme comme les autres postes ; la nomination porte sa commission.
        DB::statement('DROP INDEX IF EXISTS nominations_en_cours');
        Schema::table('nominations', function (Blueprint $table): void {
            $table->foreignId('commission_id')->nullable()->after('cellule_id')->constrained('commissions')->restrictOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX nominations_en_cours ON nominations (user_id, poste_id, COALESCE(cellule_id, 0), COALESCE(commission_id, 0)) WHERE fin_le IS NULL');

        foreach ([
            ['Jewrine de commission', 'jewrine_commission', 'commission', true, 6],
            ['Convocateur du Comité directeur', 'convocateur_comite_directeur', 'national', false, 7],
        ] as [$nom, $code, $perimetre, $unique, $ordre]) {
            if (! DB::table('postes')->where('code', $code)->exists()) {
                DB::table('postes')->insert([
                    'nom' => $nom, 'code' => $code, 'perimetre' => $perimetre, 'titulaire_unique' => $unique,
                    'ordre' => $ordre, 'actif' => true, 'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
            }
        }

        $this->suiviEtEvaluationDevientUneCommission();
        $this->creerLesEspaces();
    }

    public function down(): void
    {
        DB::table('instance_membre')->whereIn('instance_id', DB::table('instances')->where('type', 'commission')->select('id'))->delete();

        // Les espaces des commissions sans réunion disparaissent ; ceux qui ont servi deviennent des « autres comités ».
        $espaces = DB::table('instances')->where('type', 'commission')->pluck('id');
        $utilises = DB::table('reunions')->whereIn('instance_id', $espaces)->distinct()->pluck('instance_id');
        DB::table('instances')->whereIn('id', $utilises)->update(['type' => 'autre']);
        DB::table('instances')->whereIn('id', $espaces->diff($utilises))->delete();

        Schema::table('instances', function (Blueprint $table): void {
            $table->dropUnique(['commission_id']);
            $table->dropConstrainedForeignId('commission_id');
        });

        DB::table('postes')->where('code', 'suivi_evaluation')->update(['actif' => true]);

        DB::statement('DROP INDEX IF EXISTS nominations_en_cours');
        Schema::table('nominations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('commission_id');
        });
        DB::statement('CREATE UNIQUE INDEX nominations_en_cours ON nominations (user_id, poste_id, COALESCE(cellule_id, 0)) WHERE fin_le IS NULL');

        Schema::create('commission_membre', function (Blueprint $table): void {
            $table->foreignId('commission_id')->constrained('commissions')->restrictOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->primary(['commission_id', 'membre_id']);
            $table->timestamps();
        });

        $actives = DB::table('appartenances_commission')->whereNotNull('debut_le')->whereNull('fin_le')->whereNull('refuse_le')->get();

        foreach ($actives as $appartenance) {
            DB::table('commission_membre')->insertOrIgnore([
                'commission_id' => $appartenance->commission_id,
                'membre_id' => $appartenance->membre_id,
                'created_at' => $appartenance->created_at,
                'updated_at' => now(),
            ]);
        }

        DB::statement('DROP INDEX IF EXISTS appartenances_en_cours');
        Schema::drop('appartenances_commission');

        foreach (self::RENOMMAGES as $ancien => $nouveau) {
            DB::table('commissions')->where('nom', $nouveau)->update(['nom' => $ancien]);
        }

        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    /** Les titulaires du poste provisoire deviennent membres de la commission ; leur nomination prend fin. */
    private function suiviEtEvaluationDevientUneCommission(): void
    {
        $maintenant = now();
        $poste = DB::table('postes')->where('code', 'suivi_evaluation')->value('id');
        $commission = DB::table('commissions')->where('code', 'suivi_evaluation')->value('id');

        if ($poste === null || $commission === null) {
            return;
        }

        $nominations = DB::table('nominations')->where('poste_id', $poste)->whereNull('fin_le')->get(['id', 'user_id']);

        foreach ($nominations as $nomination) {
            $membre = DB::table('membres')->where('user_id', $nomination->user_id)->value('id');

            $dejaMembre = $membre === null || DB::table('appartenances_commission')
                ->where('commission_id', $commission)->where('membre_id', $membre)
                ->whereNull('fin_le')->whereNull('refuse_le')->exists();

            if (! $dejaMembre) {
                DB::table('appartenances_commission')->insert([
                    'commission_id' => $commission, 'membre_id' => $membre, 'debut_le' => $maintenant->toDateString(),
                    'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
            }

            DB::table('nominations')->where('id', $nomination->id)->update([
                'fin_le' => $maintenant, 'motif_fin' => 'Suivi et Évaluation devient une commission permanente', 'updated_at' => $maintenant,
            ]);
        }

        DB::table('postes')->where('id', $poste)->update(['actif' => false, 'updated_at' => $maintenant]);
    }

    /** Chaque commission reçoit son espace (un comité lié) : réunions, PV, décisions, tâches. */
    private function creerLesEspaces(): void
    {
        Schema::table('instances', function (Blueprint $table): void {
            $table->foreignId('commission_id')->nullable()->unique()->constrained('commissions')->restrictOnDelete();
        });

        $maintenant = now();

        foreach (DB::table('commissions')->get(['id', 'nom']) as $commission) {
            $instance = DB::table('instances')->insertGetId([
                'type' => 'commission',
                'nom' => 'Commission '.$commission->nom,
                'code' => mb_substr('C-'.Str::upper(Str::slug($commission->nom, '')), 0, 15),
                'annee' => (int) $maintenant->format('Y'),
                'commission_id' => $commission->id,
                'mise_en_place' => $maintenant->toDateString(),
                'statut' => 'active',
                'quorum' => 50,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);

            $membres = DB::table('appartenances_commission')
                ->where('commission_id', $commission->id)
                ->whereNotNull('debut_le')->whereNull('fin_le')->whereNull('refuse_le')
                ->pluck('membre_id');

            foreach ($membres as $membre) {
                DB::table('instance_membre')->insert(['instance_id' => $instance, 'membre_id' => $membre, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
            }
        }
    }
};
