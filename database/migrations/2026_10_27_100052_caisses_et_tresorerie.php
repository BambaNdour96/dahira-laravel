<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R3a : les caisses (Wave, Orange Money, caisse générale, caisse de chaque représentant ; banque plus tard) et le journal
 * de leurs mouvements, qui fait la trésorerie. Chaque paiement validé entre dans une caisse ; la remise d'un représentant
 * fait passer ses espèces dans la caisse générale. Les paiements et remises déjà enregistrés sont repris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caisses', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->string('type', 20)->index();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamp('archive_le')->nullable();
            $table->foreignId('archive_par')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('mouvements_caisse', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('caisse_id')->constrained('caisses')->restrictOnDelete();
            $table->string('sens', 10);
            $table->unsignedInteger('montant');
            $table->string('nature', 20)->index();
            $table->string('libelle', 255);
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->restrictOnDelete();
            $table->foreignId('remise_id')->nullable()->constrained('remises_fonds')->restrictOnDelete();
            $table->foreignId('participation_id')->nullable()->constrained('participations_sympathisants')->restrictOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('date_operation')->index();
            $table->timestamps();
            $table->index(['caisse_id', 'date_operation']);
        });

        Schema::table('paiements', function (Blueprint $table): void {
            $table->foreignId('caisse_id')->nullable()->after('moyen')->constrained('caisses')->restrictOnDelete();
        });

        $maintenant = now();
        $caisse = fn (string $nom, string $type, int $ordre, ?int $userId = null): int => (int) DB::table('caisses')->insertGetId([
            'nom' => $nom, 'type' => $type, 'user_id' => $userId, 'ordre' => $ordre, 'created_at' => $maintenant, 'updated_at' => $maintenant,
        ]);

        $wave = $caisse('Wave', 'wave', 1);
        $caisse('Orange Money', 'orange_money', 2);
        $generale = $caisse('Caisse générale', 'especes', 3);

        // Une caisse par représentant (désignation en cours ou passée).
        $representants = [];
        foreach (DB::table('representants_finance')->join('users', 'users.id', '=', 'representants_finance.user_id')->join('cellules', 'cellules.id', '=', 'representants_finance.cellule_id')
            ->select(['representants_finance.user_id', 'users.name', 'cellules.nom as cellule'])->orderBy('representants_finance.id')->get() as $representant) {
            $representants[$representant->user_id] ??= $caisse('Caisse de '.$representant->name.' ('.$representant->cellule.')', 'representant', 10, (int) $representant->user_id);
        }

        // Chaque paiement validé entre dans sa caisse.
        foreach (DB::table('paiements')->where('statut', 'confirme')->orderBy('id')->cursor() as $paiement) {
            $vers = match (true) {
                $paiement->moyen === 'wave' => $wave,
                isset($representants[$paiement->traite_par]) => $representants[$paiement->traite_par],
                default => $generale,
            };

            DB::table('paiements')->where('id', $paiement->id)->update(['caisse_id' => $vers]);
            DB::table('mouvements_caisse')->insert([
                'caisse_id' => $vers, 'sens' => 'entree', 'montant' => $paiement->montant, 'nature' => 'encaissement',
                'libelle' => 'Paiement '.($paiement->numero_recu ?? '#'.$paiement->id), 'paiement_id' => $paiement->id, 'auteur_id' => $paiement->traite_par,
                'date_operation' => $paiement->traite_le ?? $paiement->declare_le, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }

        // Les participations des sympathisants, enregistrées par la Finance.
        foreach (DB::table('participations_sympathisants')->orderBy('id')->cursor() as $participation) {
            DB::table('mouvements_caisse')->insert([
                'caisse_id' => $participation->moyen === 'wave' ? $wave : $generale, 'sens' => 'entree', 'montant' => $participation->montant,
                'nature' => 'encaissement', 'libelle' => 'Participation '.$participation->numero_recu, 'participation_id' => $participation->id,
                'auteur_id' => $participation->enregistre_par, 'date_operation' => $participation->created_at, 'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
        }

        // Chaque remise : les espèces du représentant passent dans la caisse générale.
        foreach (DB::table('remises_fonds')->orderBy('id')->cursor() as $remise) {
            $depuis = $representants[$remise->representant_id] ?? null;

            if ($depuis === null) {
                continue;
            }

            foreach ([[$depuis, 'sortie'], [$generale, 'entree']] as [$caisseId, $sens]) {
                DB::table('mouvements_caisse')->insert([
                    'caisse_id' => $caisseId, 'sens' => $sens, 'montant' => $remise->montant, 'nature' => 'remise', 'libelle' => 'Remise '.$remise->numero,
                    'remise_id' => $remise->id, 'auteur_id' => $remise->recue_par, 'date_operation' => $remise->created_at, 'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('caisse_id');
        });
        Schema::dropIfExists('mouvements_caisse');
        Schema::dropIfExists('caisses');
    }
};
