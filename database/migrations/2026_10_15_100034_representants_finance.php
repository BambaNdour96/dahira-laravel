<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Représentants du Jewrine finance dans les cellules : un Goor Yalla et une Sokhna par cellule,
 * leur caisse d'espèces, et les remises de fonds au Jewrine finance national.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('representants_finance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cellule_id')->constrained('cellules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('perimetre', 10);
            $table->foreignId('designe_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cellule_id', 'perimetre']);
        });

        Schema::create('remises_fonds', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('cellule_id')->constrained('cellules');
            $table->foreignId('representant_id')->constrained('users');
            $table->foreignId('recue_par')->constrained('users');
            $table->unsignedInteger('montant');
            $table->unsignedInteger('nombre_operations');
            $table->timestamp('created_at');
        });

        Schema::table('paiements', function (Blueprint $table): void {
            $table->foreignId('remise_id')->nullable()->constrained('remises_fonds')->nullOnDelete();
        });

        Role::findOrCreate('representant_finance', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('remise_id');
        });

        Schema::dropIfExists('remises_fonds');
        Schema::dropIfExists('representants_finance');
    }
};
