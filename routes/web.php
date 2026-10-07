<?php

use App\Http\Controllers\CarteController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\CotisationController;
use App\Http\Controllers\EnTantQueController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\MotDePasseController;
use App\Http\Controllers\MotDePasseOublieController;
use App\Http\Controllers\PaiementMembreController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RecuController;
use App\Http\Controllers\RelanceController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.accueil')->name('accueil');
Route::view('/confidentialite', 'public.confidentialite')->name('confidentialite');

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

    Route::get('/mon-compte/mot-de-passe', [MotDePasseController::class, 'edit'])->name('mot-de-passe.edit');
    Route::put('/mon-compte/mot-de-passe', [MotDePasseController::class, 'update'])
        ->middleware('throttle:connexion')
        ->name('mot-de-passe.update');

    Route::get('/mes-cotisations', [CotisationController::class, 'show'])->name('cotisations');
    Route::post('/mes-cotisations', [CotisationController::class, 'declarer'])
        ->middleware('throttle:inscription')
        ->name('cotisations.declarer');
    Route::get('/relances/{membre}/whatsapp', [RelanceController::class, 'whatsapp'])->name('relances.whatsapp');
    Route::get('/recus/{paiement}', [RecuController::class, 'show'])->name('recus.show');

    // Super administrateur : voir la plateforme comme un autre compte (lien signé, 5 minutes).
    Route::get('/en-tant-que/{user}', [EnTantQueController::class, 'demarrer'])
        ->middleware('signed')
        ->name('en-tant-que');
    Route::post('/retour-mon-compte', [EnTantQueController::class, 'arreter'])->name('en-tant-que.retour');

    Route::get('/photos/{membre}', PhotoController::class)->name('photos.show');
});
