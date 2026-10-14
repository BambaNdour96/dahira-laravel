<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Le budget des commissions : leurs besoins (retenus ou écartés) et leurs dépenses (validées par le Jewrine finance). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('besoins_commission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions_comite')->cascadeOnDelete();
            $table->string('designation', 150);
            $table->unsignedInteger('quantite')->default(1);
            $table->unsignedInteger('prix_unitaire');
            $table->string('statut', 10)->default('propose');
            $table->string('motif', 255)->nullable();
            $table->foreignId('propose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('depenses_commission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions_comite')->cascadeOnDelete();
            $table->string('objet', 150);
            $table->unsignedInteger('montant');
            $table->date('date_depense');
            $table->string('fournisseur', 100)->nullable();
            $table->string('justificatif_path', 255)->nullable();
            $table->string('statut', 10)->default('declaree');
            $table->string('motif', 255)->nullable();
            $table->foreignId('declaree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traitee_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses_commission');
        Schema::dropIfExists('besoins_commission');
    }
};
