<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\User;
use App\Services\CarteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarteController extends Controller
{
    public function show(Request $request, CarteService $cartes): View
    {
        $user = $request->user();
        $membre = $user instanceof User
            ? $user->membre()->with(['cellule', 'poste', 'carteActive'])->first()
            : null;

        abort_unless(
            $membre !== null && $membre->statut === StatutMembre::Valide && $membre->carteActive !== null,
            404,
        );

        return view('espace.carte', [
            'membre' => $membre,
            'qr' => $cartes->qrSvg($membre->carteActive),
        ]);
    }
}
