<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->timestamp('accepte_le')->nullable();
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->string('objet', 30)->default('frais_carte');
            $table->unsignedInteger('montant');
            $table->string('moyen', 20);
            // Une référence Wave ne peut servir qu'une seule fois.
            $table->string('reference', 60)->nullable()->unique();
            $table->string('statut', 20)->default('declare')->index();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamp('declare_le');
            $table->timestamp('traite_le')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');

        Schema::table('membres', function (Blueprint $table) {
            $table->dropColumn('accepte_le');
        });
    }
};
