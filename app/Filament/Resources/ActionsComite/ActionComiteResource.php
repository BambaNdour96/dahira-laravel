<?php

namespace App\Filament\Resources\ActionsComite;

use App\Filament\Resources\ActionsComite\Pages\ListActionsComite;
use App\Models\ActionComite;
use App\Models\Instance;
use App\Models\User;
use App\Services\ComiteService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Le suivi des actions décidées en réunion : qui, quoi, pour quand, et où on en est. */
class ActionComiteResource extends Resource
{
    protected static ?string $model = ActionComite::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Comités et réunions';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Suivi des actions';

    protected static ?string $modelLabel = 'action';

    protected static ?string $pluralModelLabel = 'actions';

    public static function canViewAny(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->can('comites.consulter');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['instance', 'responsable', 'commission']))
            ->columns([
                TextColumn::make('description')->label('Action')->wrap()->searchable(),
                TextColumn::make('instance.nom')->label('Comité')->limit(25),
                TextColumn::make('qui')->label('Qui')->state(fn (ActionComite $record): string => $record->responsableTexte()),
                TextColumn::make('echeance')->label('Pour le')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('statut')->label('État')->badge()
                    ->state(fn (ActionComite $record): string => $record->estEnRetard() ? 'En retard' : ActionComite::STATUTS[$record->statut])
                    ->color(fn (string $state): string => match ($state) {
                        'Faite' => 'success',
                        'En retard' => 'danger',
                        'En cours' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('instance_id')->label('Comité')->options(fn (): array => Instance::query()->pluck('nom', 'id')->all()),
                SelectFilter::make('statut')->label('État')->options(ActionComite::STATUTS),
                Filter::make('en_retard')->label('En retard')
                    ->query(fn (Builder $query): Builder => $query->whereNotIn('statut', ActionComite::TERMINEES)->whereDate('echeance', '<', now())),
            ])
            ->recordActions([
                Action::make('statut')
                    ->label('Mettre à jour')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (ActionComite $record): bool => ($utilisateur = Auth::user()) instanceof User && ComiteService::peutSuivre($utilisateur, $record))
                    ->fillForm(fn (ActionComite $record): array => ['statut' => $record->statut])
                    ->schema([Select::make('statut')->label('État')->options(ActionComite::STATUTS)->required()])
                    ->action(function (ActionComite $record, array $data): void {
                        $utilisateur = Auth::user();
                        abort_unless($utilisateur instanceof User, 403);

                        try {
                            app(ComiteService::class)->changerStatut($record, (string) $data['statut'], $utilisateur);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Action mise à jour.')->success()->send();
                    }),
            ])
            ->defaultSort('echeance');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListActionsComite::route('/')];
    }
}
