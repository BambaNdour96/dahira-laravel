<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Mots de passe provisoires faciles à lire et à recopier.
 */
final class AffichageMotDePasse
{
    /** Sans les caractères qui se ressemblent : 0, O, 1, I, L. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generer(int $longueur = 8): string
    {
        $motDePasse = '';

        for ($i = 0; $i < $longueur; $i++) {
            $motDePasse .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $motDePasse;
    }

    /** Le mot de passe en grand, séparé du texte, pour le recopier sans erreur. */
    public static function corps(string $motDePasse): HtmlString
    {
        return new HtmlString(
            'Mot de passe provisoire :<br>'
            .'<span style="font-family:monospace;font-size:1.5rem;font-weight:700;letter-spacing:3px">'.e($motDePasse).'</span><br>'
            .'Transmettez-le à la personne. Il ne sera plus affiché.'
        );
    }
}
