<?php

namespace App\Http\Controllers;

use App\Models\DemandeSuppression;
use App\Models\JournalActivite;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Droit à l'effacement : le membre demande la suppression de son compte.
 */
class SuppressionCompteController extends Controller
{
    public function create(Request $request): View
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return view('espace.suppression', [
            'demande' => DemandeSuppression::query()
                ->where('user_id', $utilisateur->id)
                ->where('statut', DemandeSuppression::EN_ATTENTE)
                ->latest()
                ->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $donnees = $request->validate([
            'motif' => ['nullable', 'string', 'max:1000'],
            'mot_de_passe' => ['required', 'current_password'],
            'confirmation' => ['accepted'],
        ], [
            'mot_de_passe.required' => 'Indiquez votre mot de passe pour confirmer.',
            'mot_de_passe.current_password' => 'Le mot de passe est incorrect.',
            'confirmation.accepted' => 'Cochez la case pour confirmer que vous avez compris.',
        ]);

        $dejaEnCours = DemandeSuppression::query()
            ->where('user_id', $utilisateur->id)
            ->where('statut', DemandeSuppression::EN_ATTENTE)
            ->exists();

        if ($dejaEnCours) {
            return back()->withErrors(['confirmation' => 'Une demande est déjà en cours de traitement.']);
        }

        $demande = DemandeSuppression::query()->create([
            'user_id' => $utilisateur->id,
            'membre_id' => $utilisateur->membre?->id,
            'motif' => $donnees['motif'] ?? null,
            'statut' => DemandeSuppression::EN_ATTENTE,
        ]);

        JournalActivite::enregistrer($demande, 'demande_suppression');

        $superAdmins = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'super_administrateur'))->get();

        if ($superAdmins->isNotEmpty()) {
            Notification::make()
                ->title('Nouvelle demande de suppression de compte')
                ->body($utilisateur->name.' demande l\'effacement de ses données. À traiter sous 30 jours.')
                ->warning()
                ->sendToDatabase($superAdmins);
        }

        return redirect()->route('espace')
            ->with('succes', 'Votre demande a été transmise au bureau. Elle sera traitée sous 30 jours.');
    }
}
