<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $table->unsignedSmallInteger('nombre_mois')->default(1);
        });

        // Un mois de cotisation payé (ou exonéré) par un membre.
        Schema::create('cotisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->cascadeOnDelete();
            $table->date('mois');
            $table->unsignedInteger('montant');
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
            $table->boolean('exoneree')->default(false);
            $table->string('motif', 255)->nullable();
            $table->timestamps();

            // Un même mois ne peut être payé qu'une fois.
            $table->unique(['membre_id', 'mois']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotisations');

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropColumn('nombre_mois');
        });
    }
};
