<?php

namespace App\Filament\Resources\CampagnesRenouvellement;

use App\Filament\Resources\CampagnesRenouvellement\Pages\ListCampagnesRenouvellement;
use App\Filament\Resources\CampagnesRenouvellement\Pages\ViewCampagneRenouvellement;
use App\Models\CampagneRenouvellement;
use App\Models\Exercice;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\RenouvellementService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les campagnes de renouvellement annuel : le super administrateur ouvre, les Jewrine suivent leur cellule et revalident. */
class CampagneRenouvellementResource extends Resource
{
    protected static ?string $model = CampagneRenouvellement::class;

    protected static ?string $slug = 'campagnes-renouvellement';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'campagne de renouvellement';

    protected static ?string $pluralModelLabel = 'campagnes de renouvellement';

    protected static ?string $navigationLabel = 'Renouvellement annuel';

    public static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && ($utilisateur->can('renouvellement.ouvrir') || app(CasquettesService::class)->peutQuelquePart($utilisateur, 'membres.valider'));
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
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

    /**
     * Les cellules que l'on suit : toutes, ou seulement celles que l'on dirige.
     *
     * @return list<int>|null
     */
    public static function cellulesSuivies(): ?array
    {
        $utilisateur = self::utilisateur();

        if ($utilisateur === null || $utilisateur->can('renouvellement.ouvrir') || $utilisateur->can('membres.valider')) {
            return null;
        }

        return app(CasquettesService::class)->cellulesDirigees($utilisateur);
    }

    public static function ouvrir(): Action
    {
        return Action::make('ouvrir')
            ->label('Ouvrir la campagne')
            ->icon('heroicon-o-megaphone')
            ->visible(fn (): bool => self::utilisateur()?->can('renouvellement.ouvrir') === true && CampagneRenouvellement::ouverte() === null)
            ->modalDescription('Tous les membres validés sont concernés (les membres validés depuis moins de 3 mois sont renouvelés d\'office). Chacun est prévenu, puis relancé 30, 7 et 1 jour avant la date limite.')
            ->schema([
                Select::make('exercice_id')->label('Exercice')->required()
                    ->options(fn (): array => Exercice::query()->whereNotIn('id', CampagneRenouvellement::query()->select('exercice_id'))->orderBy('date_debut')->pluck('libelle', 'id')->all()),
                DatePicker::make('date_limite')->label('Date limite')->required()->native(false)->minDate(now()->addDay()),
            ])
            ->modalSubmitActionLabel('Ouvrir et prévenir les membres')
            ->action(function (array $data): void {
                $auteur = self::utilisateur();
                abort_unless($auteur !== null, 403);

                try {
                    app(RenouvellementService::class)->ouvrir(Exercice::query()->whereKey($data['exercice_id'])->firstOrFail(), Carbon::parse((string) $data['date_limite']), $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Campagne ouverte : les membres sont prévenus.')->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('exercice')->withCount('renouvellements'))
            ->columns([
                TextColumn::make('exercice.libelle')->label('Exercice')->weight('bold'),
                TextColumn::make('ouverte_le')->label('Ouverte le')->date('d/m/Y'),
                TextColumn::make('date_limite')->label('Date limite')->date('d/m/Y'),
                TextColumn::make('renouvellements_count')->label('Membres concernés'),
                TextColumn::make('cloturee_le')->label('Close le')->date('d/m/Y')->placeholder('En cours'),
            ])
            ->recordActions([ViewAction::make()->label('Suivre')])
            ->defaultSort('ouverte_le', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListCampagnesRenouvellement::route('/'),
            'view' => ViewCampagneRenouvellement::route('/{record}'),
        ];
    }
}
