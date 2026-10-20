<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R2e : la rubrique « Arabe et Coran », dans une table à part (informations confidentielles : le membre, la commission
 * Culturelle et le super administrateur). Elle remplace le champ « niveau d'arabe », dont les données sont reprises.
 */
return new class extends Migration
{
    /** Ancien niveau => [lecture et écriture, compréhension]. */
    private const REPRISE = [
        'aucun' => ['ne_sait_pas', 'ne_comprend_pas'],
        'debutant' => ['difficulte', 'quelques_mots'],
        'intermediaire' => ['couramment', 'essentiel'],
        'avance' => ['excellent', 'maitrise'],
    ];

    public function up(): void
    {
        Schema::create('profils_arabe_coran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membre_id')->unique()->constrained('membres')->restrictOnDelete();
            $table->string('lecture', 20)->nullable();
            $table->string('ecriture', 20)->nullable();
            $table->string('comprehension', 20)->nullable();
            $table->string('coran', 20)->nullable();
            $table->unsignedTinyInteger('nombre_hizb')->nullable();
            $table->string('khassaides', 20)->nullable();
            $table->boolean('souhaite_apprendre')->nullable();
            $table->foreignId('renseigne_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // La commission Culturelle se reconnaît à son code, comme Suivi et Évaluation.
        DB::table('commissions')->whereIn('nom', ['Culturelle', 'Culturelle et religieuse'])->whereNull('code')->update(['code' => 'culturelle']);

        $maintenant = now();

        foreach (DB::table('membres')->whereNotNull('niveau_arabe')->select(['id', 'niveau_arabe'])->orderBy('id')->cursor() as $membre) {
            [$lecture, $comprehension] = self::REPRISE[$membre->niveau_arabe] ?? [null, null];

            if ($lecture !== null) {
                DB::table('profils_arabe_coran')->insert([
                    'membre_id' => $membre->id, 'lecture' => $lecture, 'ecriture' => $lecture, 'comprehension' => $comprehension,
                    'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
            }
        }

        Schema::table('membres', function (Blueprint $table): void {
            $table->dropColumn('niveau_arabe');
        });
    }

    public function down(): void
    {
        Schema::table('membres', function (Blueprint $table): void {
            $table->string('niveau_arabe', 20)->nullable();
        });

        foreach (DB::table('profils_arabe_coran')->whereNotNull('lecture')->cursor() as $profil) {
            $niveau = array_search($profil->lecture, array_map(fn (array $valeurs): string => $valeurs[0], self::REPRISE), true);
            DB::table('membres')->where('id', $profil->membre_id)->update(['niveau_arabe' => $niveau !== false ? $niveau : null]);
        }

        Schema::dropIfExists('profils_arabe_coran');
    }
};
