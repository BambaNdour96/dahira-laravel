<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le suppléant du Jewrine du comité de suivi des dépenses : désigné par le Jewrine parmi les membres de son comité,
 * validé par le Jewrine national, nommé comme un poste (une seule suppléance active par comité). Il signe les ordres
 * quand le Jewrine est indisponible ; chaque signature le dit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propositions_suivi_depenses', function (Blueprint $table): void {
            $table->string('poste', 40)->default('jewrine_suivi_depenses')->after('membre_id');
        });

        Schema::table('ordres_depense', function (Blueprint $table): void {
            $table->boolean('signe_par_suppleant')->default(false)->after('signe_par');
        });

        if (! DB::table('postes')->where('code', 'suppleant_suivi_depenses')->exists()) {
            $maintenant = now();
            DB::table('postes')->insert([
                'nom' => 'Suppléant du Jewrine du suivi des dépenses', 'code' => 'suppleant_suivi_depenses', 'perimetre' => 'comite',
                'titulaire_unique' => true, 'ordre' => 8, 'actif' => true, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        $poste = DB::table('postes')->where('code', 'suppleant_suivi_depenses')->value('id');
        if ($poste !== null) {
            DB::table('nominations')->where('poste_id', $poste)->delete();
            DB::table('postes')->where('id', $poste)->delete();
        }

        Schema::table('ordres_depense', function (Blueprint $table): void {
            $table->dropColumn('signe_par_suppleant');
        });
        Schema::table('propositions_suivi_depenses', function (Blueprint $table): void {
            $table->dropColumn('poste');
        });
    }
};
