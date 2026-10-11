<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Niveau d'études « Autre » : le membre précise son niveau en quelques mots. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membres', function (Blueprint $table): void {
            $table->string('niveau_etudes_autre', 100)->nullable()->after('niveau_etudes');
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $table): void {
            $table->dropColumn('niveau_etudes_autre');
        });
    }
};
