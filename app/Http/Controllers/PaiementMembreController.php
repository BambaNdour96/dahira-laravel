<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\PaiementService;
use App\Services\StatutMembreService;
use App\Services\TelephoneNormalizer;
use App\Support\AntiFraude;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaiementMembreController extends Controller
{
    /** Le membre déclare son paiement Wave des frais de carte. */
    public function declarer(Request $request, PaiementService $paiements): RedirectResponse
    {
        $membre = $this->membre($request);
        [$reference, $numeroWave] = self::valider($request);

        try {
            $paiements->declarer($membre, $reference, $numeroWave);
        } catch (DomainException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return redirect()->route('espace')
            ->with('succes', 'Merci ! Votre paiement est en cours de vérification par le Jewrine finance.');
    }

    /** Après expiration, le membre renvoie sa demande à son Jewrine. */
    public function relancer(Request $request, StatutMembreService $statuts): RedirectResponse
    {
        $membre = $this->membre($request);

        abort_unless($membre->statut === StatutMembre::Expire, 403);

        $auteur = $request->user();
        $statuts->changer($membre, StatutMembre::EnAttente, $auteur instanceof User ? $auteur : null);

        return redirect()->route('espace')
            ->with('succes', 'Votre demande a été renvoyée à votre Jewrine de cellule.');
    }

    /**
     * Contrôle commun des déclarations : référence unique et bien formée, numéro Wave valide.
     *
     * @return array{0: string, 1: string|null}
     */
    public static function valider(Request $request): array
    {
        $request->merge(['reference' => AntiFraude::normaliserReference($request->input('reference'))]);

        $request->validate([
            'reference' => ['required', 'string', 'regex:'.AntiFraude::FORMAT_REFERENCE, 'unique:paiements,reference'],
            'numero_wave' => ['nullable', 'string', 'max:30'],
        ], [
            'reference.required' => 'Indiquez la référence de votre transaction Wave.',
            'reference.regex' => 'Référence invalide : uniquement des lettres, des chiffres et des tirets, 4 caractères au moins.',
            'reference.unique' => 'Cette référence a déjà été utilisée. Une référence ne peut servir qu\'une seule fois.',
        ]);

        $numeroWave = null;

        if (filled($request->input('numero_wave'))) {
            $numeroWave = TelephoneNormalizer::normaliser((string) $request->input('numero_wave'));

            if ($numeroWave === null) {
                throw ValidationException::withMessages([
                    'numero_wave' => 'Numéro Wave invalide. Exemple : 77 123 45 67.',
                ]);
            }
        }

        return [(string) $request->input('reference'), $numeroWave];
    }

    private function membre(Request $request): Membre
    {
        $user = $request->user();
        $membre = $user instanceof User ? $user->membre : null;

        abort_if($membre === null, 404);

        return $membre;
    }
}
