<?php

namespace App\Filament\Resources\Collectes;

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Collectes\Pages\CreateCollecte;
use App\Filament\Resources\Collectes\Pages\EditCollecte;
use App\Filament\Resources\Collectes\Pages\ListCollectes;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Models\Collecte;
use App\Models\User;
use App\Services\CollecteService;
use App\Services\ConservationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Les collectes de la Daara : Magal de Touba, Ziar annuelle, Prokhane, programmes secondaires et projets.
 * Le super administrateur fixe tous les paramètres ; le bureau suit l'avancement.
 */
class CollecteResource extends Resource
{
    protected static ?string $model = Collecte::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Programmes';

    protected static ?string $modelLabel = 'programme';

    protected static ?string $pluralModelLabel = 'programmes';

    protected static ?string $recordTitleAttribute = 'titre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le programme')
                ->columns(2)
                ->schema([
                    Select::make('programme')
                        ->label('Programme')
                        ->options(ProgrammeCollecte::class)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            $programme = $state instanceof ProgrammeCollecte ? $state : ProgrammeCollecte::tryFrom((string) $state);

                            if ($programme === null) {
                                return;
                            }

                            $set('mode', $programme->modeSuggere()->value);

                            if (blank($get('titre'))) {
                                $set('titre', $programme->getLabel().' '.now()->year);
                            }

                            if ($programme === ProgrammeCollecte::Prokhane && blank($get('montant'))) {
                                $set('montant', 5000);
                            }
                        }),
                    TextInput::make('titre')->label('Titre')->required()->maxLength(150),
                    Textarea::make('description')
                        ->label('Description (visible par les membres)')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    Select::make('cellule_id')
                        ->label('Membres concernés')
                        ->relationship('cellule', 'nom', modifyQueryUsing: fn ($query) => $query->whereNull('archive_le'))
                        ->placeholder('Toute la Daara'),
                ]),

            Section::make('📍 Le jour J (facultatif)')
                ->description('Le rendez-vous lui-même : date, lieu, et scan des cartes des membres à l\'entrée.')
                ->columns(2)
                ->schema([
                    Toggle::make('avec_jour_j')
                        ->label('Ce programme a un jour J')
                        ->helperText('Exemple : le Magal le 12 août à Touba. Laissez désactivé pour une collecte seule (construction, équipement…).')
                        ->live()
                        ->columnSpanFull(),
                    DateTimePicker::make('jour_j_debut')
                        ->label('Début')
                        ->native(false)
                        ->seconds(false)
                        ->visible(fn (Get $get): bool => (bool) $get('avec_jour_j'))
                        ->required(fn (Get $get): bool => (bool) $get('avec_jour_j')),
                    DateTimePicker::make('jour_j_fin')
                        ->label('Fin')
                        ->native(false)
                        ->seconds(false)
                        ->afterOrEqual('jour_j_debut')
                        ->visible(fn (Get $get): bool => (bool) $get('avec_jour_j')),
                    TextInput::make('jour_j_lieu')
                        ->label('Lieu')
                        ->maxLength(150)
                        ->visible(fn (Get $get): bool => (bool) $get('avec_jour_j'))
                        ->required(fn (Get $get): bool => (bool) $get('avec_jour_j')),
                ]),

            Section::make('💰 La collecte : engagement des membres')
                ->columns(2)
                ->schema([
                    Radio::make('mode')
                        ->label('Comment est fixé l\'engagement de chaque membre ?')
                        ->options(ModeCollecte::class)
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('montant')
                        ->label(fn (Get $get): string => self::mode($get) === ModeCollecte::Minimum ? 'Minimum (FCFA)' : 'Montant pour chaque membre (FCFA)')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => in_array(self::mode($get), [ModeCollecte::Minimum, ModeCollecte::Identique], true))
                        ->required(fn (Get $get): bool => in_array(self::mode($get), [ModeCollecte::Minimum, ModeCollecte::Identique], true)),
                    TextInput::make('objectif')
                        ->label('Objectif total (FCFA)')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Facultatif. Sans objectif, la jauge suit le total des engagements.'),
                ]),

            Section::make('Dates et règles')
                ->columns(2)
                ->schema([
                    DatePicker::make('ouverture')->label('Ouverture')->required()->default(now())->native(false),
                    DatePicker::make('date_limite')->label('Date limite')->native(false)->afterOrEqual('ouverture'),
                    Toggle::make('obligatoire')->label('Participation obligatoire')->default(true),
                    Toggle::make('versement_apres_limite')
                        ->label('Accepter les versements après la date limite')
                        ->helperText('Le membre apparaît alors en retard jusqu\'à ce qu\'il ait tout versé.')
                        ->default(true),
                    Select::make('statut')
                        ->label('État')
                        ->options(collect(StatutCollecte::cases())
                            ->reject(fn (StatutCollecte $statut): bool => $statut === StatutCollecte::Annulee)
                            ->mapWithKeys(fn (StatutCollecte $statut): array => [$statut->value => $statut->getLabel()])
                            ->all())
                        ->default(StatutCollecte::Ouverte->value)
                        ->required(),
                ]),

            Section::make('Visibilité')
                ->columns(2)
                ->schema([
                    Toggle::make('visible_membres')
                        ->label('Montrer l\'avancement (la jauge) aux membres')
                        ->helperText('Les noms des contributeurs restent réservés au bureau.')
                        ->live(),
                    Toggle::make('rappels_actifs')
                        ->label('Rappels automatiques aux membres')
                        ->helperText('Fenêtre à l\'ouverture de l\'application et notifications, jamais de nuit ni en double.')
                        ->default(true),
                    DatePicker::make('jauge_visible_depuis')
                        ->label('Jauge visible à partir du')
                        ->helperText('Laisser vide : dès l\'ouverture.')
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('visible_membres')),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->label('Programme')->searchable()->weight('bold')->limit(40),
                TextColumn::make('evenement.debut')->label('Jour J')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('programme')->label('Programme')->badge(),
                TextColumn::make('mode')->label('Engagement'),
                TextColumn::make('statut')->label('État')->badge(),
                TextColumn::make('date_limite')->label('Date limite')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('collecte')
                    ->label('Collecté')
                    ->state(fn (Collecte $record): string => CollecteService::fcfa($record->totalVerse())),
                TextColumn::make('avancement')
                    ->label('Avancement')
                    ->badge()
                    ->state(fn (Collecte $record): string => $record->taux().' %')
                    ->color(fn (Collecte $record): string => match (true) {
                        $record->taux() >= 100 => 'success',
                        $record->taux() >= 50 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(StatutCollecte::class),
                SelectFilter::make('programme')->label('Programme')->options(ProgrammeCollecte::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Tableau de bord'),
                EditAction::make(),
                self::cloturer(),
                self::rouvrir(),
            ])
            ->defaultSort('ouverture', 'desc');
    }

    /** Un programme ne se supprime jamais : il s'annule, avec un motif, tant qu'il n'a reçu aucun versement. */
    public static function annuler(): Action
    {
        return ConservationActions::annuler(
            fn (Model $record): bool => $record instanceof Collecte && Gate::allows('annuler', $record),
            function (Model $record, string $motif, User $auteur): void {
                if ($record instanceof Collecte) {
                    app(ConservationService::class)->annulerProgramme($record, $motif, $auteur);
                }
            },
            'Le programme n\'apparaîtra plus aux membres. Possible seulement s\'il n\'a reçu aucun versement ; sinon, clôturez-le. Rien n\'est effacé.',
        );
    }

    public static function cloturer(): Action
    {
        return Action::make('cloturer')
            ->label('Clôturer')
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->visible(fn (Collecte $record): bool => $record->statut === StatutCollecte::Ouverte && Gate::allows('cloturer', $record))
            ->requiresConfirmation()
            ->modalDescription('Les membres ne pourront plus verser. Vous pourrez rouvrir la collecte plus tard.')
            ->action(fn (Collecte $record) => $record->update(['statut' => StatutCollecte::Cloturee]));
    }

    public static function rouvrir(): Action
    {
        return Action::make('rouvrir')
            ->label('Rouvrir')
            ->icon('heroicon-o-lock-open')
            ->color('success')
            ->visible(fn (Collecte $record): bool => $record->statut === StatutCollecte::Cloturee && Gate::allows('cloturer', $record))
            ->requiresConfirmation()
            ->action(fn (Collecte $record) => $record->update(['statut' => StatutCollecte::Ouverte]));
    }

    private static function mode(Get $get): ?ModeCollecte
    {
        $mode = $get('mode');

        return $mode instanceof ModeCollecte ? $mode : ModeCollecte::tryFrom((string) $mode);
    }

    /** @return array<int, class-string> */
    public static function getRelations(): array
    {
        return [];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListCollectes::route('/'),
            'create' => CreateCollecte::route('/create'),
            'view' => ViewCollecte::route('/{record}'),
            'edit' => EditCollecte::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $ouvertes = Collecte::query()->where('statut', StatutCollecte::Ouverte->value)->count();

        return $ouvertes > 0 ? (string) $ouvertes : null;
    }
}
