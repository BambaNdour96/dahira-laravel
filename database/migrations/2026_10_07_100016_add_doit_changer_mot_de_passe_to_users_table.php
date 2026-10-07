<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vrai après un mot de passe provisoire : la personne doit en choisir un nouveau.
            $table->boolean('doit_changer_mot_de_passe')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('doit_changer_mot_de_passe');
        });
    }
};
