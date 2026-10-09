<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CompteBureauService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnexion après inactivité : 10 minutes pour le bureau, 25 pour le super administrateur, 6 pour les membres.
 */
class ExpirationInactivite
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $minutes = self::minutesPour($utilisateur);

        $maintenant = now()->getTimestamp();
        $derniere = (int) $request->session()->get('derniere_activite', $maintenant);

        if ($maintenant - $derniere > $minutes * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Dans le panneau, la page propose alors de se recharger, puis mène à la connexion.
            abort_if($request->is('livewire*'), 419);

            return redirect()->route('login')
                ->with('succes', 'Vous avez été déconnecté après '.$minutes.' minutes sans activité. Reconnectez-vous.');
        }

        // La mise à jour automatique (notifications, compteurs) ne compte pas comme une activité.
        if (! self::enArrierePlan($request)) {
            $request->session()->put('derniere_activite', $maintenant);
        }

        return $next($request);
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
