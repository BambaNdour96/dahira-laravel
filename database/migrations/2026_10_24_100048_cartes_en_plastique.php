<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R2c2 : les cartes en plastique. Le membre commande et paie sa carte (tarif « Carte physique ») ; les cartes payées
 * entrent dans des lots numérotés, envoyés à l'imprimeur, puis reçus et remis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots_cartes', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('cellule_id')->nullable()->constrained('cellules')->restrictOnDelete();
            $table->string('statut', 20)->default('prepare')->index();
            $table->foreignId('prepare_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamp('recu_le')->nullable();
            $table->timestamps();
        });

        Schema::create('cartes_physiques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->foreignId('paiement_id')->unique()->constrained('paiements')->restrictOnDelete();
            $table->string('statut', 20)->default('payee')->index();
            $table->foreignId('lot_id')->nullable()->constrained('lots_cartes')->restrictOnDelete();
            $table->foreignId('carte_id')->nullable()->constrained('cartes')->restrictOnDelete();
            $table->timestamp('remise_le')->nullable();
            $table->foreignId('remise_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartes_physiques');
        Schema::dropIfExists('lots_cartes');
    }
};
