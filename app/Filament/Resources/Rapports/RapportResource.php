<?php

namespace App\Filament\Resources\Rapports;

use App\Enums\TypeRapport;
use App\Filament\Resources\Rapports\Pages\CreateRapport;
use App\Filament\Resources\Rapports\Pages\EditRapport;
use App\Filament\Resources\Rapports\Pages\ListRapports;
use App\Filament\Resources\Rapports\Pages\ViewRapport;
use App\Models\RapportSuivi;
use App\Models\User;
use App\Services\RapportService;
use App\Support\Confidentiel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use UnitEnum;

/** Les rapports rédigés par la commission Suivi et Évaluation, publiés au bureau. */
class RapportResource extends Resource
{
    protected static ?string $model = RapportSuivi::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Vie de la Daara';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Rapports';

    protected static ?string $modelLabel = 'rapport';

    protected static ?string $pluralModelLabel = 'rapports';

    protected static ?string $recordTitleAttribute = 'titre';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le rapport')
                ->columns(2)
                ->schema([
                    TextInput::make('titre')->label('Titre')->required()->maxLength(150)->columnSpanFull(),
                    Select::make('type')->label('Type')->options(TypeRapport::class)->default(TypeRapport::Suivi->value)->required(),
                    Select::make('projet_id')->label('Projet concerné')->relationship('projet', 'titre')->placeholder('Aucun'),
                    Select::make('collecte_id')->label('Collecte concernée')->relationship('collecte', 'titre')->placeholder('Aucune'),
                    DatePicker::make('periode_debut')->label('Période : du')->native(false),
                    DatePicker::make('periode_fin')->label('au')->native(false)->afterOrEqual('periode_debut'),
                ]),
            Section::make('Contenu')
                ->schema([
                    RichEditor::make('contenu')
                        ->label('Constats, points forts, difficultés')
                        ->required()
                        ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'underline', 'bulletList', 'orderedList', 'blockquote', 'link', 'undo', 'redo']),
                    RichEditor::make('recommandations')
                        ->label('Recommandations au bureau')
                        ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'undo', 'redo']),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::visibles($query)->with(['projet', 'collecte', 'auteur']))
            ->columns([
                TextColumn::make('titre')->label('Rapport')->searchable()->weight('bold')->limit(45),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('sujet')->label('Sujet')->state(fn (RapportSuivi $record): string => $record->sujet()),
                TextColumn::make('auteur.name')->label('Auteur')->placeholder('—'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'publie' ? 'Publié' : 'Brouillon')
                    ->color(fn (string $state): string => $state === 'publie' ? 'success' : 'gray'),
                TextColumn::make('publie_le')->label('Publié le')->date('d/m/Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Type')->options(TypeRapport::class),
                SelectFilter::make('statut')->label('État')->options(['brouillon' => 'Brouillon', 'publie' => 'Publié']),
            ])
            ->recordActions([
                ViewAction::make()->label('Lire'),
                EditAction::make(),
                self::publier(),
                self::pdf(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Brouillons visibles de leur auteur, du super administrateur et de la commission ; rapports publiés visibles de tous.
     *
     * @param  Builder<RapportSuivi>  $query
     * @return Builder<RapportSuivi>
     */
    public static function visibles(Builder $query): Builder
    {
        $utilisateur = Auth::user();

        if ($utilisateur instanceof User && ! $utilisateur->can('rapports.tout_lire')) {
            $query->whereRaw('(statut = ? or auteur_id = ?)', ['publie', $utilisateur->id]);
        }

        return $query;
    }

    public static function publier(): Action
    {
        return Action::make('publier')
            ->label('Publier au bureau')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn (RapportSuivi $record): bool => Gate::allows('publier', $record))
            ->requiresConfirmation()
            ->modalDescription('Le rapport sera visible de tout le bureau, qui sera prévenu. Il ne pourra plus être modifié.')
            ->action(function (RapportSuivi $record): void {
                $auteur = Auth::user();
                abort_unless($auteur instanceof User, 403);

                app(RapportService::class)->publier($record, $auteur);
                Notification::make()->title('Rapport publié : le bureau est prévenu.')->success()->send();
            });
    }

    public static function pdf(): Action
    {
        return Action::make('pdf')
            ->label('PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Télécharger le rapport')
            ->modalDescription(Confidentiel::AVERTISSEMENT)
            ->modalSubmitActionLabel('Télécharger')
            ->action(fn (RapportSuivi $record, Component $livewire) => $livewire->redirect(route('rapports.pdf', $record)));
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRapports::route('/'),
            'create' => CreateRapport::route('/create'),
            'view' => ViewRapport::route('/{record}'),
            'edit' => EditRapport::route('/{record}/edit'),
        ];
    }
}
