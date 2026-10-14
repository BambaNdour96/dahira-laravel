<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\DemandeSuppression;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Effacement des données personnelles (loi n° 2008-12).
 * La fiche n'est pas supprimée, pour garder la comptabilité intacte :
 * tout ce qui identifie la personne est effacé, le reste est conservé sans nom.
 */
final class DonneesPersonnellesService
{
    /** Colonnes conservées : elles n'identifient pas la personne et servent aux statistiques. */
    private const CONSERVEES = [
        'id', 'matricule', 'statut', 'sexe', 'date_adhesion', 'valide_le', 'created_at', 'updated_at', 'anonymise_le',
    ];

    public function traiterDemande(DemandeSuppression $demande, ?User $auteur): void
    {
        DB::transaction(function () use ($demande, $auteur): void {
            if ($demande->membre !== null) {
                $this->anonymiser($demande->membre);
            } elseif ($demande->user !== null) {
                $this->anonymiserCompte($demande->user);
            }

            $demande->update([
                'statut' => DemandeSuppression::TRAITEE,
                'traitee_par' => $auteur?->id,
                'traitee_le' => now(),
            ]);
        });

        JournalActivite::enregistrer($demande, 'suppression_faite');
    }

    public function anonymiser(Membre $membre): void
    {
        if ($membre->getAttribute('anonymise_le') !== null) {
            return;
        }

        $photo = $membre->photo_path;
        $compte = $membre->user;

        DB::transaction(function () use ($membre, $compte): void {
            $valeurs = [];

            foreach (Schema::getColumns('membres') as $colonne) {
                $nom = (string) $colonne['name'];

                if (in_array($nom, self::CONSERVEES, true) || str_ends_with($nom, '_id') || str_ends_with($nom, '_par')) {
                    continue;
                }

                if ($colonne['nullable']) {
                    $valeurs[$nom] = null;
                } elseif (preg_match('/char|text|string/i', (string) $colonne['type_name']) === 1) {
                    $valeurs[$nom] = match ($nom) {
                        'prenom' => 'Membre',
                        'nom' => 'anonymisé',
                        default => 'anonyme-'.$membre->id,
                    };
                }
            }

            $valeurs['statut'] = StatutMembre::Radie->value;
            $valeurs['anonymise_le'] = now();
            $valeurs['updated_at'] = now();

            DB::table('membres')->where('id', $membre->id)->update($valeurs);
            app(CommissionService::class)->terminerToutes($membre, 'Données anonymisées');

            // La carte devient invalide : son QR affichera « Carte non valide ».
            DB::table('cartes')->where('membre_id', $membre->id)->where('statut', 'active')->update([
                'statut' => 'revoquee',
                'revoquee_le' => now(),
                'motif_revocation' => 'Données personnelles effacées',
            ]);

            // Les paiements restent, sans le numéro Wave.
            DB::table('paiements')->where('membre_id', $membre->id)->update(['numero_wave' => null]);

            // Le journal garde qui a fait quoi et quand, mais plus le contenu des fiches.
            DB::table('journal_activites')
                ->where('sujet_type', $membre->getMorphClass())
                ->where('sujet_id', $membre->id)
                ->update(['changements' => null]);

            if ($compte !== null) {
                $this->anonymiserCompte($compte);
            }
        });

        if (is_string($photo) && $photo !== '') {
            Storage::disk('local')->delete($photo);
        }
    }

    public function anonymiserCompte(User $compte): void
    {
        $ancienTelephone = $compte->telephone;

        app(NominationService::class)->terminerToutes($compte, 'Compte anonymisé');
        $compte->syncRoles([]);
        $compte->forceFill([
            'name' => 'Compte supprimé',
            'telephone' => 'anonyme-u'.$compte->id,
            'email' => null,
            'password' => Str::random(40),
            'actif' => false,
            'doit_changer_mot_de_passe' => false,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            'remember_token' => null,
        ])->save();

        DB::table('connexions')
            ->where('user_id', $compte->id)
            ->orWhere('telephone', $ancienTelephone)
            ->delete();

        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $compte->id)->delete();
        }

        DB::table('journal_activites')
            ->where('sujet_type', $compte->getMorphClass())
            ->where('sujet_id', $compte->id)
            ->update(['changements' => null]);
    }
}
