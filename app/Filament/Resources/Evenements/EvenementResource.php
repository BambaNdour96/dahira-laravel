<?php

namespace App\Filament\Resources\Evenements;

use App\Enums\StatutMembre;
use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Evenements\Pages\ManageEvenements;
use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\User;
use App\Services\ConservationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Les événements de la Daara et leurs présences. Réservés au super administrateur (voir EvenementPolicy).
 */
class EvenementResource extends Resource
{
    protected static ?string $model = Evenement::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Événements';

    protected static ?string $modelLabel = 'événement';

    protected static ?string $pluralModelLabel = 'événements';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titre')->label('Titre')->required()->maxLength(150),
            TextInput::make('lieu')->label('Lieu')->required()->maxLength(150),
            DateTimePicker::make('debut')->label('Début')->required()->native(false),
            DateTimePicker::make('fin')->label('Fin')->native(false)->after('debut')
                ->helperText('Facultatif. Sans heure de fin, l\'événement dure jusqu\'à la fin de la journée.'),
            Select::make('cellule_id')
                ->label('Pour qui')
                ->options(fn (): array => Cellule::query()->enActivite()->orderBy('nom')->pluck('nom', 'id')->all())
                ->placeholder('Toute la Daara'),
            Textarea::make('description')->label('Description')->rows(4)->maxLength(5000),
            Select::make('controleurs')
                ->label('Contrôleurs à l\'entrée')
                ->relationship('controleurs', 'name', fn ($query) => $query->whereHas('membre', fn ($membre) => $membre->where('statut', StatutMembre::Valide->value)))
                ->multiple()
                ->searchable()
                ->preload()
                ->helperText('Les membres qui scanneront les cartes à l\'entrée, le jour de l\'événement. Vous pouvez toujours scanner vous-même.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('cellule')->withCount('presences'))
            ->columns([
                TextColumn::make('titre')->label('Titre')->searchable()->limit(40),
                TextColumn::make('debut')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('lieu')->label('Lieu')->limit(30),
                TextColumn::make('cible')->label('Pour qui')->state(fn (Evenement $record): string => $record->libelleCible()),
                TextColumn::make('presences_count')->label('Présents'),
                TextColumn::make('annule_le')->label('')->badge()->color('danger')
                    ->formatStateUsing(fn (): string => 'Annulé')
                    ->tooltip(fn (Evenement $record): ?string => $record->motif_annulation),
                TextColumn::make('participation')
                    ->label('Participation')
                    ->state(function (Evenement $record): string {
                        $concernes = $record->membresConcernes();

                        return $concernes > 0 ? round(((int) $record->getAttribute('presences_count')) / $concernes * 100).' %' : '—';
                    }),
            ])
            ->recordActions([
                Action::make('exporterPresences')
                    ->label('Présences (Excel)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (Evenement $record): string => route('evenements.presences.export', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                ConservationActions::annuler(
                    fn (Model $record): bool => $record instanceof Evenement && Gate::allows('annuler', $record),
                    function (Model $record, string $motif, User $auteur): void {
                        if ($record instanceof Evenement) {
                            app(ConservationService::class)->annulerEvenement($record, $motif, $auteur);
                        }
                    },
                    'L\'événement reste dans l\'historique, avec son motif. Impossible si des présences sont déjà pointées.',
                ),
            ])
            ->defaultSort('debut', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageEvenements::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $utilisateur = auth()->user();

        return $utilisateur instanceof User && $utilisateur->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur', 'suivi_evaluation']);
    }

    /** Les séances de cellule ont leur propre écran. @return \Illuminate\Database\Eloquent\Builder<Evenement> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', '!=', Evenement::SEANCE);
    }
}
