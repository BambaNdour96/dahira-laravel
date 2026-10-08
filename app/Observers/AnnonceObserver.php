<?php

namespace App\Observers;

use App\Enums\StatutMembre;
use App\Models\Annonce;
use App\Models\User;
use App\Notifications\NotificationMembre;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * À la publication d'une annonce, chaque membre validé est prévenu.
 */
class AnnonceObserver
{
    public function created(Annonce $annonce): void
    {
        $destinataires = User::query()
            ->whereHas('membre', fn ($membre) => $membre->where('statut', StatutMembre::Valide->value))
            ->when($annonce->auteur_id !== null, fn ($requete) => $requete->where('id', '!=', $annonce->auteur_id))
            ->get();

        if ($destinataires->isEmpty()) {
            return;
        }

        Notification::send($destinataires, new NotificationMembre(
            ($annonce->importante ? 'Annonce importante : ' : 'Nouvelle annonce : ').$annonce->titre,
            $annonce->contenu !== null ? Str::limit($annonce->contenu, 200) : 'Une nouvelle affiche de la Daara vous attend dans votre compte.',
            route('annonces.index'),
            'Voir l\'annonce',
        ));
    }
}
