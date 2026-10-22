<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R5b : le circuit de l'argent d'un comité d'organisation. Un ordre de dépense est un paiement direct, une avance (le
 * chef reçoit l'argent avant d'acheter) ou un remboursement (le chef a payé de sa poche). Le chef justifie son avance
 * avec les preuves et rend le reste, que la Finance encaisse ; l'avance est alors soldée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordres_depense', function (Blueprint $table): void {
            $table->string('nature', 20)->default('paiement')->after('rattachement');
            $table->unsignedInteger('montant_rendu')->default(0)->after('montant');
            $table->timestamp('soldee_le')->nullable()->after('execute_par');
        });

        Schema::create('justificatifs_depense', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ordre_depense_id')->constrained('ordres_depense')->restrictOnDelete();
            $table->string('objet', 255);
            $table->unsignedInteger('montant');
            $table->string('piece_path');
            $table->foreignId('ajoute_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificatifs_depense');
        Schema::table('ordres_depense', function (Blueprint $table): void {
            $table->dropColumn(['nature', 'montant_rendu', 'soldee_le']);
        });
    }
};
