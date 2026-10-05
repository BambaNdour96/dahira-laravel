<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cellules', function (Blueprint $table) {
            $table->string('whatsapp_lien', 255)->nullable();
        });

        // Réglages généraux de la Daara, modifiables par l'administrateur.
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 100)->unique();
            $table->string('libelle', 150);
            $table->text('valeur')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');

        Schema::table('cellules', function (Blueprint $table) {
            $table->dropColumn('whatsapp_lien');
        });
    }
};
