<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Séances de dahira des cellules : Jewrine des femmes, pointeurs désignés avec leur périmètre,
 * et pointage détaillé (scan, ajout à la main, secours, absent excusé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evenements', function (Blueprint $table): void {
            $table->string('type', 15)->default('evenement')->index()->after('id');
            $table->boolean('avec_rappel')->default(true)->after('cellule_id');
        });

        Schema::table('cellules', function (Blueprint $table): void {
            $table->foreignId('jewrine_femmes_id')->nullable()->after('responsable_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('presences', function (Blueprint $table): void {
            $table->string('mode', 10)->default('scan');
            $table->string('statut', 10)->default('present')->index();
            $table->string('motif', 150)->nullable();
        });

        Schema::create('cellule_pointeurs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cellule_id')->constrained('cellules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('perimetre', 10);
            $table->timestamps();
            $table->unique(['cellule_id', 'user_id']);
        });

        Role::findOrCreate('jewrine_femmes', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cellule_pointeurs');

        Schema::table('presences', function (Blueprint $table): void {
            $table->dropColumn(['mode', 'statut', 'motif']);
        });

        Schema::table('cellules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('jewrine_femmes_id');
        });

        Schema::table('evenements', function (Blueprint $table): void {
            $table->dropColumn(['type', 'avec_rappel']);
        });
    }
};
