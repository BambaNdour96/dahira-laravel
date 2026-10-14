<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Les numéros en série « PRÉFIXE-AAAA-0001 » (reçus de sympathisants, remises de fonds).
 *
 * Le numéro suivant part du dernier numéro de la série : sa ligne est verrouillée (PostgreSQL refuse
 * un verrou sur un comptage). Si deux personnes enregistrent au même instant, la contrainte d'unicité
 * de la colonne refuse le doublon et l'enregistrement est retenté avec le numéro suivant.
 */
final class Numerotation
{
    private const ESSAIS = 5;

    /**
     * Crée l'enregistrement avec le prochain numéro libre de la série.
     *
     * @template TModele of Model
     *
     * @param  class-string<TModele>  $modele
     * @param  Closure(string): TModele  $creer
     * @return TModele
     */
    public static function creer(string $modele, string $colonne, string $prefixe, Closure $creer): Model
    {
        for ($essai = 1; ; $essai++) {
            try {
                return DB::transaction(fn (): Model => $creer(self::suivant($modele, $colonne, $prefixe)));
            } catch (UniqueConstraintViolationException $e) {
                if ($essai >= self::ESSAIS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Le numéro qui suit le dernier de la série.
     *
     * @param  class-string<Model>  $modele
     */
    public static function suivant(string $modele, string $colonne, string $prefixe): string
    {
        // Le plus long d'abord : « 10000 » vient après « 9999 ». Seules les colonnes connues sont acceptées.
        $plusLongDabord = match ($colonne) {
            'numero' => 'length(numero) desc',
            'numero_recu' => 'length(numero_recu) desc',
            default => throw new InvalidArgumentException("Colonne de numérotation inconnue : {$colonne}."),
        };

        $dernier = $modele::query()
            ->where($colonne, 'like', $prefixe.'%')
            ->orderByRaw($plusLongDabord)
            ->orderByDesc($colonne)
            ->lockForUpdate()
            ->value($colonne);

        $rang = is_string($dernier) ? ((int) substr($dernier, strlen($prefixe))) + 1 : 1;

        return $prefixe.str_pad((string) $rang, 4, '0', STR_PAD_LEFT);
    }
}
