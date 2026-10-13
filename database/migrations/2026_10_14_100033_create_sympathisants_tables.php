<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Les sympathisants : registre léger, visites aux rendez-vous, participations volontaires, visiteurs anonymes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sympathisants', function (Blueprint $table): void {
            $table->id();
            $table->string('prenom', 80);
            $table->string('nom', 80);
            $table->string('sexe', 1);
            $table->foreignId('cellule_id')->constrained('cellules');
            $table->string('telephone', 20)->nullable()->index();
            $table->foreignId('invite_par_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->foreignId('membre_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->timestamp('consentement_le');
            $table->timestamp('derniere_visite_le')->nullable()->index();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('visites_sympathisants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();
            $table->foreignId('sympathisant_id')->constrained('sympathisants')->cascadeOnDelete();
            $table->foreignId('enregistree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['evenement_id', 'sympathisant_id']);
        });

        Schema::create('participations_sympathisants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collecte_id')->constrained('collectes')->cascadeOnDelete();
            $table->foreignId('sympathisant_id')->constrained('sympathisants')->cascadeOnDelete();
            $table->unsignedInteger('montant');
            $table->string('moyen', 10);
            $table->string('numero_recu', 20)->unique();
            $table->foreignId('enregistre_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::table('evenements', function (Blueprint $table): void {
            $table->unsignedInteger('visiteurs')->default(0)->after('avec_rappel');
        });
    }

    public function down(): void
    {
        Schema::table('evenements', function (Blueprint $table): void {
            $table->dropColumn('visiteurs');
        });

        Schema::dropIfExists('participations_sympathisants');
        Schema::dropIfExists('visites_sympathisants');
        Schema::dropIfExists('sympathisants');
    }
};
