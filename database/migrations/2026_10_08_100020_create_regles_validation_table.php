<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pour chaque poste du bureau : les postes autorisés à valider ses paiements.
        Schema::create('regles_validation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poste_id')->unique()->constrained('postes')->cascadeOnDelete();
            $table->json('validateurs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regles_validation');
    }
};
