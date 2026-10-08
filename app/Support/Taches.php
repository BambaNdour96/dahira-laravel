<?php

namespace App\Support;

use App\Models\Membre;
use App\Models\Paiement;
use App\Models\RegleValidation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Prévient la bonne personne quand une tâche l'attend, dans la cloche du panneau.
 */
final class Taches
{
    /** Une demande à examiner : le Jewrine de la cellule. */
    public static function demandeAExaminer(Membre $membre, string $titre): void
    {
        $jewrine = User::query()->find($membre->cellule->responsable_id);

        if (! $jewrine instanceof User) {
            return;
        }

        self::envoyer(
            collect([$jewrine]),
            $titre,
            $membre->prenom.' '.$membre->nom.', cellule de '.$membre->cellule->nom.'.',
            url('/admin/membres/'.$membre->id.'/edit'),
        );
    }

    /** Un paiement à vérifier : le Jewrine finance, ou les validateurs désignés d'un poste clé du bureau. */
    public static function paiementAVerifier(Paiement $paiement): void
    {
        $payeur = $paiement->membre;
        $regle = $payeur->poste_id !== null
            ? RegleValidation::query()->where('poste_id', $payeur->poste_id)->first()
            : null;

        if ($regle !== null) {
            $titre = 'Paiement d\'un membre du bureau à valider';
            $destinataires = User::query()
                ->whereHas('membre', fn ($membre) => $membre->whereIn('poste_id', $regle->idsValidateurs()))
                ->get();
        } else {
            $titre = 'Paiement à vérifier';
            $destinataires = User::query()
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'tresorier'))
                ->get();
        }

        self::envoyer(
            $destinataires->reject(fn (User $utilisateur): bool => $utilisateur->id === $payeur->user_id),
            $titre,
            $payeur->prenom.' '.$payeur->nom.' : '.$paiement->libelleObjet().', '
                .number_format($paiement->montant, 0, ',', ' ').' FCFA, référence '.$paiement->reference.'.',
            url('/admin/paiements'),
        );
    }

    /** @param  Collection<int, User>  $destinataires */
    public static function envoyer(Collection $destinataires, string $titre, string $corps, string $lien): void
    {
        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($titre)
            ->body($corps)
            ->info()
            ->actions([
                Action::make('voir')->label('Voir')->url($lien)->markAsRead(),
            ])
            ->sendToDatabase($destinataires);
    }
}
