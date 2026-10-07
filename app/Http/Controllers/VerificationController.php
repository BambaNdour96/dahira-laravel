<?php

namespace App\Http\Controllers;

use App\Services\CarteService;
use App\Services\CotisationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page ouverte par le QR code : publique, mais sans aucune donnée sensible.
 */
class VerificationController extends Controller
{
    public function show(string $jeton, CarteService $cartes, CotisationService $cotisations): View
    {
        $carte = $cartes->verifier($jeton);
        $membre = $carte?->membre;

        return view('public.verification', [
            'membre' => $membre,
            'retard' => $membre !== null ? count($cotisations->moisEnRetard($membre)) : 0,
            'photo' => $membre?->photo_path
                ? URL::temporarySignedRoute('verification.photo', now()->addMinutes(5), ['jeton' => $jeton])
                : null,
        ]);
    }

    public function photo(string $jeton, CarteService $cartes): StreamedResponse
    {
        $chemin = $cartes->verifier($jeton)?->membre->photo_path;

        abort_if($chemin === null || ! Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin);
    }
}
