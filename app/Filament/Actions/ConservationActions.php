<?php

namespace App\Filament\Actions;

use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\CommissionComite;
use App\Models\Indicateur;
use App\Models\Jalon;
use App\Models\RepresentantFinance;
use App\Models\User;
use App\Services\ConservationService;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Les actions qui remplacent la suppression (« rien ne se perd ») : annuler, archiver, mettre fin.
 * La règle métier vit dans ConservationService ; ici, seulement l'écran.
 */
final class ConservationActions
{
    /**
     * Annuler avec un motif. $annuler reçoit l'enregistrement, le motif et l'auteur.
     *
     * @param  Closure(Model): bool  $peut
     * @param  Closure(Model, string, User): void  $annuler
     */
    public static function annuler(Closure $peut, Closure $annuler, string $explication = 'Rien n\'est effacé : l\'annulation, son motif et son auteur restent visibles.'): Action
    {
        return Action::make('annuler')
            ->label('Annuler')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Model $record): bool => self::utilisateur() !== null && ! (bool) $record->getAttribute('annule_le') && $peut($record))
            ->modalHeading('Annuler')
            ->modalDescription($explication)
            ->modalSubmitActionLabel('Confirmer l\'annulation')
            ->schema([
                Textarea::make('motif')->label('Motif de l\'annulation')->required()->maxLength(255)->rows(2),
            ])
            ->action(function (Model $record, array $data) use ($peut, $annuler): void {
                $utilisateur = self::utilisateur();

                if ($utilisateur === null || ! $peut($record)) {
                    return;
                }

                self::executer(fn () => $annuler($record, (string) $data['motif'], $utilisateur), 'Annulation enregistrée.');
            });
    }

    /** @param  Closure(Model): bool  $peut */
    public static function archiver(Closure $peut): Action
    {
        return Action::make('archiver')
            ->label('Archiver')
            ->icon('heroicon-o-archive-box')
            ->color('gray')
            ->visible(fn (Model $record): bool => self::utilisateur() !== null && ! (bool) $record->getAttribute('archive_le') && $peut($record))
            ->requiresConfirmation()
            ->modalDescription('L\'élément disparaît des listes de choix mais reste consultable avec le filtre « Archivés ». Rien n\'est effacé.')
            ->action(function (Model $record) use ($peut): void {
                $utilisateur = self::utilisateur();

                if ($utilisateur === null || ! $peut($record) || ! self::estArchivable($record)) {
                    return;
                }

                self::executer(fn () => app(ConservationService::class)->archiver($record, $utilisateur), 'Archivé.');
            });
    }

    /** @param  Closure(Model): bool  $peut */
    public static function desarchiver(Closure $peut): Action
    {
        return Action::make('desarchiver')
            ->label('Désarchiver')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->visible(fn (Model $record): bool => (bool) $record->getAttribute('archive_le') && $peut($record))
            ->requiresConfirmation()
            ->action(function (Model $record) use ($peut): void {
                if (! $peut($record) || ! self::estArchivable($record)) {
                    return;
                }

                self::executer(fn () => app(ConservationService::class)->desarchiver($record), 'De nouveau en activité.');
            });
    }

    /** @param  Closure(Model): bool  $peut */
    public static function mettreFin(Closure $peut): Action
    {
        return Action::make('mettreFin')
            ->label('Mettre fin')
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->visible(fn (Model $record): bool => ! (bool) $record->getAttribute('fin_le') && $peut($record))
            ->requiresConfirmation()
            ->modalDescription('La désignation prend fin aujourd\'hui. Elle reste dans l\'historique.')
            ->action(function (Model $record) use ($peut): void {
                $utilisateur = self::utilisateur();

                if ($utilisateur === null || ! $peut($record) || ! ($record instanceof CellulePointeur || $record instanceof RepresentantFinance)) {
                    return;
                }

                self::executer(fn () => app(ConservationService::class)->mettreFin($record, $utilisateur), 'La désignation a pris fin.');
            });
    }

    /** @phpstan-assert-if-true Cellule|CommissionComite|Jalon|Indicateur $record */
    private static function estArchivable(Model $record): bool
    {
        return $record instanceof Cellule || $record instanceof CommissionComite || $record instanceof Jalon || $record instanceof Indicateur;
    }

    private static function executer(Closure $operation, string $succes): void
    {
        try {
            $operation();
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($succes)->success()->send();
    }

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }
}
