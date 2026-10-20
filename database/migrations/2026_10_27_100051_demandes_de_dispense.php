<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** R3e : la dispense de cotisation est demandée par le Jewrine de cellule et validée par le Jewrine national. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_dispense', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->unsignedTinyInteger('nombre_mois');
            $table->string('motif', 255);
            $table->foreignId('demandee_par')->constrained('users')->restrictOnDelete();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->timestamp('decide_le')->nullable();
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('motif_refus', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_dispense');
    }
};
