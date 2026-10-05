<?php

namespace App\Filament\Resources\Membres\Actions;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\StatutMembreService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

final class ChangerStatutAction
{
    /** @return list<Action> */
    public static function toutes(): array
    {
        return [
            self::creer(StatutMembre::Valide, 'heroicon-o-check-circle', 'success'),
            self::creer(StatutMembre::ACorriger, 'heroicon-o-pencil-square', 'info'),
            self::creer(StatutMembre::Rejete, 'heroicon-o-x-circle', 'danger'),
            self::creer(StatutMembre::Suspendu, 'heroicon-o-pause-circle', 'gray'),
            self::creer(StatutMembre::Radie, 'heroicon-o-no-symbol', 'danger'),
        ];
    }

    private static function creer(StatutMembre $cible, string $icone, string $couleur): Action
    {
        $libelle = match ($cible) {
            StatutMembre::Valide => 'Valider',
            StatutMembre::ACorriger => 'Demander une correction',
            StatutMembre::Rejete => 'Rejeter',
            StatutMembre::Suspendu => 'Suspendre',
            StatutMembre::Radie => 'Radier',
            StatutMembre::EnAttente => 'Remettre en attente',
        };

        return Action::make('statut_'.$cible->value)
            ->label(fn (Membre $record): string => $cible === StatutMembre::Valide && $record->statut === StatutMembre::Suspendu
                ? 'Réactiver'
                : $libelle)
            ->icon($icone)
            ->color($couleur)
            ->visible(fn (Membre $record): bool => $record->statut->peutPasserA($cible))
            ->requiresConfirmation()
            ->modalDescription($cible === StatutMembre::Radie ? 'Attention : la radiation est définitive.' : null)
            ->schema($cible->exigeMotif() ? [
                Textarea::make('motif')
                    ->label('Motif')
                    ->required()
                    ->maxLength(1000),
            ] : [])
            ->action(function (Membre $record, array $data) use ($cible): void {
                $auteur = Auth::user();

                try {
                    app(StatutMembreService::class)->changer(
                        $record,
                        $cible,
                        $auteur instanceof User ? $auteur : null,
                        $data['motif'] ?? null,
                    );
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Statut mis à jour : '.$cible->getLabel())
                    ->success()
                    ->send();
            });
    }
}
