<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('matricule', 20)->nullable()->unique();
            $table->string('prenom', 100);
            $table->string('nom', 100);
            $table->string('sexe', 1);
            $table->date('date_naissance')->nullable();
            $table->string('telephone', 20)->unique();
            $table->string('email')->nullable();
            $table->foreignId('cellule_id')->constrained('cellules')->restrictOnDelete();
            $table->string('photo_path')->nullable();
            $table->string('fonction', 100)->nullable();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->text('motif_statut')->nullable();
            $table->date('date_adhesion')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consentement_le')->nullable();
            $table->string('consentement_version', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membres');
    }
};
