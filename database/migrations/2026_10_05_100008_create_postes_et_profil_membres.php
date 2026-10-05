<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('membres', function (Blueprint $table) {
            $table->foreignId('poste_id')->nullable()->constrained('postes')->nullOnDelete();
            $table->string('adresse', 255)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('pays_residence', 100)->default('Sénégal');
            $table->string('niveau_etudes', 20)->nullable();
            $table->string('diplome', 150)->nullable();
            $table->string('domaine_etudes', 150)->nullable();
            $table->json('langues')->nullable();
            $table->string('niveau_arabe', 20)->nullable();
            $table->string('situation_professionnelle', 20)->nullable();
            $table->string('profession', 150)->nullable();
            $table->string('secteur_activite', 150)->nullable();
            $table->dropColumn('fonction');
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $table) {
            $table->string('fonction', 100)->nullable();
            $table->dropConstrainedForeignId('poste_id');
            $table->dropColumn([
                'adresse', 'ville', 'pays_residence', 'niveau_etudes', 'diplome', 'domaine_etudes',
                'langues', 'niveau_arabe', 'situation_professionnelle', 'profession', 'secteur_activite',
            ]);
        });

        Schema::dropIfExists('postes');
    }
};
