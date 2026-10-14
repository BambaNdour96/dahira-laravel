<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le canal « notification » (12 caractères) ne tenait pas dans 10 caractères :
 * sous PostgreSQL, l'enregistrement du rappel échouait et le rappel n'était pas tracé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rappels', function (Blueprint $table): void {
            $table->string('canal', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rappels', function (Blueprint $table): void {
            $table->string('canal', 10)->change();
        });
    }
};
