<?php

use App\Support\Capacites;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot R1b : les décisions se prennent sans vote (unanimité, ou tranchées par le Jewrine national),
 * et le quorum n'est plus qu'une information (présents sur membres). Les votes déjà enregistrés
 * sont recopiés dans le texte de chaque décision avant que leurs colonnes disparaissent.
 * La matrice des droits est mise à jour (convocation du Comité directeur, Jewrine de commission).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decisions', function (Blueprint $table): void {
            $table->string('issue', 20)->default('unanimite');
        });

        $votees = DB::table('decisions')
            ->where(fn ($query) => $query->whereNotNull('pour')->orWhereNotNull('contre')->orWhereNotNull('abstention'))
            ->get(['id', 'texte', 'pour', 'contre', 'abstention']);

        foreach ($votees as $decision) {
            DB::table('decisions')->where('id', $decision->id)->update([
                'texte' => $decision->texte.sprintf(
                    "\n(Vote enregistré : %d pour, %d contre, %d abstention.)",
                    (int) $decision->pour, (int) $decision->contre, (int) $decision->abstention,
                ),
            ]);
        }

        Schema::table('decisions', function (Blueprint $table): void {
            $table->dropColumn(['pour', 'contre', 'abstention']);
        });

        Schema::table('instances', function (Blueprint $table): void {
            $table->dropColumn('quorum');
        });

        Capacites::synchroniser();
    }

    /** Les colonnes reviennent, vides : les votes restent lisibles dans le texte des décisions. */
    public function down(): void
    {
        Schema::table('instances', function (Blueprint $table): void {
            $table->unsignedTinyInteger('quorum')->default(50);
        });

        Schema::table('decisions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pour')->nullable();
            $table->unsignedSmallInteger('contre')->nullable();
            $table->unsignedSmallInteger('abstention')->nullable();
            $table->dropColumn('issue');
        });
    }
};
