<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R3b : aucun argent ne sort sans un ordre de dépense. L'ordre est demandé, signé par le Jewrine national, puis exécuté par
 * la Finance depuis une caisse, avec sa preuve. Chaque dépense est rattachée (programme et commission, projet, ou
 * fonctionnement) et porte un libellé, créé librement.
 */
return new class extends Migration
{
    private const LIBELLES = ['Achats de vivres', 'Location de matériel', 'Transport', 'Sonorisation', 'Hébergement', 'Impression et communication', 'Frais bancaires et de transfert', 'Entretien et réparations', 'Dons et solidarité', 'Divers'];

    public function up(): void
    {
        Schema::create('libelles_depense', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('archive_le')->nullable();
            $table->foreignId('archive_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ordres_depense', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('objet', 255);
            $table->foreignId('libelle_depense_id')->constrained('libelles_depense')->restrictOnDelete();
            $table->unsignedInteger('montant');
            $table->string('beneficiaire', 150);
            $table->string('rattachement', 20)->index();
            $table->foreignId('collecte_id')->nullable()->constrained('collectes')->restrictOnDelete();
            $table->foreignId('commission_comite_id')->nullable()->constrained('commissions_comite')->restrictOnDelete();
            $table->foreignId('projet_id')->nullable()->constrained('projets')->restrictOnDelete();
            $table->foreignId('cellule_id')->nullable()->constrained('cellules')->restrictOnDelete();
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->restrictOnDelete();
            $table->string('devis_path', 255)->nullable();
            $table->foreignId('demande_par')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('demande')->index();
            $table->timestamp('signe_le')->nullable();
            $table->foreignId('signe_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamp('execute_le')->nullable();
            $table->foreignId('execute_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('caisse_id')->nullable()->constrained('caisses')->restrictOnDelete();
            $table->string('preuve_path', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->foreignId('ordre_depense_id')->nullable()->after('participation_id')->constrained('ordres_depense')->restrictOnDelete();
        });

        $maintenant = now();

        foreach (self::LIBELLES as $ordre => $nom) {
            DB::table('libelles_depense')->insert(['nom' => $nom, 'ordre' => $ordre + 1, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        }
    }

    public function down(): void
    {
        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ordre_depense_id');
        });
        Schema::dropIfExists('ordres_depense');
        Schema::dropIfExists('libelles_depense');
    }
};
