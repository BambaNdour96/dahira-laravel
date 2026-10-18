<?php

namespace App\Filament\Resources\Reunions;

use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Reunions\Pages\CreateReunion;
use App\Filament\Resources\Reunions\Pages\EditReunion;
use App\Filament\Resources\Reunions\Pages\ListReunions;
use App\Filament\Resources\Reunions\Pages\ViewReunion;
use App\Models\Decision;
use App\Models\Instance;
use App\Models\Reunion;
use App\Models\User;
use App\Services\ComiteService;
use App\Services\ConservationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les réunions des comités : ordre du jour, résumés, décisions et actions, puis PV publié. */
class ReunionResource extends Resource
{
    public const ETATS = ['preparee' => 'En préparation', 'convoquee' => 'Convoquée', 'publiee' => 'PV publié', 'annulee' => 'Annulée'];

    protected static ?string $model = Reunion::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Comités et réunions';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Réunions';

    protected static ?string $modelLabel = 'réunion';

    protected static ?string $pluralModelLabel = 'réunions';

    protected static ?string $recordTitleAttribute = 'titre';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && ($utilisateur->can('comites.consulter') || self::instancesOrganisables($utilisateur) !== []);
    }

    /** @return Builder<Reunion> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Reunion> $query */
        $query = parent::getEloquentQuery();
        $utilisateur = self::utilisateur();

        if ($utilisateur !== null && ! $utilisateur->can('comites.consulter')) {
            $query->whereIn('instance_id', array_keys(self::instancesOrganisables($utilisateur)));
        }

        return $query;
    }

    public static function canView(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && $record instanceof Reunion && ComiteService::peutLire($utilisateur, $record->instance);
    }

    public static function canCreate(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && self::instancesOrganisables($utilisateur) !== [];
    }

    public static function canEdit(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && $record instanceof Reunion && ComiteService::peutRediger($utilisateur, $record);
    }

    /** Rien ne se supprime : une réunion s'annule, avec un motif (voir annuler). */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function annuler(): Action
    {
        return ConservationActions::annuler(
            fn (Model $record): bool => ($utilisateur = self::utilisateur()) !== null && $record instanceof Reunion
                && ! $record->estPubliee() && ComiteService::peutOrganiser($utilisateur, $record->instance),
            function (Model $record, string $motif, User $auteur): void {
                if ($record instanceof Reunion) {
                    app(ConservationService::class)->annulerReunion($record, $motif, $auteur);
                }
            },
            'La réunion, ses points et ses décisions restent dans l\'historique. Si elle était convoquée, les membres sont prévenus.',
        );
    }

    /**
     * Une décision ou une action enregistrée ne se retire plus du formulaire : on change son état.
     * Seules les lignes ajoutées et pas encore enregistrées peuvent être retirées.
     */
    private static function seulementLesNouvelles(Action $action): Action
    {
        return $action->visible(fn (array $arguments): bool => ! str_starts_with((string) ($arguments['item'] ?? ''), 'record-'));
    }

    /** @return array<int, string> */
    public static function instancesOrganisables(User $utilisateur): array
    {
        return Instance::query()->where('statut', 'active')->with('president')->get()
            ->filter(fn (Instance $instance): bool => ComiteService::peutOrganiser($utilisateur, $instance))
            ->mapWithKeys(fn (Instance $instance): array => [$instance->id => $instance->nom])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $membresDuComite = fn (Get $get): array => ($instance = Instance::query()->whereKey($get('../../instance_id'))->first()) !== null
            ? ComiteService::choixMembres($instance)
            : [];

        // Dès la convocation, la date, le lieu, l'ordre du jour et le secrétaire ne changent plus ici : l'organisateur
        // les modifie par « Modifier la convocation », qui prévient les membres. Le secrétaire garde le compte rendu.
        $convoquee = fn ($livewire): bool => $livewire instanceof EditRecord
            && ($reunion = $livewire->getRecord()) instanceof Reunion
            && $reunion->convoquee_le !== null;
        $modifiable = fn ($livewire): bool => ! $convoquee($livewire);

        return $schema->components([
            Section::make('La réunion')
                ->columns(2)
                ->schema([
                    Select::make('instance_id')
                        ->label('Comité')
                        ->options(fn (): array => ($utilisateur = self::utilisateur()) !== null ? self::instancesOrganisables($utilisateur) : [])
                        ->default(fn (): ?int => request()->integer('instance') ?: null)
                        ->required()
                        ->live()
                        ->disabledOn('edit'),
                    TextInput::make('titre')->label('Titre')->required()->maxLength(150)->placeholder('Réunion de préparation n° 3'),
                    DateTimePicker::make('debut')->label('Date et heure')->required()->native(false)->seconds(false)->disabled($convoquee),
                    TextInput::make('lieu')->label('Lieu')->required()->maxLength(150)->disabled($convoquee),
                    TextInput::make('lien')->label('Lien de visioconférence (facultatif)')->url()->maxLength(255)->disabled($convoquee),
                    Select::make('secretaire_id')
                        ->label('Secrétaire de séance')
                        ->helperText('Un membre du Comité directeur ; pour une commission permanente, un membre de la commission. Dès la convocation, lui seul rédige et publie le PV.')
                        ->options(fn (Get $get): array => ($instance = Instance::query()->whereKey($get('instance_id'))->first()) !== null
                            ? ComiteService::secretairesPossibles($instance)
                            : ComiteService::choixMembres(Instance::comiteDirecteur()))
                        ->searchable()
                        ->required()
                        ->disabled($convoquee),
                ]),
            Section::make('Ordre du jour et compte rendu')
                ->description('Fixez les points avant la réunion ; complétez le résumé de chaque point pendant ou après.')
                ->schema([
                    Repeater::make('points')
                        ->relationship('points')
                        ->label('')
                        ->orderColumn('ordre')
                        ->addActionLabel('Ajouter un point')
                        ->addable($modifiable)
                        ->deletable($modifiable)
                        ->reorderable($modifiable)
                        ->schema([
                            TextInput::make('titre')->label('Point')->required()->maxLength(200)->disabled($convoquee),
                            Textarea::make('resume')->label('Résumé des échanges')->rows(3),
                        ]),
                ]),
            Section::make('Décisions')
                ->schema([
                    Repeater::make('decisions')
                        ->relationship('decisions')
                        ->defaultItems(0)
                        ->label('')
                        ->addActionLabel('Ajouter une décision')
                        ->deleteAction(fn (Action $action): Action => self::seulementLesNouvelles($action))
                        ->columns(4)
                        ->schema([
                            Textarea::make('texte')->label('Décision')->required()->rows(2)->columnSpanFull(),
                            Select::make('issue')->label('Prise')->options(Decision::ISSUES)->default('unanimite')->required()->columnSpan(2),
                            TextInput::make('numero')->label('Numéro')->disabled()->dehydrated(false)->placeholder('Attribué à l\'enregistrement'),
                        ]),
                ]),
            Section::make('Actions décidées')
                ->schema([
                    Repeater::make('actions')
                        ->relationship('actions')
                        ->defaultItems(0)
                        ->label('')
                        ->addActionLabel('Ajouter une action')
                        ->deleteAction(fn (Action $action): Action => self::seulementLesNouvelles($action))
                        ->columns(4)
                        ->schema([
                            TextInput::make('description')->label('Quoi')->required()->maxLength(255)->columnSpan(2),
                            Select::make('responsable_id')->label('Qui (un membre)')->options($membresDuComite)->searchable(),
                            Select::make('commission_id')->label('Ou une commission')
                                ->options(fn (Get $get): array => ($instance = Instance::query()->whereKey($get('../../instance_id'))->first()) !== null
                                    ? $instance->commissions()->pluck('nom', 'id')->all()
                                    : []),
                            DatePicker::make('echeance')->label('Pour quand')->native(false),
                        ]),
                ]),
            Section::make('Divers')
                ->schema([
                    Textarea::make('divers')->label('')->rows(3),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('instance')->withCount(['decisions', 'actions']))
            ->columns([
                TextColumn::make('debut')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('instance.nom')->label('Comité')->limit(30),
                TextColumn::make('titre')->label('Réunion')->weight('bold')->searchable(),
                TextColumn::make('decisions_count')->label('Décisions'),
                TextColumn::make('actions_count')->label('Actions'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => self::ETATS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'publiee' ? 'success' : ($state === 'convoquee' ? 'info' : 'gray')),
            ])
            ->filters([
                SelectFilter::make('instance_id')->label('Comité')->options(fn (): array => Instance::query()->pluck('nom', 'id')->all()),
            ])
            ->recordActions([ViewAction::make()->label('Ouvrir'), EditAction::make()->label('Rédiger')])
            ->defaultSort('debut', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListReunions::route('/'),
            'create' => CreateReunion::route('/create'),
            'view' => ViewReunion::route('/{record}'),
            'edit' => EditReunion::route('/{record}/edit'),
        ];
    }
}
