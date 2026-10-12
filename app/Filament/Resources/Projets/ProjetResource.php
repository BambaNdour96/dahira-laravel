<?php

namespace App\Filament\Resources\Projets;

use App\Enums\ResultatProjet;
use App\Enums\StatutMembre;
use App\Enums\StatutProjet;
use App\Filament\Resources\Projets\Pages\CreateProjet;
use App\Filament\Resources\Projets\Pages\EditProjet;
use App\Filament\Resources\Projets\Pages\ListProjets;
use App\Filament\Resources\Projets\Pages\ViewProjet;
use App\Models\Membre;
use App\Models\Projet;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Les projets de la Daara, suivis par la commission Suivi et Évaluation :
 * calendrier, budget, étapes, indicateurs, journal, feu tricolore et évaluation finale.
 */
class ProjetResource extends Resource
{
    protected static ?string $model = Projet::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'projet';

    protected static ?string $pluralModelLabel = 'projets';

    protected static ?string $recordTitleAttribute = 'titre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le projet')
                ->columns(2)
                ->schema([
                    TextInput::make('titre')->label('Titre')->required()->maxLength(150)->columnSpanFull(),
                    Textarea::make('objectif')->label('Objectif')->required()->rows(3)->maxLength(2000)->columnSpanFull(),
                    Textarea::make('description')->label('Description')->rows(3)->maxLength(5000)->columnSpanFull(),
                    Select::make('commission_id')
                        ->label('Commission concernée')
                        ->relationship('commission', 'nom')
                        ->placeholder('Aucune'),
                    Select::make('responsable_id')
                        ->label('Responsable du projet')
                        ->searchable()
                        ->options(fn (): array => Membre::query()
                            ->where('statut', StatutMembre::Valide->value)
                            ->orderBy('nom')
                            ->get()
                            ->mapWithKeys(fn (Membre $membre): array => [$membre->id => $membre->prenom.' '.$membre->nom])
                            ->all()),
                ]),

            Section::make('Calendrier et budget')
                ->columns(2)
                ->schema([
                    DatePicker::make('date_debut')->label('Date de début')->required()->default(now())->native(false),
                    DatePicker::make('date_fin')->label('Date de fin prévue')->required()->native(false)->afterOrEqual('date_debut'),
                    TextInput::make('budget')->label('Budget prévu (FCFA)')->numeric()->minValue(0),
                    Select::make('collecte_id')
                        ->label('Collecte qui finance le projet')
                        ->relationship('collecte', 'titre')
                        ->placeholder('Aucune'),
                    Select::make('statut')
                        ->label('État')
                        ->options(StatutProjet::class)
                        ->default(StatutProjet::EnCours->value)
                        ->required(),
                ]),

            Section::make('Évaluation finale')
                ->description('À remplir à la fin du projet.')
                ->collapsed()
                ->schema([
                    Radio::make('resultat')->label('Les objectifs ont-ils été atteints ?')->options(ResultatProjet::class),
                    Textarea::make('bilan')->label('Bilan')->rows(4)->maxLength(5000),
                    Textarea::make('lecons')->label('Leçons retenues')->rows(4)->maxLength(5000),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['commission', 'responsable']))
            ->columns([
                TextColumn::make('titre')->label('Projet')->searchable()->weight('bold')->limit(40),
                TextColumn::make('commission.nom')->label('Commission')->placeholder('—'),
                TextColumn::make('responsable')
                    ->label('Responsable')
                    ->state(fn (Projet $record): string => $record->responsable !== null ? $record->responsable->prenom.' '.$record->responsable->nom : '—'),
                TextColumn::make('date_fin')->label('Fin prévue')->date('d/m/Y')->sortable(),
                TextColumn::make('avancement')
                    ->label('Avancement')
                    ->state(fn (Projet $record): string => $record->avancement().' %'),
                TextColumn::make('sante')
                    ->label('Santé')
                    ->badge()
                    ->state(fn (Projet $record): string => $record->sante()->getLabel())
                    ->color(fn (Projet $record): string => $record->sante()->getColor()),
                TextColumn::make('statut')->label('État')->badge(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(StatutProjet::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Tableau de bord'),
                EditAction::make(),
            ])
            ->defaultSort('date_fin');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListProjets::route('/'),
            'create' => CreateProjet::route('/create'),
            'view' => ViewProjet::route('/{record}'),
            'edit' => EditProjet::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $enCours = Projet::query()->where('statut', StatutProjet::EnCours->value)->count();

        return $enCours > 0 ? (string) $enCours : null;
    }
}
