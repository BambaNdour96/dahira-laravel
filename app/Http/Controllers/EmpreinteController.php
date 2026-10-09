<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Throwable;

/**
 * Connexion par empreinte digitale, visage ou code du téléphone (clés d'accès).
 * L'empreinte ne quitte jamais l'appareil : la plateforme ne garde qu'une clé publique.
 */
class EmpreinteController extends Controller
{
    private const OPTIONS_ENREGISTREMENT = 'passkey-registration-options';

    private const OPTIONS_CONNEXION = 'passkey-authentication-options';

    public function optionsEnregistrement(Request $request, GeneratePasskeyRegisterOptionsAction $action): Response
    {
        $this->preparerDomaine($request);
        $options = $action->execute($this->utilisateur($request));
        abort_unless(is_string($options), 500);
        $request->session()->put(self::OPTIONS_ENREGISTREMENT, $options);

        return response($options, 200, ['Content-Type' => 'application/json']);
    }

    public function enregistrer(Request $request, StorePasskeyAction $action): JsonResponse
    {
        $this->preparerDomaine($request);
        $cle = $request->input('cle');
        $options = $request->session()->pull(self::OPTIONS_ENREGISTREMENT);

        if (! is_string($cle) || ! is_string($options)) {
            return response()->json(['message' => 'Activation impossible. Réessayez.'], 422);
        }

        try {
            $action->execute($this->utilisateur($request), $cle, $options, $request->getHost(), [
                'name' => $this->nomAppareil($request).', ajoutée le '.now()->format('d/m/Y'),
            ]);
        } catch (Throwable) {
            return response()->json(['message' => 'Activation impossible sur cet appareil. Réessayez.'], 422);
        }

        return response()->json(['message' => 'Connexion par empreinte activée sur cet appareil.']);
    }

    public function supprimer(Request $request, int $cle): RedirectResponse
    {
        $this->utilisateur($request)->passkeys()->whereKey($cle)->delete();

        return back()->with('succes', 'Cette empreinte ne permet plus de vous connecter.');
    }

    public function optionsConnexion(Request $request, GeneratePasskeyAuthenticationOptionsAction $action): Response
    {
        $this->preparerDomaine($request);

        return response($action->execute(), 200, ['Content-Type' => 'application/json']);
    }

    public function connecter(Request $request, FindPasskeyToAuthenticateAction $action): JsonResponse
    {
        $this->preparerDomaine($request);
        $reponse = $request->input('reponse');
        $options = $request->session()->pull(self::OPTIONS_CONNEXION);
        $refus = response()->json(['message' => 'Empreinte non reconnue. Connectez-vous avec votre mot de passe.'], 422);

        if (! is_string($reponse) || ! is_string($options)) {
            return $refus;
        }

        try {
            $cle = $action->execute($reponse, $options);
        } catch (Throwable) {
            return $refus;
        }

        $utilisateur = $cle?->authenticatable;

        // Mêmes règles que la connexion par mot de passe : un compte désactivé ou effacé n'entre pas.
        if (! $utilisateur instanceof User || ! (bool) $utilisateur->actif) {
            return $refus;
        }

        Auth::login($utilisateur);
        $request->session()->regenerate();

        return response()->json([
            'redirection' => $request->session()->pull('url.intended', route('espace')),
        ]);
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }

    /** Pendant les essais, l'adresse du tunnel ; en production, le nom de domaine du site. */
    private function preparerDomaine(Request $request): void
    {
        $domaine = app()->isLocal()
            ? $request->getHost()
            : (parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost());

        config([
            'passkeys.relying_party.id' => $domaine,
            'passkeys.relying_party.name' => 'Daara Nouroud Daareyni',
        ]);
    }

    private function nomAppareil(Request $request): string
    {
        $agent = (string) $request->userAgent();

        return match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iPhone',
            str_contains($agent, 'Android') => 'Téléphone Android',
            str_contains($agent, 'Windows') => 'Ordinateur Windows',
            str_contains($agent, 'Mac') => 'Mac',
            default => 'Appareil',
        };
    }
}
