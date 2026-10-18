<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R2a : les familles. Un membre (le responsable) gère les fiches de ses proches : enfants sans compte de connexion,
 * ou adultes sans téléphone. Le Jewrine de cellule peut inscrire lui-même un membre sans téléphone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membres', function (Blueprint $table): void {
            $table->string('telephone', 20)->nullable()->change();
            $table->foreignId('responsable_id')->nullable()->after('user_id')->constrained('membres')->restrictOnDelete();
            $table->string('lien_famille', 20)->nullable()->after('responsable_id');
            $table->foreignId('inscrit_par')->nullable()->after('lien_famille')->constrained('users')->restrictOnDelete();
            $table->timestamp('compte_propose_le')->nullable()->after('inscrit_par');
        });

        Schema::table('paiements', function (Blueprint $table): void {
            $table->foreignId('declare_par')->nullable()->after('declare_le')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('declare_par');
        });

        Schema::table('membres', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsable_id');
            $table->dropConstrainedForeignId('inscrit_par');
            $table->dropColumn(['lien_famille', 'compte_propose_le']);
            $table->string('telephone', 20)->nullable(false)->change();
        });
    }
};
