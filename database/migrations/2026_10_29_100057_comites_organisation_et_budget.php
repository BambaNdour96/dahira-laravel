<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R5a : un type commun « Comité d'organisation » (le CDGMT en est un cas, le Grand Ziar a le même espace), avec
 * l'option « comité de suivi des dépenses » (d'office pour un Magal) et son Jewrine, nommé sur proposition du
 * président et validation du Jewrine national, ou désigné par le super administrateur. Le budget de chaque commission
 * est arbitré dans l'enveloppe de l'événement, et chaque révision est tracée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instances', function (Blueprint $table): void {
            $table->boolean('suivi_depenses')->default(false)->after('statut');
        });

        // Les comités du Magal (CDGMT) et les « autres comités » deviennent des comités d'organisation.
        DB::table('instances')->whereIn('type', ['cdgmt', 'autre'])->update(['type' => 'comite_organisation']);
        $magals = DB::table('collectes')->join('types_programme', 'types_programme.id', '=', 'collectes.type_programme_id')
            ->where('types_programme.code', 'magal')->pluck('collectes.id');
        DB::table('instances')->where('type', 'comite_organisation')
            ->where(fn ($query) => $query->whereIn('collecte_id', $magals)->orWhere('code', 'CDGMT'))
            ->update(['suivi_depenses' => true]);

        Schema::table('commissions_comite', function (Blueprint $table): void {
            $table->unsignedInteger('budget_arrete')->nullable()->after('mission');
        });

        Schema::create('revisions_budget', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_comite_id')->constrained('commissions_comite')->restrictOnDelete();
            $table->unsignedInteger('ancien')->nullable();
            $table->unsignedInteger('nouveau');
            $table->string('motif', 255);
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('nominations', function (Blueprint $table): void {
            $table->foreignId('instance_id')->nullable()->after('commission_id')->constrained('instances')->restrictOnDelete();
        });

        Schema::create('propositions_suivi_depenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->restrictOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->foreignId('propose_par')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('proposee')->index();
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->string('motif', 255)->nullable();
            $table->timestamps();
        });

        $maintenant = now();
        if (! DB::table('postes')->where('code', 'jewrine_suivi_depenses')->exists()) {
            DB::table('postes')->insert([
                'nom' => 'Jewrine du comité de suivi des dépenses', 'code' => 'jewrine_suivi_depenses', 'perimetre' => 'comite',
                'titulaire_unique' => true, 'ordre' => 7, 'actif' => true, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        $poste = DB::table('postes')->where('code', 'jewrine_suivi_depenses')->value('id');
        if ($poste !== null) {
            DB::table('nominations')->where('poste_id', $poste)->delete();
            DB::table('postes')->where('id', $poste)->delete();
        }

        Schema::dropIfExists('propositions_suivi_depenses');
        Schema::table('nominations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('instance_id');
        });
        Schema::dropIfExists('revisions_budget');
        Schema::table('commissions_comite', function (Blueprint $table): void {
            $table->dropColumn('budget_arrete');
        });
        DB::table('instances')->where('type', 'comite_organisation')->update(['type' => DB::raw("CASE WHEN code = 'CDGMT' THEN 'cdgmt' ELSE 'autre' END")]);
        Schema::table('instances', function (Blueprint $table): void {
            $table->dropColumn('suivi_depenses');
        });
    }
};
