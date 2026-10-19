<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R2c1 : le renouvellement annuel. Une campagne par exercice, ouverte par le super administrateur ; pour chaque membre,
 * son renouvellement (fiche vérifiée, paiement, revalidation éventuelle). Chaque carte porte son année.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes_renouvellement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exercice_id')->unique()->constrained('exercices')->restrictOnDelete();
            $table->date('date_limite');
            $table->timestamp('ouverte_le');
            $table->foreignId('ouverte_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('relance_30_le')->nullable();
            $table->timestamp('relance_7_le')->nullable();
            $table->timestamp('relance_1_le')->nullable();
            $table->timestamp('cloturee_le')->nullable();
            $table->timestamps();
        });

        Schema::create('renouvellements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campagne_id')->constrained('campagnes_renouvellement')->restrictOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->string('statut', 20)->index();
            $table->string('empreinte_fiche', 64)->nullable();
            $table->timestamp('fiche_verifiee_le')->nullable();
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->restrictOnDelete();
            $table->timestamp('renouvele_le')->nullable();
            $table->foreignId('revalide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['campagne_id', 'membre_id']);
        });

        $this->modifierCartes(function (Blueprint $table): void {
            $table->foreignId('exercice_id')->nullable()->after('membre_id')->constrained('exercices')->restrictOnDelete();
        });
    }

    /**
     * Sous SQLite, Laravel reconstruit la table et perd la clause WHERE de l'index partiel « une seule carte active
     * par membre » : on le recrée à l'identique.
     */
    private function modifierCartes(Closure $modification): void
    {
        $partiels = DB::getDriverName() === 'sqlite'
            ? DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'cartes' AND sql LIKE '% WHERE %'")
            : [];

        Schema::table('cartes', $modification);

        foreach ($partiels as $index) {
            DB::statement('DROP INDEX IF EXISTS '.$index->name);
            DB::statement($index->sql);
        }
    }

    public function down(): void
    {
        $this->modifierCartes(function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exercice_id');
        });
        Schema::dropIfExists('renouvellements');
        Schema::dropIfExists('campagnes_renouvellement');
    }
};
