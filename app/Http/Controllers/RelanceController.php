<?php

namespace App\Http\Controllers;

use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\User;
use App\Services\CotisationService;
use App\Support\MessagesRelance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RelanceController extends Controller
{
    /** Note la relance, puis ouvre WhatsApp avec le message déjà rédigé. */
    public function whatsapp(Request $request, Membre $membre, CotisationService $cotisations): RedirectResponse
    {
        $utilisateur = $request->user();

        abort_unless(
            $utilisateur instanceof User
                && $utilisateur->hasAnyRole(['super_administrateur', 'tresorier', 'responsable_section'])
                && Gate::allows('view', $membre),
            403,
        );

        $retard = $cotisations->moisEnRetard($membre);
        $message = MessagesRelance::individuel($membre, $retard, $cotisations->montantMensuel());

        Membre::query()->whereKey($membre->id)->update([
            'relance_le' => now(),
            'relance_par' => $utilisateur->id,
        ]);

        JournalActivite::enregistrer($membre, 'relance', ['apres' => ['whatsapp' => count($retard).' mois de retard']]);

        return redirect()->away('https://wa.me/'.ltrim($membre->telephone, '+').'?text='.rawurlencode($message));
    }
}
