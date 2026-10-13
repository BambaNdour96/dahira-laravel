<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** La charte de confidentialité du bureau : lue et acceptée une fois, la date est gardée. */
class CharteController extends Controller
{
    public function show(): View
    {
        return view('espace.charte');
    }

    public function accepter(Request $request): RedirectResponse
    {
        $request->validate(['accepte' => ['accepted']], ['accepte.accepted' => 'Cochez la case pour accepter la charte.']);

        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $utilisateur->forceFill(['charte_acceptee_le' => now()])->save();

        return redirect()->intended('/admin');
    }
}
