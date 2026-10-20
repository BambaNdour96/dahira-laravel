<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R3d : les réaffectations de fonds (décidées par le Jewrine national, enregistrées par la Finance, avec leur origine),
 * et la clôture des exercices (préparée par le Jewrine finance, confirmée par le Jewrine national), avec le bilan figé.
 * Les réaffectations de paiements et les avoirs (R3c) s'inscrivent désormais au journal des caisses, par un couple de
 * mouvements qui ne change pas le solde : l'existant est repris ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reaffectations_fonds', function (Blueprint $table): void {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->unsignedInteger('montant');
            $table->string('motif', 255);
            $table->string('origine', 20);
            $table->foreignId('origine_collecte_id')->nullable()->constrained('collectes')->restrictOnDelete();
            $table->foreignId('origine_projet_id')->nullable()->constrained('projets')->restrictOnDelete();
            $table->string('destination', 20);
            $table->foreignId('destination_collecte_id')->nullable()->constrained('collectes')->restrictOnDelete();
            $table->foreignId('destination_projet_id')->nullable()->constrained('projets')->restrictOnDelete();
            $table->string('statut', 20)->default('decidee')->index();
            $table->foreignId('decidee_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('enregistree_le')->nullable();
            $table->foreignId('enregistree_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('exercices', function (Blueprint $table): void {
            $table->timestamp('cloture_preparee_le')->nullable();
            $table->foreignId('cloture_preparee_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cloture_le')->nullable();
            $table->foreignId('cloture_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->longText('bilan')->nullable();
        });

        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->foreignId('avoir_id')->nullable()->after('ordre_depense_id')->constrained('avoirs')->restrictOnDelete();
        });

        $this->reprendreLesCorrections();
    }

    /** Les réaffectations et les avoirs déjà faits reçoivent leur couple de mouvements, à leur date. */
    private function reprendreLesCorrections(): void
    {
        $generale = DB::table('caisses')->where('type', 'especes')->orderBy('id')->value('id');
        $caisseDe = fn (?int $paiementId): ?int => ($paiementId !== null ? DB::table('paiements')->where('id', $paiementId)->value('caisse_id') : null) ?? $generale;
        $couple = function (?int $caisse, int $montant, array $depuis, array $vers, string $libelle, ?int $auteur, string $date): void {
            if ($caisse === null) {
                return;
            }
            foreach ([['sortie', $depuis], ['entree', $vers]] as [$sens, $cible]) {
                DB::table('mouvements_caisse')->insert([
                    'caisse_id' => $caisse, 'sens' => $sens, 'montant' => $montant, 'nature' => 'reaffectation', 'libelle' => $libelle,
                    'auteur_id' => $auteur, 'date_operation' => $date, 'created_at' => $date, 'updated_at' => $date, ...$cible,
                ]);
            }
        };

        foreach (DB::table('corrections_paiement')->where('type', 'reaffectation')->whereNotNull('nouveau_paiement_id')->get() as $correction) {
            $couple($caisseDe((int) $correction->paiement_id), (int) $correction->montant, ['paiement_id' => $correction->paiement_id],
                ['paiement_id' => $correction->nouveau_paiement_id], 'Réaffectation d\'un paiement', (int) $correction->auteur_id, (string) $correction->created_at);
        }

        foreach (DB::table('avoirs')->get() as $avoir) {
            $origine = $avoir->paiement_origine_id !== null ? (int) $avoir->paiement_origine_id : null;
            $couple($caisseDe($origine), (int) $avoir->montant, ['paiement_id' => $origine], ['avoir_id' => $avoir->id],
                'Paiement mis en avoir', (int) $avoir->auteur_id, (string) $avoir->created_at);

            foreach (DB::table('paiements')->where('avoir_id', $avoir->id)->where('moyen', 'avoir')->get() as $paiement) {
                $couple($caisseDe($origine), (int) $paiement->montant, ['avoir_id' => $avoir->id], ['paiement_id' => $paiement->id],
                    'Imputation d\'un avoir', $paiement->traite_par !== null ? (int) $paiement->traite_par : null, (string) $paiement->created_at);
            }
        }
    }

    public function down(): void
    {
        DB::table('mouvements_caisse')->where('nature', 'reaffectation')->where(fn ($query) => $query->whereNotNull('paiement_id')->orWhereNotNull('avoir_id'))->delete();
        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('avoir_id');
        });
        Schema::table('exercices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cloture_preparee_par');
            $table->dropConstrainedForeignId('cloture_par');
            $table->dropColumn(['cloture_preparee_le', 'cloture_le', 'bilan']);
        });
        Schema::dropIfExists('reaffectations_fonds');
    }
};
