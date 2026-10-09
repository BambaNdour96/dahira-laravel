<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CompteBureauService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessions :
 * - inactivité : 2 minutes pour un membre (retour à la même page après reconnexion),
 *   10 minutes pour le bureau, 25 pour le super administrateur ;
 * - durée maximale : 10 minutes pour un membre, actif ou non (retour à l'accueil).
 */
class ExpirationInactivite
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $maintenant = now()->getTimestamp();
        $session = $request->session();

        if (! $session->has('connecte_le')) {
            $session->put('connecte_le', $maintenant);
        }

        // Durée maximale du membre : on repart de l'accueil.
        $fin = self::finSessionPour($utilisateur, (int) $session->get('connecte_le'));

        if ($fin !== null && $maintenant >= $fin) {
            return $this->fermer($request, null,
                'Votre session de '.self::dureeMaxMembres().' minutes est terminée. Reconnectez-vous.');
        }

        // Inactivité : on revient à la même page après la reconnexion.
        $minutes = self::minutesPour($utilisateur);
        $derniere = (int) $session->get('derniere_activite', $maintenant);

        if ($maintenant - $derniere > $minutes * 60) {
            $page = $request->isMethod('GET') && ! $request->is('livewire*') && ! $request->expectsJson()
                ? $request->fullUrl()
                : url()->previous();

            return $this->fermer($request, $page,
                'Vous avez été déconnecté après '.$minutes.' minutes sans activité. Reconnectez-vous pour reprendre où vous étiez.');
        }

        // La mise à jour automatique (notifications, compteurs) ne compte pas comme une activité.
        if (! self::enArrierePlan($request)) {
            $session->put('derniere_activite', $maintenant);
        }

        return $next($request);
    }

    private function fermer(Request $request, ?string $pageDeRetour, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($pageDeRetour !== null) {
            $request->session()->put('url.intended', $pageDeRetour);
        }

        // Dans le panneau, la page propose alors de se recharger, puis mène à la connexion.
        abort_if($request->is('livewire*'), 419);

        return redirect()->route('login')->with('succes', $message);
    }

    public static function estDuBureau(User $utilisateur): bool
    {
        return $utilisateur->hasAnyRole([...CompteBureauService::ROLES_BUREAU, 'super_administrateur']);
    }

    /** Durée d'inactivité autorisée, en minutes, selon le profil. */
    public static function minutesPour(User $utilisateur): int
    {
        return match (true) {
            $utilisateur->hasRole('super_administrateur') => (int) config('dahira.inactivite_super_admin', 25),
            $utilisateur->hasAnyRole(CompteBureauService::ROLES_BUREAU) => (int) config('dahira.inactivite_bureau', 10),
            default => (int) config('dahira.inactivite_membres', 2),
        };
    }

    public static function dureeMaxMembres(): int
    {
        return (int) config('dahira.session_max_membres', 10);
    }

    /** Heure de fin de la session d'un membre (timestamp), ou null pour le bureau. */
    public static function finSessionPour(User $utilisateur, ?int $connecteLe = null): ?int
    {
        if (self::estDuBureau($utilisateur)) {
            return null;
        }

        $debut = $connecteLe ?? (int) session('connecte_le', now()->getTimestamp());

        return $debut + self::dureeMaxMembres() * 60;
    }

    /** Une requête Livewire qui ne fait que rafraîchir l'écran, sans action de la personne. */
    public static function enArrierePlan(Request $request): bool
    {
        $composants = $request->input('components');

        if (! $request->is('livewire*') || ! is_array($composants) || $composants === []) {
            return false;
        }

        foreach ($composants as $composant) {
            if (! is_array($composant) || ! empty($composant['updates'] ?? [])) {
                return false;
            }

            foreach ((array) ($composant['calls'] ?? []) as $appel) {
                if (! is_array($appel) || ($appel['method'] ?? null) !== '$refresh') {
                    return false;
                }
            }
        }

        return true;
    }
}
