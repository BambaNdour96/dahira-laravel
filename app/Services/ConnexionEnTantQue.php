<?php

namespace App\Services;

use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Le super administrateur ouvre la plateforme comme un autre compte,
 * puis revient au sien. Chaque usage est noté au journal.
 */
final class ConnexionEnTantQue
{
    public const CLE = 'impersonateur_id';

    public function demarrer(User $superAdmin, User $cible): void
    {
        if (! $superAdmin->hasRole('super_administrateur')
            || $cible->hasRole('super_administrateur')
            || $cible->is($superAdmin)) {
            throw new AuthorizationException('Action non autorisée.');
        }

        JournalActivite::enregistrer($cible, 'en_tant_que', ['apres' => ['par' => $superAdmin->name]]);

        session()->put(self::CLE, $superAdmin->id);
        Auth::login($cible);
    }

    /** Revient au compte du super administrateur. */
    public function arreter(): ?User
    {
        $original = self::original();

        if ($original === null) {
            session()->forget(self::CLE);

            return null;
        }

        Auth::login($original);
        session()->forget(self::CLE);

        return $original;
    }

    /** Le super administrateur d'origine, si une session « en tant que » est en cours. */
    public static function original(): ?User
    {
        $id = session()->get(self::CLE);

        return is_int($id) ? User::query()->find($id) : null;
    }
}
