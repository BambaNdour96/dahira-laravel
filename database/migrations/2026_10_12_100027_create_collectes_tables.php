<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collectes de la Daara (Magal, Ziar, Prokhane, programmes secondaires, projets),
 * engagements des membres, et historique de chaque modification d'engagement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collectes', function (Blueprint $table): void {
            $table->id();
            $table->string('titre', 150);
            $table->string('programme', 30);
            $table->string('mode', 20);
            $table->unsignedInteger('montant')->nullable();
            $table->unsignedBigInteger('objectif')->nullable();
            $table->date('ouverture');
            $table->date('date_limite')->nullable();
            $table->boolean('obligatoire')->default(true);
            $table->boolean('versement_apres_limite')->default(true);
            $table->boolean('visible_membres')->default(false);
            $table->date('jauge_visible_depuis')->nullable();
            $table->string('statut', 15)->default('ouverte')->index();
            $table->foreignId('cellule_id')->nullable()->constrained('cellules')->nullOnDelete();
            $table->foreignId('evenement_id')->nullable()->constrained('evenements')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('engagements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collecte_id')->constrained('collectes')->cascadeOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->unsignedInteger('montant');
            $table->string('fixe_par', 10);
            $table->timestamps();
            $table->unique(['collecte_id', 'membre_id']);
        });

        Schema::create('historique_engagements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->unsignedInteger('ancien')->nullable();
            $table->unsignedInteger('nouveau');
            $table->string('motif', 255);
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('paiements', function (Blueprint $table): void {
            $table->foreignId('collecte_id')->nullable()->after('membre_id')->constrained('collectes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collecte_id');
        });

        Schema::dropIfExists('historique_engagements');
        Schema::dropIfExists('engagements');
        Schema::dropIfExists('collectes');
    }
};
