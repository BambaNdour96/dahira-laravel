<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rapports rédigés par la commission Suivi et Évaluation (ou la direction), publiés au bureau. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports_suivi', function (Blueprint $table): void {
            $table->id();
            $table->string('titre', 150);
            $table->string('type', 15);
            $table->foreignId('projet_id')->nullable()->constrained('projets')->nullOnDelete();
            $table->foreignId('collecte_id')->nullable()->constrained('collectes')->nullOnDelete();
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->longText('contenu');
            $table->longText('recommandations')->nullable();
            $table->string('statut', 10)->default('brouillon')->index();
            $table->timestamp('publie_le')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_suivi');
    }
};
