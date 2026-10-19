<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R2d : la reprise des listes existantes. Un import s'analyse d'abord (rapport ligne par ligne), puis se confirme ;
 * chaque membre importé garde son origine. Les membres importés activent eux-mêmes leur compte, validé par le Jewrine
 * quand ils ne connaissent pas leur matricule. Les mois déjà réglés avant la plateforme sont notés « Reprise des données ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_membres', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auteur_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cellule_id')->nullable()->constrained('cellules')->restrictOnDelete();
            $table->string('fichier_nom', 255);
            $table->string('chemin', 255);
            $table->string('statut', 20)->default('analyse')->index();
            $table->unsignedInteger('lignes_total')->default(0);
            $table->unsignedInteger('lignes_pretes')->default(0);
            $table->unsignedInteger('lignes_importees')->default(0);
            $table->longText('rapport')->nullable();
            $table->timestamp('importe_le')->nullable();
            $table->timestamps();
        });

        Schema::table('membres', function (Blueprint $table): void {
            $table->string('origine', 20)->nullable()->after('inscrit_par');
            $table->foreignId('import_id')->nullable()->after('origine')->constrained('imports_membres')->restrictOnDelete();
        });

        Schema::table('cotisations', function (Blueprint $table): void {
            $table->boolean('reprise')->default(false)->after('exoneree');
        });

        Schema::create('activations_compte', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membre_id')->constrained('membres')->restrictOnDelete();
            $table->string('telephone', 20);
            $table->string('mot_de_passe', 255);
            $table->string('statut', 20)->default('en_attente')->index();
            $table->timestamp('decide_le')->nullable();
            $table->foreignId('decide_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activations_compte');
        Schema::table('cotisations', function (Blueprint $table): void {
            $table->dropColumn('reprise');
        });
        Schema::table('membres', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('import_id');
            $table->dropColumn('origine');
        });
        Schema::dropIfExists('imports_membres');
    }
};
