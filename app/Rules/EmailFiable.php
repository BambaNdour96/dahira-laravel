<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Une adresse email fiable : format strict, domaine qui reçoit des emails,
 * pas d'adresse jetable, et une seule utilisation, majuscules comprises.
 */
final class EmailFiable implements ValidationRule
{
    private const JETABLES = [
        'yopmail.com', 'yopmail.fr', 'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'trashmail.com', 'getnada.com', 'sharklasers.com', 'dispostable.com', 'maildrop.cc',
        'throwawaymail.com', 'fakeinbox.com', 'mohmal.com', 'emailondeck.com', 'mailnesia.com', 'tempail.com',
    ];

    public function __construct(private readonly ?int $ignorerUtilisateur = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $email = mb_strtolower(trim($value));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/', $email) !== 1) {
            $fail('Cette adresse email n\'est pas valide.');

            return;
        }

        $domaine = substr($email, (int) strrpos($email, '@') + 1);

        if (in_array($domaine, self::JETABLES, true)) {
            $fail('Les adresses email temporaires ne sont pas acceptées.');

            return;
        }

        if (self::verifierDomaine() && ! checkdnsrr($domaine, 'MX') && ! checkdnsrr($domaine, 'A')) {
            $fail('Le domaine de cette adresse ne reçoit pas d\'emails. Vérifiez l\'orthographe.');

            return;
        }

        $dejaPrise = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->when($this->ignorerUtilisateur !== null, fn ($requete) => $requete->whereKeyNot($this->ignorerUtilisateur))
            ->exists();

        if ($dejaPrise) {
            $fail('Cette adresse email est déjà utilisée par un autre compte.');
        }
    }

    /** Pas d'appel au réseau pendant les tests automatiques. */
    private static function verifierDomaine(): bool
    {
        return ! app()->runningUnitTests() && (bool) config('dahira.verifier_domaine_email', true);
    }
}
