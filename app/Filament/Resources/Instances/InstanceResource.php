<?php

namespace App\Filament\Resources\Instances;

use App\Filament\Resources\Instances\Pages\CreateInstance;
use App\Filament\Resources\Instances\Pages\EditInstance;
use App\Filament\Resources\Instances\Pages\ListInstances;
use App\Filament\Resources\Instances\Pages\ViewInstance;
use App\Models\Collecte;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\User;
use App\Services\ComiteService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Le Comité directeur, les CDGMT et les autres comités, avec leurs commissions et leurs chefs. */
class InstanceResource extends Resource
{
    protected static ?string $model = Instance::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Comités et réunions';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Comités';

    protected static ?string $modelLabel = 'comité';

    protected static ?string $pluralModelLabel = 'comités';

    protected static ?string $recordTitleAttribute = 'nom';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && $utilisateur->can('comites.consulter');
    }

    public static function canView(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && $record instanceof Instance && ComiteService::peutLire($utilisateur, $record);
    }

    public static function canCreate(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && ComiteService::peutCreer($utilisateur);
    }

    public static function canEdit(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && $record instanceof Instance && ! $record->estComiteDirecteur() && ComiteService::peutGerer($utilisateur, $record);
    }

    /** Rien ne se supprime : un comité se clôture (État « Clôturé »). */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le comité')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options(array_diff_key(Instance::TYPES, [Instance::COMITE_DIRECTEUR => true]))
                        ->default(Instance::CDGMT)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('code', $state === Instance::CDGMT ? 'CDGMT' : 'COM');

                            if ($state === Instance::CDGMT) {
                                $set('nom', 'CDGMT – Grand Magal de Touba '.now()->year);
                            }
                        }),
                    TextInput::make('nom')->label('Nom')->required()->maxLength(150)->default('CDGMT – Grand Magal de Touba '.now()->year),
                    TextInput::make('code')->label('Code (numéros des décisions)')->required()->maxLength(15)->default('CDGMT')
                        ->helperText('Exemple : CDGMT donne CDGMT-2026-D-001.'),
                    TextInput::make('annee')->label('Année')->numeric()->required()->default(now()->year),
                    Select::make('collecte_id')->label('Programme lié')->options(fn (): array => Collecte::query()->latest('ouverture')->pluck('titre', 'id')->all())
                        ->placeholder('Aucun')->visible(fn (Get $get): bool => $get('type') === Instance::CDGMT),
                    Select::make('president_id')
                        ->label('Président (membre du Comité directeur)')
                        ->options(fn (): array => ComiteService::choixMembres(Instance::comiteDirecteur()))
                        ->searchable()
                        ->required(),
                    TextInput::make('quorum')->label('Quorum (% des membres)')->numeric()->minValue(1)->maxValue(100)->default(50)->required(),
                    DatePicker::make('mise_en_place')->label('Mise en place le')->native(false)->default(now()),
                    Select::make('statut')->label('État')->options(['active' => 'Actif', 'close' => 'Clôturé'])->default('active')->required(),
                ]),
            Section::make('Les commissions')
                ->description('Elles peuvent changer à chaque Magal. Les chefs et les membres des commissions extérieurs au comité y sont ajoutés automatiquement.')
                ->schema([
                    Repeater::make('commissions')
                        ->relationship('commissions')
                        ->label('')
                        ->orderColumn('ordre')
                        ->addActionLabel('Ajouter une commission')
                        ->helperText('Une commission enregistrée ne se retire pas d\'ici : si elle est vide, archivez-la depuis le tableau de bord du comité.')
                        ->deleteAction(fn (Action $action): Action => $action->visible(fn (array $arguments): bool => ! str_starts_with((string) ($arguments['item'] ?? ''), 'record-')))
                        ->columns(3)
                        ->schema([
                            TextInput::make('nom')->label('Commission')->required()->maxLength(100)->placeholder('Cuisine'),
                            TextInput::make('mission')->label('Mission')->maxLength(255)->placeholder('Menus, achats, cuisson, service'),
                            Select::make('chef_id')->label('Chef de commission')->options(fn (): array => ComiteService::choixMembres())->searchable(),
                            Select::make('membres')
                                ->label('Membres de la commission')
                                ->relationship('membres', 'nom', modifyQueryUsing: fn (Builder $query) => $query->select(['membres.id', 'membres.prenom', 'membres.nom', 'membres.matricule'])->where('membres.statut', 'valide'))
                                ->getOptionLabelFromRecordUsing(fn (Membre $record): string => $record->prenom.' '.$record->nom.' — '.$record->matricule)
                                ->multiple()
                                ->searchable(['prenom', 'nom', 'matricule'])
                                ->preload()
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('president')->withCount(['membres', 'reunions', 'commissions']))
            ->columns([
                TextColumn::make('nom')->label('Comité')->weight('bold')->searchable(),
                TextColumn::make('type')->label('Type')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Instance::CDGMT ? 'CDGMT' : (Instance::TYPES[$state] ?? $state)),
                TextColumn::make('annee')->label('Année')->sortable(),
                TextColumn::make('president.nom')->label('Président')->placeholder('—')
                    ->formatStateUsing(fn (Instance $record): string => $record->president ? $record->president->prenom.' '.$record->president->nom : '—'),
                TextColumn::make('membres_count')->label('Membres'),
                TextColumn::make('commissions_count')->label('Commissions'),
                TextColumn::make('reunions_count')->label('Réunions'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Actif' : 'Clôturé')
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->recordActions([ViewAction::make()->label('Ouvrir'), EditAction::make()])
            ->defaultSort('annee', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListInstances::route('/'),
            'create' => CreateInstance::route('/create'),
            'view' => ViewInstance::route('/{record}'),
            'edit' => EditInstance::route('/{record}/edit'),
        ];
    }
}
