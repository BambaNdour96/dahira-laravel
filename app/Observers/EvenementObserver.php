<?php

namespace App\Observers;

use App\Enums\StatutMembre;
use App\Models\Evenement;
use App\Models\User;
use App\Notifications\NotificationMembre;
use Illuminate\Support\Facades\Notification;

class EvenementObserver
{
    public function created(Evenement $evenement): void
    {
        $destinataires = User::query()
            ->whereHas('membre', fn ($membre) => $membre
                ->where('statut', StatutMembre::Valide->value)
                ->when($evenement->cellule_id !== null, fn ($requete) => $requete->where('cellule_id', $evenement->cellule_id)))
            ->when($evenement->auteur_id !== null, fn ($requete) => $requete->where('id', '!=', $evenement->auteur_id))
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::send($destinataires, new NotificationMembre(
            'Nouvel événement : '.$evenement->titre,
            'Le '.$evenement->debut->translatedFormat('l j F Y à H\hi').', '.$evenement->lieu.'.',
            route('evenements.index'),
            'Voir l\'événement',
        ));
    }
}
