<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150);
            $table->text('description')->nullable();
            $table->string('lieu', 150);
            $table->timestamp('debut');
            $table->timestamp('fin')->nullable();
            // Vide : toute la Daara. Sinon : une seule cellule.
            $table->foreignId('cellule_id')->nullable()->constrained('cellules')->nullOnDelete();
            $table->string('affiche_path')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Les personnes autorisées à scanner les cartes à l'entrée de chaque événement.
        Schema::create('evenement_controleur', function (Blueprint $table) {
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['evenement_id', 'user_id']);
        });

        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->foreignId('enregistree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['evenement_id', 'membre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
        Schema::dropIfExists('evenement_controleur');
        Schema::dropIfExists('evenements');
    }
};
