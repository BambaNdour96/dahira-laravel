<?php

use App\Http\Controllers\CarteController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfilController;
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

    Route::view('/mot-de-passe-oublie', 'public.mot-de-passe-oublie')->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('logout');
    Route::view('/inscription/merci', 'public.merci')->name('inscription.merci');

    Route::get('/mon-espace', [EspaceController::class, 'show'])->name('espace');
    Route::get('/mon-espace/profil', [ProfilController::class, 'edit'])->name('espace.profil');
    Route::put('/mon-espace/profil', [ProfilController::class, 'update'])->name('espace.profil.update');
    Route::get('/ma-carte', [CarteController::class, 'show'])->name('carte');

    Route::get('/photos/{membre}', PhotoController::class)->name('photos.show');
});
