<?php

namespace App\Filament\Resources\Collectes;

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\StatutMembre;
use App\Filament\Actions\ConservationActions;
use App\Filament\Resources\Collectes\Pages\CreateCollecte;
use App\Filament\Resources\Collectes\Pages\EditCollecte;
use App\Filament\Resources\Collectes\Pages\ListCollectes;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Models\Collecte;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\TypeProgramme;
use App\Models\User;
use App\Services\CollecteService;
use App\Services\ConservationService;
use App\Services\FinanceRapportService;
use App\Services\ProgrammeService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Les événements de la Daara (Magal, Ziar, Prokhane, Gamou…), réglés d'un seul formulaire par le super administrateur
 * ou le Jewrine national ; le bureau suit l'avancement. Les collectes de projet sont à part (avec leur projet).
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

    /** @return Builder<Collecte> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Collecte> $query */
        $query = parent::getEloquentQuery()->whereNotNull('type_programme_id')->with('type');

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('L\'événement')
                ->description('Tout se règle ici, une seule fois : les membres, le jour J et les rapports le reprennent.')
                ->columns(2)
                ->schema([
                    Select::make('type_programme_id')
                        ->label('Type')
                        ->options(fn (?Collecte $record): array => TypeProgramme::query()
                            ->where(fn ($query) => $query->whereNull('archive_le')->when($record?->type_programme_id !== null, fn ($actuel) => $actuel->orWhere('id', $record?->type_programme_id)))
                            ->orderBy('ordre')->orderBy('nom')->pluck('nom', 'id')->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            $type = TypeProgramme::query()->whereKey((int) $state)->first();

                            if ($type === null) {
                                return;
                            }

                            if (blank($get('titre'))) {
                                $set('titre', $type->nom.' '.now()->year);
                            }

                            if (blank($get('organisation'))) {
                                $set('organisation', in_array($type->code, [TypeProgramme::MAGAL, TypeProgramme::ZIAR], true) ? Collecte::COMITE : Collecte::COMMISSIONS);
                            }
                        }),
                    TextInput::make('titre')->label('Nom')->required()->maxLength(150),
                    Textarea::make('description')
                        ->label('Description (visible par les membres)')
                        ->rows(3)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    DateTimePicker::make('debut')->label('Début')->required()->native(false)->seconds(false),
                    DateTimePicker::make('fin')->label('Fin')->native(false)->seconds(false)->afterOrEqual('debut'),
                    TextInput::make('lieu')->label('Lieu')->required()->maxLength(150),
                    Select::make('cellule_id')
                        ->label('Membres concernés')
                        ->relationship('cellule', 'nom', modifyQueryUsing: fn ($query) => $query->whereNull('archive_le'))
                        ->placeholder('Toute la Daara'),
                    Toggle::make('avec_jour_j')
                        ->label('Jour J avec scan des cartes')
                        ->helperText('Le jour de l\'événement, les pointeurs scannent les cartes à l\'entrée. Laissez désactivé sans contrôle des présences.'),
                    Toggle::make('itineraire_transport')
                        ->label('Grand déplacement : itinéraire et transport')
                        ->helperText('Étapes du voyage, cars et inscriptions : ils arriveront avec l\'espace des grands événements.'),
                ]),

            Section::make('💰 La contribution')
                ->description('Choisie par le super administrateur. Tant qu\'elle n\'est pas réglée, la collecte reste fermée aux membres.')
                ->columns(2)
                ->schema([
                    Radio::make('mode')
                        ->label('Mode de contribution')
                        ->options(ModeCollecte::class)
                        ->disabled(fn (): bool => ! self::peut('programmes.contribution'))
                        ->helperText(fn (): ?string => self::peut('programmes.contribution') ? null : 'Seul le super administrateur choisit le mode ; il est prévenu.')
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('montant')
                        ->label('Sass (FCFA), le même pour toute la Daara')
                        ->helperText('Fixé par le Jewrine national. Chaque membre concerné y est engagé d\'office et peut l\'augmenter ; personne ne s\'engage en dessous.')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn (Get $get): bool => self::mode($get) === ModeCollecte::Sass),
                ]),

            Section::make('🎯 Objectif et budget')
                ->description('Fixés par le Jewrine national.')
                ->columns(2)
                ->schema([
                    TextInput::make('objectif')
                        ->label('Objectif de recettes (FCFA)')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Sans objectif, la jauge suit le total des engagements.'),
                    TextInput::make('budget')
                        ->label('Budget de dépenses (FCFA)')
                        ->numeric()
                        ->minValue(1),
                ]),

            Section::make('🧭 L\'organisation')
                ->schema([
                    Radio::make('organisation')
                        ->label('Qui organise ?')
                        ->options(Collecte::ORGANISATIONS)
                        ->required()
                        ->live(),
                    Select::make('president_id')
                        ->label('Président du comité')
                        ->helperText('Il crée les commissions de l\'événement et en désigne les chefs.')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Membre::query()->where('statut', StatutMembre::Valide->value)
                            ->where(fn ($query) => $query->whereLike('prenom', '%'.$search.'%')->orWhereLike('nom', '%'.$search.'%')->orWhereLike('matricule', '%'.$search.'%'))
                            ->limit(20)->get()->mapWithKeys(fn (Membre $membre): array => [$membre->id => $membre->prenom.' '.$membre->nom.' ('.($membre->matricule ?? $membre->telephone).')'])->all())
                        ->getOptionLabelUsing(fn (mixed $value): ?string => ($membre = Membre::query()->whereKey((int) $value)->first()) !== null ? $membre->prenom.' '.$membre->nom : null)
                        ->visible(fn (Get $get): bool => $get('organisation') === Collecte::COMITE),
                    Repeater::make('commissions')
                        ->label('Commissions permanentes choisies')
                        ->helperText('Chacune reçoit ses tâches et, si vous le voulez, sa part du budget.')
                        ->schema([
                            Select::make('commission_id')->label('Commission')->required()
                                ->options(fn (): array => Commission::query()->orderBy('nom')->pluck('nom', 'id')->all())
                                ->distinct(),
                            TextInput::make('budget')->label('Sa part du budget (FCFA)')->numeric()->minValue(1),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Ajouter une commission')
                        ->visible(fn (Get $get): bool => $get('organisation') === Collecte::COMMISSIONS),
                ]),

            Section::make('La collecte : dates et règles')
                ->columns(2)
                ->schema([
                    DatePicker::make('ouverture')->label('Ouverture des versements')->required()->default(now())->native(false),
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

            Section::make('Visibilité et rappels')
                ->columns(2)
                ->schema([
                    Toggle::make('visible_membres')
                        ->label('Montrer l\'avancement (la jauge) aux membres')
                        ->helperText('Les noms des contributeurs restent réservés au bureau.')
                        ->live(),
                    Toggle::make('rappels_actifs')
                        ->label('Rappels aux membres jusqu\'à la date limite')
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

    private static function peut(string $capacite): bool
    {
        return ($utilisateur = Auth::user()) instanceof User && $utilisateur->can($capacite);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titre')->label('Programme')->searchable()->weight('bold')->limit(40),
                TextColumn::make('debut')->label('Date')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('type.nom')->label('Type')->badge(),
                TextColumn::make('mode')->label('Contribution')->placeholder('À choisir'),
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
                SelectFilter::make('type_programme_id')->label('Type')->relationship('type', 'nom'),
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
            ->modalDescription('Les membres ne pourront plus verser. Le solde rejoindra la caisse générale par une réaffectation, décidée par le Jewrine national et enregistrée par la Finance. Vous pourrez rouvrir la collecte.')
            ->action(function (Collecte $record): void {
                self::executer(function (ProgrammeService $service, User $auteur) use ($record): void {
                    $reaffectation = $service->cloturer($record, $auteur);
                    Notification::make()->title($reaffectation !== null ? 'Événement clos : réaffectation du solde '.$reaffectation->numero.' envoyée à la Finance.' : 'Événement clos.')->success()->send();
                });
            });
    }

    /** Le Jewrine national décide que le solde d'un événement clos rejoint la caisse générale. */
    public static function verserLeSolde(): Action
    {
        return Action::make('verserLeSolde')
            ->label('Verser le solde à la caisse générale')
            ->icon('heroicon-o-arrows-right-left')
            ->color('success')
            ->visible(fn (Collecte $record): bool => ($utilisateur = Auth::user()) instanceof User && app(ProgrammeService::class)->peutVerserLeSolde($utilisateur, $record))
            ->requiresConfirmation()
            ->modalDescription(fn (Collecte $record): string => 'Solde : '.CollecteService::fcfa(app(FinanceRapportService::class)->soldeProgramme($record)).'. La Finance enregistre la réaffectation.')
            ->action(fn (Collecte $record) => self::executer(function (ProgrammeService $service, User $auteur) use ($record): void {
                $reaffectation = $service->verserLeSolde($record, $auteur);
                Notification::make()->title('Réaffectation '.$reaffectation->numero.' décidée : la Finance l\'enregistre.')->success()->send();
            }));
    }

    /** Celui qui pilote l'événement confie une tâche à l'une des commissions choisies. */
    public static function confierTache(): Action
    {
        return Action::make('confierTache')
            ->label('Confier une tâche')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('gray')
            ->visible(fn (Collecte $record): bool => $record->organisation === Collecte::COMMISSIONS && self::peut('programmes.parametrer') && $record->commissionsChoisies()->whereNull('retiree_le')->exists())
            ->schema(fn (Collecte $record): array => [
                Select::make('commission_id')->label('Commission')->required()
                    ->options($record->commissionsChoisies()->whereNull('retiree_le')->with('commission')->get()->mapWithKeys(fn ($choix): array => [$choix->commission_id => $choix->commission->nom])->all()),
                TextInput::make('description')->label('Tâche')->required()->maxLength(255)->placeholder('Préparer la sonorisation de la nuit'),
                DatePicker::make('echeance')->label('Pour le')->native(false)->minDate(now()->startOfDay()),
            ])
            ->action(fn (Collecte $record, array $data) => self::executer(function (ProgrammeService $service, User $auteur) use ($record, $data): void {
                $commission = Commission::query()->whereKey((int) $data['commission_id'])->firstOrFail();
                $service->confierTache($record, $commission, (string) $data['description'], filled($data['echeance'] ?? null) ? (string) $data['echeance'] : null, $auteur);
                Notification::make()->title('Tâche confiée : le Jewrine de la commission '.$commission->nom.' est prévenu.')->success()->send();
            }));
    }

    /** @param  callable(ProgrammeService, User): void  $operation */
    public static function executer(callable $operation): void
    {
        $auteur = Auth::user();
        abort_unless($auteur instanceof User, 403);

        try {
            $operation(app(ProgrammeService::class), $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
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
        $ouvertes = Collecte::query()->evenements()->where('statut', StatutCollecte::Ouverte->value)->count();

        return $ouvertes > 0 ? (string) $ouvertes : null;
    }
}
