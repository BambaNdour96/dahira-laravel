<?php

namespace App\Http\Controllers;

use App\Enums\LienFamille;
use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use App\Policies\MembrePolicy;
use App\Services\CotisationService;
use App\Services\FamilleService;
use App\Services\PaiementService;
use App\Support\MessagesValidation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** « Ma famille » sur le téléphone : les fiches des proches que le membre gère, leurs cartes et leurs paiements. */
class FamilleController extends Controller
{
    public function index(Request $request, CotisationService $cotisations): View
    {
        $membre = self::membre($request);
        $proches = $membre->proches()->with(['cellule', 'carteActive'])->orderBy('date_naissance')->get();

        return view('espace.famille', [
            'membre' => $membre,
            'proches' => $proches,
            'retards' => $cotisations->retards($proches->where('statut', StatutMembre::Valide)),
        ]);
    }

    public function create(Request $request): View
    {
        return view('espace.famille-ajouter', ['membre' => self::membre($request), 'liens' => LienFamille::cases(), 'sexes' => Sexe::cases()]);
    }

    public function store(Request $request, FamilleService $familles): RedirectResponse
    {
        $membre = self::membre($request);

        $donnees = $request->validate([
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'sexe' => ['required', Rule::enum(Sexe::class)],
            'date_naissance' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'lieu_naissance' => ['nullable', 'string', 'max:100'],
            'lien_famille' => ['required', Rule::enum(LienFamille::class)],
            'telephone' => ['nullable', 'string', 'max:20'],
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ], [
            ...MessagesValidation::tous(),
            'photo.required' => 'La photo est nécessaire pour la carte de membre.',
        ]);

        try {
            $proche = $familles->ajouterProche($membre, [
                'prenom' => (string) $donnees['prenom'],
                'nom' => (string) $donnees['nom'],
                'sexe' => (string) $donnees['sexe'],
                'date_naissance' => (string) $donnees['date_naissance'],
                'lieu_naissance' => isset($donnees['lieu_naissance']) ? (string) $donnees['lieu_naissance'] : null,
                'lien_famille' => (string) $donnees['lien_famille'],
                'telephone' => isset($donnees['telephone']) ? (string) $donnees['telephone'] : null,
            ], $request->file('photo'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['telephone' => $e->getMessage()]);
        }

        return redirect()->route('famille.show', $proche)
            ->with('succes', 'La fiche de '.$proche->prenom.' est envoyée à votre Jewrine de cellule, qui la validera.');
    }

    public function show(Request $request, Membre $proche, PaiementService $paiements, CotisationService $cotisations, FamilleService $familles): View
    {
        $utilisateur = self::utilisateur($request);
        self::verifier($utilisateur, $proche);
        $fraisCarte = $proche->paiements()->where('objet', 'frais_carte');

        return view('espace.famille-proche', [
            'proche' => $proche->load(['cellule', 'carteActive']),
            'montantFraisCarte' => $paiements->montantFraisCarte(),
            'lienWave' => Parametre::valeur('wave_lien_frais_carte') ?: Parametre::valeur('wave_lien_paiement'),
            'paiementEnCours' => $paiements->enCours($proche),
            'dernierRefus' => (clone $fraisCarte)->where('statut', StatutPaiement::Refuse->value)->latest('traite_le')->first(),
            'retard' => $proche->statut === StatutMembre::Valide ? count($cotisations->moisEnRetard($proche)) : 0,
            'recus' => $proche->paiements()->where('statut', StatutPaiement::Confirme->value)->whereNotNull('numero_recu')->latest('traite_le')->limit(12)->get(),
            'peutDonnerSonCompte' => $familles->peutDonnerSonCompte($utilisateur, $proche) && ! $proche->estEnfant(),
        ]);
    }

    /** Le responsable déclare le paiement Wave des frais de carte de son proche. */
    public function frais(Request $request, Membre $proche, PaiementService $paiements): RedirectResponse
    {
        $utilisateur = self::utilisateur($request);
        self::verifier($utilisateur, $proche);
        [$reference, $numeroWave] = PaiementMembreController::valider($request);

        try {
            $paiements->declarer($proche, $reference, $numeroWave, $utilisateur);
        } catch (DomainException $e) {
            return back()->withErrors(['reference' => $e->getMessage()]);
        }

        return redirect()->route('famille.show', $proche)
            ->with('succes', 'Merci ! Le paiement des frais de carte de '.$proche->prenom.' est en cours de vérification.');
    }

    /** Le responsable donne son propre compte à un proche majeur : le mot de passe provisoire s'affiche une fois. */
    public function compte(Request $request, Membre $proche, FamilleService $familles): RedirectResponse
    {
        $utilisateur = self::utilisateur($request);
        self::verifier($utilisateur, $proche);
        $request->validate(['telephone' => ['required', 'string', 'max:20']], [...MessagesValidation::tous(), 'telephone.required' => 'Indiquez le numéro du proche.']);

        try {
            $motDePasse = $familles->donnerSonCompte($proche, (string) $request->input('telephone'), $utilisateur);
        } catch (DomainException $e) {
            return back()->withErrors(['telephone' => $e->getMessage()]);
        }

        return redirect()->route('famille.index')
            ->with('succes', $proche->prenom.' a maintenant son propre compte. Transmettez-lui son mot de passe provisoire : '.$motDePasse.' (il le changera à la première connexion).');
    }

    /** Seul le membre qui gère la fiche de ce proche y a accès. */
    public static function verifier(User $utilisateur, Membre $proche): void
    {
        abort_unless(MembrePolicy::gereLaFiche($utilisateur, $proche), 403);
    }

    private static function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }

    private static function membre(Request $request): Membre
    {
        $membre = self::utilisateur($request)->membre;
        abort_if($membre === null, 404);

        return $membre;
    }
}
