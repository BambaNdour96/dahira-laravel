<?php

namespace App\Services;

use App\Models\Membre;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Crée le compte et la fiche du membre au statut « en attente ».
 */
final class InscriptionService
{
    /** @param  array<string, mixed>  $donnees */
    public function inscrire(array $donnees, UploadedFile $photo): User
    {
        $chemin = $photo->store('photos', 'local');

        if ($chemin === false) {
            throw new RuntimeException('Impossible d\'enregistrer la photo.');
        }

        try {
            return DB::transaction(function () use ($donnees, $chemin): User {
                $user = User::create([
                    'name' => trim((string) $donnees['prenom'].' '.(string) $donnees['nom']),
                    'telephone' => $donnees['telephone'],
                    'email' => $donnees['email'] ?? null,
                    'password' => $donnees['password'],
                    'actif' => true,
                ]);
                $user->assignRole('membre');

                Membre::create([
                    'user_id' => $user->id,
                    'prenom' => $donnees['prenom'],
                    'nom' => $donnees['nom'],
                    'sexe' => $donnees['sexe'],
                    'date_naissance' => $donnees['date_naissance'] ?? null,
                    'lieu_naissance' => $donnees['lieu_naissance'] ?? null,
                    'telephone' => $donnees['telephone'],
                    'email' => $donnees['email'] ?? null,
                    'section_id' => $donnees['section_id'],
                    'photo_path' => $chemin,
                    'consentement_le' => now(),
                    'consentement_version' => config('dahira.consentement_version'),
                ]);

                return $user;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($chemin);

            throw $e;
        }
    }
}
