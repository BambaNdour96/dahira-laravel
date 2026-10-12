<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Projets de la Daara, suivis par la commission Suivi et Évaluation :
 * étapes, indicateurs, journal (points, risques, décisions, dépenses) et évaluation finale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projets', function (Blueprint $table): void {
            $table->id();
            $table->string('titre', 150);
            $table->text('objectif');
            $table->text('description')->nullable();
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedBigInteger('budget')->nullable();
            $table->foreignId('collecte_id')->nullable()->constrained('collectes')->nullOnDelete();
            $table->string('statut', 15)->default('en_cours')->index();
            $table->string('resultat', 15)->nullable();
            $table->text('bilan')->nullable();
            $table->text('lecons')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('jalons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets')->cascadeOnDelete();
            $table->string('titre', 150);
            $table->date('echeance');
            $table->date('realise_le')->nullable();
            $table->timestamps();
        });

        Schema::create('indicateurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets')->cascadeOnDelete();
            $table->string('libelle', 150);
            $table->string('unite', 30)->nullable();
            $table->decimal('cible', 14, 2);
            $table->decimal('valeur', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('suivis_projet', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projet_id')->constrained('projets')->cascadeOnDelete();
            $table->string('type', 15);
            $table->text('texte');
            $table->unsignedBigInteger('montant')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Role::findOrCreate('suivi_evaluation', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('suivis_projet');
        Schema::dropIfExists('indicateurs');
        Schema::dropIfExists('jalons');
        Schema::dropIfExists('projets');
    }
};
