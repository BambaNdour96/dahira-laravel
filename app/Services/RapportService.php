<?php

namespace App\Services;

use App\Filament\Resources\Rapports\RapportResource;
use App\Models\RapportSuivi;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Publication d'un rapport rédigé : il devient visible du bureau, qui en est prévenu. */
final class RapportService
{
    public function publier(RapportSuivi $rapport, User $auteur): RapportSuivi
    {
        $rapport->update(['statut' => 'publie', 'publie_le' => now()]);

        $destinataires = User::role(['super_administrateur', 'jewrine_national', 'secretaire_general', 'tresorier'])
            ->where('id', '!=', $auteur->id)
            ->get();

        if ($destinataires->isNotEmpty()) {
            Notification::make()
                ->title('Nouveau rapport : '.$rapport->titre)
                ->body($rapport->type->getLabel().' · '.$rapport->sujet().' · par '.$auteur->name)
                ->info()
                ->actions([
                    Action::make('lire')->label('Lire')->url(RapportResource::getUrl('view', ['record' => $rapport]))->markAsRead(),
                ])
                ->sendToDatabase($destinataires);
        }

        return $rapport;
    }
}
