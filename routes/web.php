<?php

use App\Http\Controllers\AbonnementTelephoneController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\BordereauRemiseController;
use App\Http\Controllers\BudgetComiteController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\CarteController;
use App\Http\Controllers\CelluleController;
use App\Http\Controllers\CharteController;
use App\Http\Controllers\ComitesController;
use App\Http\Controllers\CommissionsController;
use App\Http\Controllers\ConfirmationEmailController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\CotisationController;
use App\Http\Controllers\EmpreinteController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\EnTantQueController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\ExportPresencesController;
use App\Http\Controllers\FamilleController;
use App\Http\Controllers\FicheCollecteController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\LotCartesController;
use App\Http\Controllers\MesDonneesController;
use App\Http\Controllers\MesNotificationsController;
use App\Http\Controllers\MesRecusController;
use App\Http\Controllers\MotDePasseController;
use App\Http\Controllers\MotDePasseOublieController;
use App\Http\Controllers\OrdreDepenseController;
use App\Http\Controllers\PaiementMembreController;
use App\Http\Controllers\PaiementPourAutruiController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PieceOrdreDepenseController;
use App\Http\Controllers\PointageController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\ProgrammeExportController;
use App\Http\Controllers\ProgrammeRapportController;
use App\Http\Controllers\PvReunionController;
use App\Http\Controllers\RapportFinancierController;
use App\Http\Controllers\RapportProjetController;
use App\Http\Controllers\RapportSuiviPdfController;
use App\Http\Controllers\RecensementController;
use App\Http\Controllers\RecuController;
use App\Http\Controllers\RecuSympathisantController;
use App\Http\Controllers\RelanceController;
use App\Http\Controllers\RenouvellementController;
use App\Http\Controllers\RepriseController;
use App\Http\Controllers\SuppressionCompteController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\VolontariatController;
use App\Http\Middleware\ExigerCharteConfidentialite;
use App\Listeners\MemoriserNumero;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.accueil')->name('accueil');
Route::view('/confidentialite', 'public.confidentialite')->name('confidentialite');
Route::view('/hors-ligne', 'public.hors-ligne')->name('hors-ligne');

// Page ouverte par le QR code de la carte.
Route::get('/v/{jeton}', [VerificationController::class, 'show'])
    ->middleware('throttle:verification')
    ->name('verification');
Route::get('/v/{jeton}/photo', [VerificationController::class, 'photo'])
    ->middleware('signed')
    ->name('verification.photo');

Route::middleware('guest')->group(function () {
    Route::get('/inscription', [InscriptionController::class, 'create'])->name('inscription');
    Route::post('/inscription', [InscriptionController::class, 'store'])
        ->middleware('throttle:inscription')
        ->name('inscription.store');

    Route::get('/connexion', [ConnexionController::class, 'create'])->name('login');
    Route::get('/connexion/autre-numero', fn () => redirect()->route('login')->withoutCookie(MemoriserNumero::COOKIE))->name('connexion.autre-numero');
    Route::post('/connexion', [ConnexionController::class, 'store'])
        ->middleware('throttle:connexion')
        ->name('login.store');

    // Activer le compte d'une fiche reprise des listes existantes
    Route::get('/activer-mon-compte', [ActivationController::class, 'create'])->name('activation');
    Route::post('/activer-mon-compte', [ActivationController::class, 'store'])->middleware('throttle:connexion')->name('activation.store');

    // Mot de passe oublié
    Route::get('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [MotDePasseOublieController::class, 'store'])
        ->middleware('throttle:connexion')
        ->name('password.email');
    Route::get('/reinitialiser-mot-de-passe/{token}', [MotDePasseOublieController::class, 'edit'])->name('password.reset');
    Route::post('/reinitialiser-mot-de-passe', [MotDePasseOublieController::class, 'update'])
        ->middleware('throttle:connexion')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('logout');
    Route::view('/inscription/merci', 'public.merci')->name('inscription.merci');

    Route::get('/mon-espace', [EspaceController::class, 'show'])->name('espace');
    Route::get('/mon-espace/profil', [ProfilController::class, 'edit'])->name('espace.profil');
    Route::put('/mon-espace/profil', [ProfilController::class, 'update'])->name('espace.profil.update');
    Route::post('/mon-espace/paiement', [PaiementMembreController::class, 'declarer'])
        ->middleware('throttle:inscription')
        ->name('espace.paiement');
    Route::post('/mon-espace/relancer', [PaiementMembreController::class, 'relancer'])->name('espace.relancer');
    Route::get('/ma-carte', [CarteController::class, 'show'])->name('carte');
    Route::get('/ma-carte/pdf', [CarteController::class, 'pdf'])->name('carte.pdf');
    Route::post('/ma-carte/plastique', [CarteController::class, 'commander'])->middleware('throttle:inscription')->name('carte.plastique');

    Route::get('/mon-compte/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mon-compte/mot-de-passe', [MotDePasseController::class, 'update'])
        ->middleware('throttle:connexion')
        ->name('mot-de-passe.update');

    Route::get('/ma-famille', [FamilleController::class, 'index'])->name('famille.index');
    Route::get('/ma-famille/ajouter', [FamilleController::class, 'create'])->name('famille.create');
    Route::post('/ma-famille', [FamilleController::class, 'store'])->middleware('throttle:inscription')->name('famille.store');
    Route::get('/ma-famille/{proche}', [FamilleController::class, 'show'])->name('famille.show');
    Route::post('/ma-famille/{proche}/frais', [FamilleController::class, 'frais'])->middleware('throttle:inscription')->name('famille.frais');
    Route::post('/ma-famille/{proche}/compte', [FamilleController::class, 'compte'])->name('famille.compte');
    Route::post('/ma-famille/{proche}/savoir-faire', [FamilleController::class, 'competences'])->name('famille.competences');
    Route::post('/ma-famille/{proche}/arabe-coran', [FamilleController::class, 'arabeCoran'])->name('famille.arabe_coran');
    Route::get('/ma-famille/{proche}/carte', [CarteController::class, 'show'])->name('famille.carte');
    Route::get('/ma-famille/{proche}/carte/pdf', [CarteController::class, 'pdf'])->name('famille.carte.pdf');
    Route::post('/ma-famille/{proche}/carte/plastique', [CarteController::class, 'commander'])->middleware('throttle:inscription')->name('famille.carte.plastique');
    Route::get('/ma-famille/{proche}/engagements', [EngagementController::class, 'index'])->name('famille.engagements');
    Route::post('/ma-famille/{proche}/engagements/{collecte}', [EngagementController::class, 'fixerPourProche'])->middleware('throttle:inscription')->name('famille.engagements.fixer');
    Route::post('/ma-famille/{proche}/engagements/{collecte}/augmenter', [EngagementController::class, 'augmenterPourProche'])->middleware('throttle:inscription')->name('famille.engagements.augmenter');
    Route::post('/ma-famille/{proche}/engagements/{collecte}/versement', [EngagementController::class, 'declarerPourProche'])->middleware('throttle:inscription')->name('famille.engagements.versement');
    Route::get('/payer-pour-un-membre', [PaiementPourAutruiController::class, 'show'])->name('autrui.show');
    Route::post('/payer-pour-un-membre/{beneficiaire}/cotisation', [PaiementPourAutruiController::class, 'cotisation'])->middleware('throttle:inscription')->name('autrui.cotisation');
    Route::post('/payer-pour-un-membre/{beneficiaire}/renouvellement', [PaiementPourAutruiController::class, 'renouvellement'])->middleware('throttle:inscription')->name('autrui.renouvellement');
    Route::post('/payer-pour-un-membre/{beneficiaire}/plastique', [PaiementPourAutruiController::class, 'plastique'])->middleware('throttle:inscription')->name('autrui.plastique');
    Route::post('/payer-pour-un-membre/{beneficiaire}/collectes/{collecte}', [PaiementPourAutruiController::class, 'versement'])->middleware('throttle:inscription')->name('autrui.versement');
    Route::get('/ma-famille/{proche}/cotisations', [CotisationController::class, 'show'])->name('famille.cotisations');
    Route::post('/ma-famille/{proche}/cotisations', [CotisationController::class, 'declarer'])->middleware('throttle:inscription')->name('famille.cotisations.declarer');

    Route::get('/mes-cotisations', [CotisationController::class, 'show'])->name('cotisations');
    Route::post('/mes-cotisations', [CotisationController::class, 'declarer'])
        ->middleware('throttle:inscription')
        ->name('cotisations.declarer');
    Route::get('/mes-engagements', [EngagementController::class, 'index'])->name('engagements.index');
    Route::post('/mes-engagements/{collecte}', [EngagementController::class, 'fixer'])
        ->middleware('throttle:inscription')
        ->name('engagements.fixer');
    Route::post('/mes-engagements/{collecte}/augmenter', [EngagementController::class, 'augmenter'])->middleware('throttle:inscription')->name('engagements.augmenter');
    Route::post('/mes-engagements/{collecte}/versement', [EngagementController::class, 'declarer'])
        ->middleware('throttle:inscription')
        ->name('engagements.versement');
    Route::get('/collectes/fiche-de-parametrage', FicheCollecteController::class)->name('collectes.fiche');
    Route::get('/relances/{membre}/whatsapp', [RelanceController::class, 'whatsapp'])->name('relances.whatsapp');
    Route::get('/relances/engagements/{engagement}/whatsapp', [RelanceController::class, 'collecte'])->name('relances.collecte');
    Route::get('/projets/{projet}/rapport', RapportProjetController::class)->name('projets.rapport');
    Route::get('/rapports/{rapport}/pdf', RapportSuiviPdfController::class)->name('rapports.pdf');
    Route::post('/comites/commissions/{commission}/bilan', [BudgetComiteController::class, 'bilanCommission'])->name('comites.bilans.store');
    Route::post('/comites/commissions/{commission}/besoins', [BudgetComiteController::class, 'besoin'])->name('comites.besoins.store');
    Route::post('/comites/commissions/{commission}/membres', [ComitesController::class, 'ajouterMembre'])->name('comites.membres.ajouter');
    Route::post('/comites/commissions/{commission}/membres/{membre}/retirer', [ComitesController::class, 'retirerMembre'])->name('comites.membres.retirer');
    Route::post('/comites/commissions/{commission}/taches', [ComitesController::class, 'tache'])->name('comites.taches.store');
    Route::post('/comites/ordres-depense/{ordre}/justificatifs', [OrdreDepenseController::class, 'justifier'])->middleware('throttle:inscription')->name('comites.ordres.justifier');
    Route::post('/comites/commissions/{commission}/ordres-depense', [OrdreDepenseController::class, 'pourCommission'])->middleware('throttle:inscription')->name('comites.ordres.store');
    Route::get('/justificatifs/{depense}', [BudgetComiteController::class, 'justificatif'])->name('justificatifs.show');
    Route::get('/comites/{instance}/bilan', [BudgetComiteController::class, 'bilan'])->name('comites.bilan');
    Route::post('/comites/taches/{action}/repartir', [ComitesController::class, 'repartir'])->name('comites.taches.repartir');
    Route::post('/comites/actions/{action}/statut', [ComitesController::class, 'statut'])->name('comites.actions.statut');
    Route::get('/mes-comites', ComitesController::class.'@index')->name('comites.index');
    Route::get('/renouvellement', [RenouvellementController::class, 'show'])->name('renouvellement.show');
    Route::post('/renouvellement/fiche', [RenouvellementController::class, 'verifier'])->name('renouvellement.verifier');
    Route::post('/renouvellement/signaler', [RenouvellementController::class, 'signaler'])->middleware('throttle:inscription')->name('renouvellement.signaler');
    Route::post('/renouvellement/paiement', [RenouvellementController::class, 'declarer'])->middleware('throttle:inscription')->name('renouvellement.payer');
    Route::get('/ma-famille/{proche}/renouvellement', [RenouvellementController::class, 'show'])->name('famille.renouvellement');
    Route::post('/ma-famille/{proche}/renouvellement/fiche', [RenouvellementController::class, 'verifier'])->name('famille.renouvellement.verifier');
    Route::post('/ma-famille/{proche}/renouvellement/signaler', [RenouvellementController::class, 'signaler'])->middleware('throttle:inscription')->name('famille.renouvellement.signaler');
    Route::post('/ma-famille/{proche}/renouvellement/paiement', [RenouvellementController::class, 'declarer'])->middleware('throttle:inscription')->name('famille.renouvellement.payer');
    Route::get('/recensement-arabe-coran', [RecensementController::class, 'index'])->name('recensement.index');
    Route::get('/apprenants-arabe-coran', [RecensementController::class, 'apprenants'])->name('recensement.apprenants');
    Route::get('/ma-cellule', [CelluleController::class, 'index'])->name('cellule.index');
    Route::post('/ma-cellule', [CelluleController::class, 'demander'])->middleware('throttle:inscription')->name('cellule.demander');
    Route::post('/ma-cellule/demandes/{changement}/retirer', [CelluleController::class, 'retirer'])->name('cellule.retirer');
    Route::get('/se-porter-volontaire', [VolontariatController::class, 'index'])->name('volontariat.index');
    Route::post('/se-porter-volontaire/{commission}', [VolontariatController::class, 'proposer'])->middleware('throttle:inscription')->name('volontariat.proposer');
    Route::post('/volontariats/{volontaire}/retirer', [VolontariatController::class, 'retirer'])->name('volontariat.retirer');
    Route::post('/volontariats/{volontaire}/reponse', [VolontariatController::class, 'repondre'])->name('volontariat.repondre');
    Route::post('/comites/volontariats/{volontaire}/decision', [VolontariatController::class, 'decider'])->name('comites.volontaires.decider');
    Route::post('/comites/commissions/{commission}/inviter/{membre}', [VolontariatController::class, 'inviter'])->name('comites.volontaires.inviter');
    Route::post('/commissions/{commission}/membres', [CommissionsController::class, 'ajouter'])->name('commissions.membres.ajouter');
    Route::post('/commissions/appartenances/{appartenance}/accepter', [CommissionsController::class, 'accepter'])->name('commissions.souhaits.accepter');
    Route::post('/commissions/appartenances/{appartenance}/refuser', [CommissionsController::class, 'refuser'])->name('commissions.souhaits.refuser');
    Route::post('/commissions/appartenances/{appartenance}/retirer', [CommissionsController::class, 'retirer'])->name('commissions.membres.retirer');
    Route::get('/reunions/{reunion}/pv', PvReunionController::class)->name('reunions.pv');
    Route::middleware(ExigerCharteConfidentialite::class)->group(function () {
        Route::get('/caisse', [CaisseController::class, 'index'])->name('caisse.index');
        Route::get('/caisse/membres/{membre}', [CaisseController::class, 'membre'])->name('caisse.membre');
        Route::post('/caisse/membres/{membre}/cotisation', [CaisseController::class, 'cotisation'])->name('caisse.cotisation');
        Route::post('/caisse/membres/{membre}/renouvellement', [CaisseController::class, 'renouvellement'])->name('caisse.renouvellement');
        Route::post('/caisse/membres/{membre}/plastique', [CaisseController::class, 'plastique'])->name('caisse.plastique');
        Route::post('/caisse/membres/{membre}/engagements/{engagement}', [CaisseController::class, 'versement'])->name('caisse.versement');
        Route::post('/caisse/paiements/{paiement}/valider', [CaisseController::class, 'valider'])->name('caisse.valider');
        Route::post('/caisse/paiements/{paiement}/refuser', [CaisseController::class, 'refuser'])->name('caisse.refuser');
    });
    Route::get('/remises/{remise}/bordereau', BordereauRemiseController::class)->name('remises.bordereau');
    Route::get('/finances/bilan/{exercice}', [RapportFinancierController::class, 'bilan'])->name('finances.bilan');
    Route::get('/finances/rapport.xlsx', [RapportFinancierController::class, 'excel'])->name('finances.excel');
    Route::get('/ordres-depense/{ordre}/{piece}', PieceOrdreDepenseController::class)->whereIn('piece', ['devis', 'preuve'])->name('ordres-depense.piece');
    Route::get('/reprise/modele', [RepriseController::class, 'modele'])->name('reprise.modele');
    Route::get('/reprise/{import}/rapport', [RepriseController::class, 'rapport'])->name('reprise.rapport');
    Route::get('/lots-cartes/{lot}/{format}', LotCartesController::class)->whereIn('format', ['pdf', 'csv'])->name('lots-cartes.fichier');
    Route::post('/pointage/{evenement}/sympathisants', [PointageController::class, 'accueillirSympathisant'])->name('pointage.sympathisant.nouveau');
    Route::post('/pointage/{evenement}/sympathisants/{sympathisant}', [PointageController::class, 'sympathisantPresent'])->name('pointage.sympathisant');
    Route::post('/pointage/{evenement}/visiteurs', [PointageController::class, 'visiteur'])->name('pointage.visiteur');
    Route::get('/recus-sympathisants/{participation}', RecuSympathisantController::class)->name('sympathisants.recu');
    Route::get('/pointage', [PointageController::class, 'index'])->name('pointage.index');
    Route::get('/pointage/{evenement}', [PointageController::class, 'show'])->name('pointage.show');
    Route::post('/pointage/{evenement}/{membre}', [PointageController::class, 'pointer'])->name('pointage.pointer');
    Route::get('/bureau/charte', [CharteController::class, 'show'])->name('charte');
    Route::post('/bureau/charte', [CharteController::class, 'accepter'])->name('charte.accepter');
    Route::get('/programmes/{collecte}/export', ProgrammeExportController::class)->name('programmes.export');
    Route::get('/programmes/{collecte}/rapport', ProgrammeRapportController::class)->name('programmes.rapport');
    Route::get('/recus/{paiement}', [RecuController::class, 'show'])->name('recus.show');
    Route::get('/mes-recus', [MesRecusController::class, 'index'])->name('recus.index');
    Route::get('/mon-compte/mes-donnees', [MesDonneesController::class, 'telecharger'])->name('donnees.export');
    Route::get('/mes-notifications', [MesNotificationsController::class, 'index'])->name('notifications.index');
    Route::view('/mon-compte/parametres', 'espace.parametres')->name('parametres');
    Route::post('/mes-notifications/lues', [MesNotificationsController::class, 'marquerLues'])->name('notifications.lues');
    Route::post('/notifications-telephone', [AbonnementTelephoneController::class, 'store'])->name('notifications-telephone.store');
    Route::delete('/notifications-telephone', [AbonnementTelephoneController::class, 'destroy'])->name('notifications-telephone.destroy');
    Route::post('/session/activite', fn () => response()->noContent())->name('session.activite');
    Route::get('/evenements', [EvenementController::class, 'index'])->name('evenements.index');
    Route::post('/v/{jeton}/presence/{evenement}', [PresenceController::class, 'store'])->name('presences.store');
    Route::get('/exports/evenements/{evenement}/presences', ExportPresencesController::class)->name('evenements.presences.export');
    Route::get('/mon-compte/suppression', [SuppressionCompteController::class, 'create'])->name('suppression.create');
    Route::post('/mon-compte/suppression', [SuppressionCompteController::class, 'store'])->middleware('throttle:connexion')->name('suppression.store');

    // Super administrateur : voir la plateforme comme un autre compte (lien signé, 5 minutes).
    Route::get('/en-tant-que/{user}', [EnTantQueController::class, 'demarrer'])
        ->middleware('signed')
        ->name('en-tant-que');
    Route::post('/retour-mon-compte', [EnTantQueController::class, 'arreter'])->name('en-tant-que.retour');

    Route::get('/photos/{membre}', PhotoController::class)->name('photos.show');
});

// Confirmation de l'adresse email : lien signé reçu par email, et renvoi depuis le compte.
Route::get('/email/confirmer/{user}/{hash}', [ConfirmationEmailController::class, 'confirmer'])
    ->middleware('signed')->name('email.confirmer');
Route::post('/mon-compte/email/renvoyer', [ConfirmationEmailController::class, 'renvoyer'])
    ->middleware(['auth', 'throttle:3,10'])->name('email.renvoyer');

// Connexion par empreinte (clés d'accès).
Route::middleware('auth')->group(function (): void {
    Route::get('/mon-compte/empreinte/options', [EmpreinteController::class, 'optionsEnregistrement'])->name('empreinte.options');
    Route::post('/mon-compte/empreinte', [EmpreinteController::class, 'enregistrer'])->name('empreinte.enregistrer');
    Route::delete('/mon-compte/empreinte/{cle}', [EmpreinteController::class, 'supprimer'])->whereNumber('cle')->name('empreinte.supprimer');
});
Route::middleware(['guest', 'throttle:10,1'])->group(function (): void {
    Route::get('/connexion/empreinte/options', [EmpreinteController::class, 'optionsConnexion'])->name('empreinte.options-connexion');
    Route::post('/connexion/empreinte', [EmpreinteController::class, 'connecter'])->name('empreinte.connexion');
});
