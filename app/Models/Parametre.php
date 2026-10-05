<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglage général de la Daara, par exemple le lien du groupe WhatsApp national.
 *
 * @property string $cle
 * @property string $libelle
 * @property string|null $valeur
 */
class Parametre extends Model
{
    use Journalise;

    protected $fillable = ['cle', 'libelle', 'valeur'];

    public static function valeur(string $cle): ?string
    {
        $valeur = static::query()->where('cle', $cle)->value('valeur');

        return is_string($valeur) && $valeur !== '' ? $valeur : null;
    }
}
