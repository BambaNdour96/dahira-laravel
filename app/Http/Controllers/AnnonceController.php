<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Annonce;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnonceController extends Controller
{
    public function index(Request $request): View
    {
        $utilisateur = $request->user();
        $membre = $utilisateur instanceof User ? $utilisateur->membre : null;

        abort_unless($membre !== null && $membre->statut === StatutMembre::Valide, 404);

        return view('espace.annonces', [
            'annonces' => Annonce::query()->enCours()->paginate(10),
        ]);
    }
}
