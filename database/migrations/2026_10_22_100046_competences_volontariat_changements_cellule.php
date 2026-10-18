<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R2b : les savoir-faire des membres, le volontariat dans les commissions d'événement, et le changement de cellule
 * (demandé, validé par le super administrateur, avec l'historique des cellules). Chaque paiement garde la cellule du
 * moment où il a été fait.
 */
return new class extends Migration
{
    /** La liste de départ ; le super administrateur la complète dans Paramètres. */
    private const COMPETENCES = [
        'Cuisine', 'Sonorisation', 'Chauffeur', 'Santé et secourisme', 'Maçonnerie et bâtiment', 'Électricité',
        'Couture', 'Photo et vidéo', 'Informatique', 'Comptabilité', 'Khassaides et animation', 'Logistique et montage',
        'Accueil et sécurité', 'Communication et réseaux sociaux', 'Enseignement',
    ];

    public function up(): void
    {
        Schema::create('competences', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('archive_le')->nullable();
            $table->foreignId('archive_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('competence_membre', function (Blueprint $table): void {
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->foreignId('competence_id')->constrained('competences')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['membre_id', 'competence_id']);
        });

        Schema::create('volontariats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_comite_id')->constrained('commissions_comite')->restrictOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->string('statut', 20)->index();
            $table->string('message', 255)->nullable();
            $table->foreignId('invite_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decide_le')->nullable();
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('changements_cellule', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->foreignId('cellule_depart_id')->constrained('cellules')->restrictOnDelete();
            $table->foreignId('cellule_arrivee_id')->constrained('cellules')->restrictOnDelete();
            $table->string('motif', 255)->nullable();
            $table->foreignId('demande_par')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->timestamp('decide_le')->nullable();
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('historique_cellules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->foreignId('cellule_id')->constrained('cellules')->restrictOnDelete();
            $table->date('debut_le');
            $table->date('fin_le')->nullable();
            $table->foreignId('changement_id')->nullable()->constrained('changements_cellule')->restrictOnDelete();
            $table->timestamps();
            $table->index(['membre_id', 'fin_le']);
        });

        Schema::table('paiements', function (Blueprint $table): void {
            $table->foreignId('cellule_id')->nullable()->after('membre_id')->constrained('cellules')->restrictOnDelete();
        });

        $maintenant = now();

        foreach (self::COMPETENCES as $ordre => $nom) {
            DB::table('competences')->insert(['nom' => $nom, 'ordre' => $ordre + 1, 'created_at' => $maintenant, 'updated_at' => $maintenant]);
        }

        // Les paiements déjà faits gardent la cellule actuelle du membre ; chaque membre ouvre son historique.
        DB::table('paiements')->whereNull('cellule_id')->update([
            'cellule_id' => DB::raw('(select membres.cellule_id from membres where membres.id = paiements.membre_id)'),
        ]);

        foreach (DB::table('membres')->select(['id', 'cellule_id', 'date_adhesion', 'created_at'])->orderBy('id')->cursor() as $membre) {
            DB::table('historique_cellules')->insert([
                'membre_id' => $membre->id,
                'cellule_id' => $membre->cellule_id,
                'debut_le' => substr((string) ($membre->date_adhesion ?? $membre->created_at ?? $maintenant), 0, 10),
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cellule_id');
        });
        Schema::dropIfExists('historique_cellules');
        Schema::dropIfExists('changements_cellule');
        Schema::dropIfExists('volontariats');
        Schema::dropIfExists('competence_membre');
        Schema::dropIfExists('competences');
    }
};
