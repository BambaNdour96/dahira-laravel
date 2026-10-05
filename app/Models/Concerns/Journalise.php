<?php

namespace App\Models\Concerns;

use App\Models\JournalActivite;
use Illuminate\Database\Eloquent\Model;

/**
 * Journalise automatiquement les créations, modifications et suppressions.
 */
trait Journalise
{
    /**
     * Champs jamais recopiés dans le journal.
     *
     * @var list<string>
     */
    private static array $champsIgnores = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootJournalise(): void
    {
        static::created(function (Model $modele): void {
            JournalActivite::enregistrer($modele, 'cree');
        });

        static::updated(function (Model $modele): void {
            $apres = array_diff_key($modele->getChanges(), array_flip(self::$champsIgnores));

            if ($apres === []) {
                return;
            }

            $avant = array_intersect_key($modele->getRawOriginal(), $apres);

            JournalActivite::enregistrer($modele, 'modifie', ['avant' => $avant, 'apres' => $apres]);
        });

        static::deleted(function (Model $modele): void {
            JournalActivite::enregistrer($modele, 'supprime');
        });
    }
}
