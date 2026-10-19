<?php

namespace App\Filament\Resources\Paiements;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\CartePhysique;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Renouvellement;
use App\Models\User;
use App\Services\CartePhysiqueService;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Services\RenouvellementService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class PaiementActions
{
    public static function confirmer(): Action
    {
        return Action::make('confirmer')
            ->label('Confirmer')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Paiement $record): bool => $record->statut === StatutPaiement::Declare
                && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalDescription(fn (Paiement $record): string => 'Vérifiez d\'abord dans Wave Business le paiement de '
                .self::fcfa($record->montant).', référence '.$record->reference.($record->numero_wave ? ', payé depuis le '.$record->numero_wave : '').'. '
                .match ($record->objet) {
                    'cotisation' => 'Les '.$record->nombre_mois.' mois seront marqués réglés.',
                    'collecte' => 'Le versement s\'ajoutera à l\'engagement du membre.',
                    default => 'Le membre sera validé et recevra sa carte.',
                })
            ->action(function (Paiement $record): void {
                if (! Gate::allows('update', $record)) {
                    return;
                }

                try {
                    app(PaiementService::class)->confirmer($record, self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(match ($record->objet) {
                        'cotisation' => 'Cotisation confirmée.',
                        'collecte' => 'Versement confirmé, avec son reçu.',
                        default => 'Paiement confirmé : le membre a reçu sa carte.',
                    })
                    ->success()
                    ->send();
            });
    }

    public static function refuser(): Action
    {
        return Action::make('refuser')
            ->label('Refuser')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Paiement $record): bool => $record->statut === StatutPaiement::Declare
                && Gate::allows('update', $record))
            ->schema([
                Textarea::make('motif')
                    ->label('Motif (visible par le membre)')
                    ->placeholder('Exemple : référence introuvable dans Wave Business')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Paiement $record, array $data): void {
                if (! Gate::allows('update', $record)) {
                    return;
                }

                try {
                    app(PaiementService::class)->refuser($record, self::auteur(), (string) $data['motif']);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Déclaration refusée. Le membre pourra déclarer à nouveau.')->success()->send();
            });
    }

    /** Frais de carte réglés en espèces. */
    public static function especes(): Action
    {
        return Action::make('paiementEspeces')
            ->label('Frais de carte en espèces')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Membre $record): bool => in_array($record->statut, [StatutMembre::Accepte, StatutMembre::Expire], true)
                && Gate::allows('create', Paiement::class))
            ->requiresConfirmation()
            ->modalDescription(fn (): string => 'Confirmez avoir reçu '
                .self::fcfa(app(PaiementService::class)->montantFraisCarte())
                .' en espèces. Le membre sera validé et recevra sa carte.')
            ->action(function (Membre $record): void {
                if (! Gate::allows('create', Paiement::class)) {
                    return;
                }

                try {
                    app(PaiementService::class)->enregistrerEspeces($record, self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Paiement enregistré : le membre a reçu sa carte.')->success()->send();
            });
    }

    /** Frais de renouvellement réglés en espèces. */
    public static function renouvellementEspeces(): Action
    {
        return Action::make('renouvellementEspeces')
            ->label('Renouvellement en espèces')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->visible(fn (Membre $record): bool => in_array(app(RenouvellementService::class)->pour($record)?->statut, Renouvellement::A_PAYER, true)
                && Gate::allows('create', Paiement::class))
            ->requiresConfirmation()
            ->modalDescription(fn (): string => 'Confirmez avoir reçu '
                .self::fcfa(app(PaiementService::class)->montantFraisCarte())
                .' en espèces. Si sa carte n\'a pas changé, le membre est renouvelé et reçoit sa nouvelle carte ; sinon, son Jewrine revalide.')
            ->action(function (Membre $record): void {
                try {
                    app(RenouvellementService::class)->enregistrerEspeces($record, self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Renouvellement encaissé.')->success()->send();
            });
    }

    /** Carte en plastique réglée en espèces : elle attend le prochain lot. */
    public static function cartePhysiqueEspeces(): Action
    {
        return Action::make('cartePhysiqueEspeces')
            ->label('Carte en plastique en espèces')
            ->icon('heroicon-o-identification')
            ->color('success')
            ->visible(fn (Membre $record): bool => $record->statut === StatutMembre::Valide
                && app(CartePhysiqueService::class)->tarif() !== null
                && app(CartePhysiqueService::class)->commandeEnCours($record) === null
                && Gate::allows('create', Paiement::class))
            ->requiresConfirmation()
            ->modalDescription(fn (): string => 'Confirmez avoir reçu '.self::fcfa((int) app(CartePhysiqueService::class)->tarif()).' en espèces. La carte entrera dans le prochain lot d\'impression.')
            ->action(function (Membre $record): void {
                try {
                    app(CartePhysiqueService::class)->enregistrerEspeces($record, self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Carte en plastique payée : elle attend le prochain lot.')->success()->send();
            });
    }

    /** La carte en plastique est remise au membre. */
    public static function remettreCartePhysique(): Action
    {
        return Action::make('remettreCartePhysique')
            ->label('Remettre la carte en plastique')
            ->icon('heroicon-o-hand-raised')
            ->color('gray')
            ->visible(fn (Membre $record): bool => app(CartePhysiqueService::class)->commandeEnCours($record)?->statut === CartePhysique::PRETE)
            ->requiresConfirmation()
            ->action(function (Membre $record): void {
                $commande = app(CartePhysiqueService::class)->commandeEnCours($record);
                $auteur = self::auteur();

                try {
                    abort_unless($commande !== null && $auteur !== null, 404);
                    app(CartePhysiqueService::class)->remettre($commande, $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Carte en plastique remise.')->success()->send();
            });
    }

    /** Cotisations réglées en espèces. */
    public static function cotisationEspeces(): Action
    {
        return Action::make('cotisationEspeces')
            ->label('Cotisation en espèces')
            ->icon('heroicon-o-calendar-days')
            ->color('success')
            ->visible(fn (Membre $record): bool => $record->statut === StatutMembre::Valide
                && Gate::allows('create', Paiement::class))
            ->schema([
                Select::make('nombre_mois')
                    ->label('Nombre de mois réglés')
                    ->options(fn (): array => collect(CotisationService::CHOIX_MOIS)
                        ->mapWithKeys(fn (int $nombre): array => [
                            $nombre => $nombre.' mois — '.self::fcfa($nombre * app(CotisationService::class)->montantMensuel()),
                        ])
                        ->all())
                    ->required()
                    ->helperText('Le paiement couvre d\'abord les mois de retard.'),
            ])
            ->action(function (Membre $record, array $data): void {
                if (! Gate::allows('create', Paiement::class)) {
                    return;
                }

                try {
                    app(CotisationService::class)->enregistrerEspeces($record, (int) $data['nombre_mois'], self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Cotisation enregistrée.')->success()->send();
            });
    }

    /** Télécharger le reçu d'un paiement confirmé. */
    public static function recu(): Action
    {
        return Action::make('recu')
            ->label('Reçu')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn (Paiement $record): bool => $record->numero_recu !== null)
            ->url(fn (Paiement $record): string => route('recus.show', $record))
            ->openUrlInNewTab();
    }

    /** Dispense de cotisation, réservée au Jewrine national et au super administrateur. */
    public static function exoneration(): Action
    {
        return Action::make('exonerer')
            ->label('Dispenser de cotisation')
            ->icon('heroicon-o-gift')
            ->color('info')
            ->visible(fn (Membre $record): bool => $record->statut === StatutMembre::Valide && self::peutDispenser())
            ->schema([
                Select::make('nombre_mois')
                    ->label('Nombre de mois dispensés')
                    ->options(collect(range(1, 12))->mapWithKeys(fn (int $n): array => [$n => $n.' mois'])->all())
                    ->required()
                    ->helperText('Les mois de retard sont dispensés en premier.'),
                Textarea::make('motif')
                    ->label('Motif')
                    ->placeholder('Exemple : maladie, étudiant sans revenu')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Membre $record, array $data): void {
                if (! self::peutDispenser()) {
                    return;
                }

                try {
                    app(CotisationService::class)->exonerer($record, (int) $data['nombre_mois'], (string) $data['motif']);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Dispense enregistrée.')->success()->send();
            });
    }

    private static function peutDispenser(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->can('cotisations.dispenser');
    }

    private static function fcfa(int $montant): string
    {
        return number_format($montant, 0, ',', ' ').' FCFA';
    }

    private static function auteur(): ?User
    {
        $auteur = Auth::user();

        return $auteur instanceof User ? $auteur : null;
    }
}
