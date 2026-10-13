<?php

use App\Http\Controllers\AbonnementTelephoneController;
use App\Http\Controllers\CarteController;
use App\Http\Controllers\CharteController;
use App\Http\Controllers\ConfirmationEmailController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\CotisationController;
use App\Http\Controllers\EmpreinteController;
use App\Http\Controllers\EngagementController;
use App\Http\Controllers\EnTantQueController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\ExportPresencesController;
use App\Http\Controllers\FicheCollecteController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\MesDonneesController;
use App\Http\Controllers\MesNotificationsController;
use App\Http\Controllers\MesRecusController;
use App\Http\Controllers\MotDePasseController;
use App\Http\Controllers\MotDePasseOublieController;
use App\Http\Controllers\PaiementMembreController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PointageController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\ProgrammeExportController;
use App\Http\Controllers\ProgrammeRapportController;
use App\Http\Controllers\RapportProjetController;
use App\Http\Controllers\RapportSuiviPdfController;
use App\Http\Controllers\RecuController;
use App\Http\Controllers\RelanceController;
use App\Http\Controllers\SuppressionCompteController;
use App\Http\Controllers\VerificationController;
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

    Route::get('/mon-compte/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mon-compte/mot-de-passe', [MotDePasseController::class, 'update'])
        ->middleware('throttle:connexion')
        ->name('mot-de-passe.update');

    Route::get('/mes-cotisations', [CotisationController::class, 'show'])->name('cotisations');
    Route::post('/mes-cotisations', [CotisationController::class, 'declarer'])
        ->middleware('throttle:inscription')
        ->name('cotisations.declarer');
    Route::get('/mes-engagements', [EngagementController::class, 'index'])->name('engagements.index');
    Route::post('/mes-engagements/{collecte}', [EngagementController::class, 'fixer'])
        ->middleware('throttle:inscription')
        ->name('engagements.fixer');
    Route::post('/mes-engagements/{collecte}/versement', [EngagementController::class, 'declarer'])
        ->middleware('throttle:inscription')
        ->name('engagements.versement');
    Route::get('/collectes/fiche-de-parametrage', FicheCollecteController::class)->name('collectes.fiche');
    Route::get('/relances/{membre}/whatsapp', [RelanceController::class, 'whatsapp'])->name('relances.whatsapp');
    Route::get('/relances/engagements/{engagement}/whatsapp', [RelanceController::class, 'collecte'])->name('relances.collecte');
    Route::get('/projets/{projet}/rapport', RapportProjetController::class)->name('projets.rapport');
    Route::get('/rapports/{rapport}/pdf', RapportSuiviPdfController::class)->name('rapports.pdf');
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
