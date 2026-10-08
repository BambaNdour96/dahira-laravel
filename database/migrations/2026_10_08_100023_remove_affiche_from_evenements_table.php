<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Les événements n'ont plus d'affiche : titre, date, lieu et description seulement.
        Schema::table('evenements', function (Blueprint $table) {
            $table->dropColumn('affiche_path');
        });
    }

    public function down(): void
    {
        Schema::table('evenements', function (Blueprint $table) {
            $table->string('affiche_path')->nullable();
        });
    }
};
