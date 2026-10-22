<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R5c : la clôture d'un comité d'organisation. Chaque chef rend le bilan de sa commission (chiffres figés, objectifs
 * atteints ou non, forces, faiblesses, améliorations), le président le bilan général, Suivi et Évaluation son
 * évaluation ; le président choisit la réunion de bilan ; le comité se clôture quand tous les bilans sont rendus et
 * toutes les avances justifiées, puis reste en archive, en lecture seule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bilans_comite', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances')->restrictOnDelete();
            $table->foreignId('commission_comite_id')->nullable()->constrained('commissions_comite')->restrictOnDelete();
            $table->string('type', 20)->index();
            $table->string('appreciation', 20)->nullable();
            $table->text('forces')->nullable();
            $table->text('faiblesses')->nullable();
            $table->text('ameliorations')->nullable();
            $table->json('chiffres')->nullable();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('instances', function (Blueprint $table): void {
            $table->foreignId('reunion_bilan_id')->nullable()->after('suivi_depenses')->constrained('reunions')->restrictOnDelete();
            $table->timestamp('cloture_le')->nullable()->after('reunion_bilan_id');
            $table->foreignId('cloture_par')->nullable()->after('cloture_le')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reunion_bilan_id');
            $table->dropConstrainedForeignId('cloture_par');
            $table->dropColumn('cloture_le');
        });
        Schema::dropIfExists('bilans_comite');
    }
};
