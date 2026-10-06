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
use Illuminate\Support\Facades\Gate;

final class ChangerStatutAction
{
    /** @return list<Action> */
    public static function toutes(): array
    {
        return [
            self::creer(StatutMembre::Accepte, 'heroicon-o-check-circle', 'success'),
            self::creer(StatutMembre::Valide, 'heroicon-o-check-badge', 'gray'),
            self::creer(StatutMembre::ACorriger, 'heroicon-o-pencil-square', 'info'),
            self::creer(StatutMembre::Rejete, 'heroicon-o-x-circle', 'danger'),
            self::creer(StatutMembre::Suspendu, 'heroicon-o-pause-circle', 'gray'),
            self::creer(StatutMembre::Radie, 'heroicon-o-no-symbol', 'danger'),
        ];
    }

    private static function creer(StatutMembre $cible, string $icone, string $couleur): Action
    {
        $libelle = match ($cible) {
            StatutMembre::Accepte => 'Accepter',
            StatutMembre::Valide => 'Valider sans paiement',
            StatutMembre::ACorriger => 'Demander une correction',
            StatutMembre::Rejete => 'Rejeter',
            StatutMembre::Suspendu => 'Suspendre',
            StatutMembre::Radie => 'Radier',
            StatutMembre::EnAttente => 'Remettre en attente',
            StatutMembre::Expire => 'Faire expirer',
        };

        return Action::make('statut_'.$cible->value)
            ->label(fn (Membre $record): string => $cible === StatutMembre::Valide && $record->statut === StatutMembre::Suspendu
                ? 'Réactiver'
                : $libelle)
            ->icon($icone)
            ->color($couleur)
            ->visible(fn (Membre $record): bool => $record->statut->peutPasserA($cible)
                && Gate::allows('changerStatut', [$record, $cible]))
            ->requiresConfirmation()
            ->modalDescription(match ($cible) {
                StatutMembre::Accepte => 'Le membre aura '.Membre::DELAI_PAIEMENT_JOURS.' jours pour régler les frais de carte.',
                StatutMembre::Valide => 'Le membre sera validé sans payer les frais de carte, et recevra aussitôt sa carte.',
                StatutMembre::Radie => 'Attention : la radiation est définitive.',
                default => null,
            })
            ->schema($cible->exigeMotif() ? [
                Textarea::make('motif')
                    ->label('Motif')
                    ->required()
                    ->maxLength(1000),
            ] : [])
            ->action(function (Membre $record, array $data) use ($cible): void {
                if (! Gate::allows('changerStatut', [$record, $cible])) {
                    Notification::make()->title('Action non autorisée.')->danger()->send();

                    return;
                }

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
