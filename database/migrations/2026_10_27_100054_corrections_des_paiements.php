<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R3c : les corrections d'un paiement déjà validé, réservées au Jewrine finance national. Rien ne s'efface : la
 * contre-passation annule par une écriture inverse ; la réaffectation crée un paiement nouveau ; l'avoir devient un
 * crédit du membre ; le remboursement passe par un ordre de dépense. Les mois de cotisation d'un paiement corrigé sont
 * marqués annulés (et redeviennent dus), jamais supprimés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avoirs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->unsignedInteger('montant');
            $table->unsignedInteger('reste');
            $table->string('motif', 255);
            $table->foreignId('paiement_origine_id')->nullable()->constrained('paiements')->restrictOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('paiements', function (Blueprint $table): void {
            $table->foreignId('avoir_id')->nullable()->after('caisse_id')->constrained('avoirs')->restrictOnDelete();
            $table->timestamp('contrepasse_le')->nullable()->after('traite_par');
            $table->foreignId('contrepasse_par')->nullable()->after('contrepasse_le')->constrained('users')->restrictOnDelete();
            $table->string('motif_correction', 255)->nullable()->after('contrepasse_par');
        });

        Schema::create('corrections_paiement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements')->restrictOnDelete();
            $table->string('type', 20)->index();
            $table->string('statut', 20)->default('faite');
            $table->unsignedInteger('montant');
            $table->string('motif', 255);
            $table->foreignId('nouveau_paiement_id')->nullable()->constrained('paiements')->restrictOnDelete();
            $table->foreignId('avoir_id')->nullable()->constrained('avoirs')->restrictOnDelete();
            $table->foreignId('ordre_depense_id')->nullable()->constrained('ordres_depense')->restrictOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Les mois d'un paiement corrigé sont annulés, pas effacés : un même mois peut donc revenir une fois réglé de nouveau.
        Schema::table('cotisations', function (Blueprint $table): void {
            $table->timestamp('annulee_le')->nullable()->after('motif');
            $table->dropUnique(['membre_id', 'mois']);
        });
        DB::statement('CREATE UNIQUE INDEX cotisations_un_mois_en_vigueur ON cotisations (membre_id, mois) WHERE annulee_le IS NULL');

        $maintenant = now();
        if (! DB::table('libelles_depense')->where('nom', 'Remboursement')->exists()) {
            DB::table('libelles_depense')->insert(['nom' => 'Remboursement', 'ordre' => 20, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cotisations_un_mois_en_vigueur');
        Schema::table('cotisations', function (Blueprint $table): void {
            $table->dropColumn('annulee_le');
            $table->unique(['membre_id', 'mois']);
        });
        Schema::dropIfExists('corrections_paiement');
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('avoir_id');
            $table->dropConstrainedForeignId('contrepasse_par');
            $table->dropColumn(['contrepasse_le', 'motif_correction']);
        });
        Schema::dropIfExists('avoirs');
    }
};
