<?php

namespace App\Filament\Resources\Dispenses;

use App\Filament\Resources\Dispenses\Pages\ListDispenses;
use App\Models\DemandeDispense;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\DispenseService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les demandes de dispense de cotisation : le Jewrine de cellule les fait, le Jewrine national décide. */
class DispenseResource extends Resource
{
    protected static ?string $model = DemandeDispense::class;

    protected static ?string $slug = 'dispenses';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?string $modelLabel = 'demande de dispense';

    protected static ?string $pluralModelLabel = 'demandes de dispense';

    protected static ?string $navigationLabel = 'Dispenses de cotisation';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && ($utilisateur->can('cotisations.dispenser') || app(CasquettesService::class)->peutQuelquePart($utilisateur, 'cotisations.dispense_demander'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $attente = self::utilisateur()?->can('cotisations.dispenser') === true ? DemandeDispense::query()->where('statut', DemandeDispense::EN_ATTENTE)->count() : 0;

        return $attente > 0 ? (string) $attente : null;
    }

    /** @return Builder<DemandeDispense> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<DemandeDispense> $query */
        $query = parent::getEloquentQuery()->with(['membre.cellule', 'demandeur']);
        $utilisateur = self::utilisateur();

        // Le Jewrine de cellule suit les demandes de ses cellules.
        if ($utilisateur !== null && ! $utilisateur->can('cotisations.dispenser') && ! $utilisateur->can('cotisations.dispense_demander')) {
            $cellules = app(CasquettesService::class)->cellulesDirigees($utilisateur);
            $query->whereHas('membre', fn (Builder $membre) => $membre->whereIn('cellule_id', $cellules));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Demandée le')->date('d/m/Y'),
                TextColumn::make('membre.nom_complet')->label('Membre'),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('nombre_mois')->label('Mois')->suffix(' mois'),
                TextColumn::make('motif')->label('Motif')->limit(50),
                TextColumn::make('demandeur.name')->label('Demandée par'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => DemandeDispense::LIBELLES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        DemandeDispense::VALIDEE => 'success',
                        DemandeDispense::REFUSEE => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([SelectFilter::make('statut')->label('État')->options(DemandeDispense::LIBELLES)->default(DemandeDispense::EN_ATTENTE)])
            ->recordActions([
                Action::make('valider')->label('Valider')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (DemandeDispense $record): bool => $record->statut === DemandeDispense::EN_ATTENTE && self::peutDecider($record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (DemandeDispense $record): string => $record->nombre_mois.' mois de dispense pour '.$record->membre->nom_complet.' (les mois de retard d\'abord).')
                    ->action(fn (DemandeDispense $record) => self::executer(fn (DispenseService $service, User $auteur) => $service->valider($record, $auteur), 'Dispense validée.')),
                Action::make('refuser')->label('Refuser')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (DemandeDispense $record): bool => $record->statut === DemandeDispense::EN_ATTENTE && self::peutDecider($record))
                    ->schema([Textarea::make('motif')->label('Motif du refus')->required()->maxLength(255)])
                    ->action(fn (DemandeDispense $record, array $data) => self::executer(fn (DispenseService $service, User $auteur) => $service->refuser($record, (string) $data['motif'], $auteur), 'Demande refusée.')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function peutDecider(DemandeDispense $demande): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && app(DispenseService::class)->peutDecider($utilisateur, $demande);
    }

    /** @param  callable(DispenseService, User): mixed  $operation */
    private static function executer(callable $operation, string $succes): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(DispenseService::class), $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($succes)->success()->send();
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListDispenses::route('/')];
    }
}
