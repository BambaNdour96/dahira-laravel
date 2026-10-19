<?php

namespace App\Filament\Resources\ImportsMembres;

use App\Filament\Resources\ImportsMembres\Pages\ListImportsMembres;
use App\Filament\Resources\ImportsMembres\Pages\ViewImportMembres;
use App\Models\ImportMembres;
use App\Models\User;
use App\Services\ImportMembresService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** La reprise des listes existantes : analyser un fichier, lire le rapport, puis importer les lignes prêtes. */
class ImportMembresResource extends Resource
{
    protected static ?string $model = ImportMembres::class;

    protected static ?string $slug = 'reprise-des-donnees';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'import de membres';

    protected static ?string $pluralModelLabel = 'imports de membres';

    protected static ?string $navigationLabel = 'Reprise des données';

    public static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && app(ImportMembresService::class)->peutImporter($utilisateur);
    }

    public static function canView(Model $record): bool
    {
        $utilisateur = self::utilisateur();

        return self::canViewAny() && $record instanceof ImportMembres && ($utilisateur?->can('membres.importer') === true || $record->auteur_id === $utilisateur?->id);
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

    /** @return Builder<ImportMembres> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ImportMembres> $query */
        $query = parent::getEloquentQuery()->with('auteur');
        $utilisateur = self::utilisateur();

        if ($utilisateur !== null && ! $utilisateur->can('membres.importer')) {
            $query->where('auteur_id', $utilisateur->id);
        }

        return $query;
    }

    public static function analyser(): Action
    {
        return Action::make('analyser')
            ->label('Analyser un fichier')
            ->icon('heroicon-o-magnifying-glass')
            ->modalDescription('Rien n\'est enregistré à cette étape : la plateforme lit le fichier et prépare un rapport ligne par ligne. Vous importerez ensuite les lignes prêtes.')
            ->schema([
                FileUpload::make('fichier')
                    ->label('Fichier CSV (Excel : « Enregistrer sous » → « CSV UTF-8 »)')
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    // Le type d'un CSV se devine mal d'après son contenu (Excel, encodages) : on contrôle l'extension.
                    ->rules(['extensions:csv,txt'])
                    ->validationMessages(['extensions' => 'Déposez un fichier .csv (Excel : « Enregistrer sous » → « CSV UTF-8 »).'])
                    ->maxSize(5120)
                    ->storeFileNamesIn('nom_fichier')
                    ->required(),
            ])
            ->action(function (array $data, $livewire): void {
                $auteur = self::utilisateur();
                abort_unless($auteur !== null, 403);

                try {
                    $import = app(ImportMembresService::class)->analyser((string) $data['fichier'], (string) ($data['nom_fichier'] ?? $data['fichier']), $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $livewire->redirect(self::getUrl('view', ['record' => $import]));
            });
    }

    public static function modele(): Action
    {
        return Action::make('modele')
            ->label('Télécharger le modèle')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->url(route('reprise.modele'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([View::make('filament.reprise-rapport')->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Analysé le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('fichier_nom')->label('Fichier')->limit(40),
                TextColumn::make('auteur.name')->label('Par'),
                TextColumn::make('lignes_total')->label('Lignes'),
                TextColumn::make('lignes_pretes')->label('Prêtes'),
                TextColumn::make('lignes_importees')->label('Importées'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ImportMembres::IMPORTE ? 'Importé' : 'Analysé, à importer')
                    ->color(fn (string $state): string => $state === ImportMembres::IMPORTE ? 'success' : 'warning'),
            ])
            ->recordActions([ViewAction::make()->label('Rapport')])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListImportsMembres::route('/'),
            'view' => ViewImportMembres::route('/{record}'),
        ];
    }
}
