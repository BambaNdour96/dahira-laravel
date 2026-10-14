<?php

namespace App\Filament\Resources\Decisions;

use App\Filament\Resources\Decisions\Pages\ListDecisions;
use App\Models\Decision;
use App\Models\Instance;
use App\Models\User;
use App\Services\ComiteService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Le registre de toutes les décisions des comités, numérotées. */
class DecisionResource extends Resource
{
    protected static ?string $model = Decision::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Comités et réunions';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Registre des décisions';

    protected static ?string $modelLabel = 'décision';

    protected static ?string $pluralModelLabel = 'décisions';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['instance', 'reunion']))
            ->columns([
                TextColumn::make('numero')->label('N°')->weight('bold')->searchable()->sortable(),
                TextColumn::make('texte')->label('Décision')->wrap()->searchable()->limit(120),
                TextColumn::make('instance.nom')->label('Comité')->limit(25),
                TextColumn::make('reunion.debut')->label('Réunion du')->date('d/m/Y'),
                TextColumn::make('etat')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => Decision::ETATS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'en_vigueur' => 'success',
                        'modifiee' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('instance_id')->label('Comité')->options(fn (): array => Instance::query()->pluck('nom', 'id')->all()),
                SelectFilter::make('etat')->label('État')->options(Decision::ETATS),
            ])
            ->recordActions([
                Action::make('etat')
                    ->label('Changer l\'état')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Decision $record): bool => ($utilisateur = Auth::user()) instanceof User && ComiteService::peutOrganiser($utilisateur, $record->instance))
                    ->fillForm(fn (Decision $record): array => ['etat' => $record->etat])
                    ->schema([Select::make('etat')->label('État')->options(Decision::ETATS)->required()])
                    ->action(function (Decision $record, array $data): void {
                        $record->update(['etat' => (string) $data['etat']]);
                        Notification::make()->title('Décision '.$record->numero.' : '.Decision::ETATS[$record->etat].'.')->success()->send();
                    }),
            ])
            ->defaultSort('numero', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListDecisions::route('/')];
    }
}
