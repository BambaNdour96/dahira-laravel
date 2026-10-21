<?php

namespace App\Filament\Resources\OrdresDepense;

use App\Filament\Resources\OrdresDepense\Pages\ListOrdresDepense;
use App\Models\Caisse;
use App\Models\LibelleDepense;
use App\Models\OrdreDepense;
use App\Models\User;
use App\Services\OrdreDepenseService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/** Les ordres de dépense : demander, signer, exécuter. Aucun argent ne sort sans eux. */
class OrdreDepenseResource extends Resource
{
    protected static ?string $model = OrdreDepense::class;

    protected static ?string $slug = 'ordres-depense';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'ordre de dépense';

    protected static ?string $pluralModelLabel = 'ordres de dépense';

    protected static ?string $navigationLabel = 'Ordres de dépense';

    public static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    /** Voir tous les ordres : la Finance, le Jewrine national, ceux qui voient les finances. */
    private static function voitTout(User $utilisateur): bool
    {
        return $utilisateur->can('depenses.signer') || $utilisateur->can('depenses.executer') || $utilisateur->can('finances.voir');
    }

    public static function canViewAny(): bool
    {
        $utilisateur = self::utilisateur();

        return $utilisateur !== null && (self::voitTout($utilisateur) || app(OrdreDepenseService::class)->peutDemander($utilisateur));
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

    public static function getNavigationBadge(): ?string
    {
        $utilisateur = self::utilisateur();
        $attente = match (true) {
            $utilisateur?->can('depenses.signer') === true => OrdreDepense::query()->where('statut', OrdreDepense::DEMANDE)->count(),
            $utilisateur?->can('depenses.executer') === true => OrdreDepense::query()->where('statut', OrdreDepense::SIGNE)->count(),
            default => 0,
        };

        return $attente > 0 ? (string) $attente : null;
    }

    /** @return Builder<OrdreDepense> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<OrdreDepense> $query */
        $query = parent::getEloquentQuery()->with(['libelle', 'demandeur', 'collecte', 'commissionComite', 'projet', 'cellule', 'commission', 'caisse']);
        $utilisateur = self::utilisateur();

        if ($utilisateur !== null && ! self::voitTout($utilisateur)) {
            $query->where('demande_par', $utilisateur->id);
        }

        return $query;
    }

    public static function demander(): Action
    {
        return Action::make('demander')
            ->label('Demander un ordre de dépense')
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => ($utilisateur = self::utilisateur()) !== null && app(OrdreDepenseService::class)->peutDemander($utilisateur))
            ->modalDescription('L\'ordre part au Jewrine national pour signature, puis à la Finance pour paiement. Aucun argent ne sort sans lui.')
            ->schema(function (): array {
                $utilisateur = self::utilisateur();
                $choix = $utilisateur !== null ? app(OrdreDepenseService::class)->choix($utilisateur) : ['collectes' => [], 'commissions_comite' => [], 'projets' => [], 'cellules' => [], 'commissions' => []];

                return [
                    TextInput::make('objet')->label('Objet')->required()->maxLength(255)->placeholder('Achat de 40 bols pour la cuisine'),
                    Select::make('libelle_depense_id')->label('Libellé')->required()->options(fn (): array => LibelleDepense::query()->enActivite()->orderBy('ordre')->pluck('nom', 'id')->all()),
                    TextInput::make('montant')->label('Montant (FCFA)')->required()->numeric()->minValue(1),
                    TextInput::make('beneficiaire')->label('Bénéficiaire (fournisseur ou personne)')->required()->maxLength(150),
                    Select::make('rattachement')->label('Rattachée à')->required()->live()->options(OrdreDepense::RATTACHEMENTS),
                    Select::make('collecte_id')->label('Programme')->options($choix['collectes'])->visible(fn (Get $get): bool => $get('rattachement') === 'programme' && $choix['collectes'] !== []),
                    Select::make('commission_comite_id')->label('Commission du comité')->options($choix['commissions_comite'])->visible(fn (Get $get): bool => $get('rattachement') === 'programme'),
                    Select::make('projet_id')->label('Projet')->options($choix['projets'])->visible(fn (Get $get): bool => $get('rattachement') === 'projet'),
                    Select::make('cellule_id')->label('Cellule (facultatif)')->options($choix['cellules'])->visible(fn (Get $get): bool => $get('rattachement') === 'fonctionnement'),
                    Select::make('commission_id')->label('Commission permanente (facultatif)')->options($choix['commissions'])
                        ->helperText(fn (Get $get): ?string => $get('rattachement') === 'programme' ? 'Pour une commission choisie pour organiser ce programme.' : null)
                        ->visible(fn (Get $get): bool => in_array($get('rattachement'), ['fonctionnement', 'programme'], true)),
                    FileUpload::make('devis')->label('Devis ou facture pro forma (facultatif)')->disk('local')->directory('depenses')->visibility('private')->maxSize(5120)->storeFiles(false),
                ];
            })
            ->action(function (array $data): void {
                self::executer(function (OrdreDepenseService $service, User $auteur) use ($data): void {
                    $devis = $data['devis'] ?? null;
                    $ordre = $service->demander($data, $devis instanceof UploadedFile ? $devis : null, $auteur);
                    Notification::make()->title('Ordre '.$ordre->numero.' envoyé au Jewrine national.')->success()->send();
                });
            });
    }

    /** @param  callable(OrdreDepenseService, User): void  $operation */
    private static function executer(callable $operation): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(OrdreDepenseService::class), $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('Ordre')->weight('bold')->searchable(),
                TextColumn::make('created_at')->label('Demandé le')->date('d/m/Y')->sortable(),
                TextColumn::make('objet')->label('Objet')->limit(40)->searchable(),
                TextColumn::make('libelle.nom')->label('Libellé'),
                TextColumn::make('imputation')->label('Rattachement')->state(fn (OrdreDepense $record): string => $record->imputation())->limit(40),
                TextColumn::make('montant')->label('Montant')->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' FCFA')->alignEnd(),
                TextColumn::make('demandeur.name')->label('Demandé par'),
                TextColumn::make('statut')->label('État')->badge()
                    ->formatStateUsing(fn (string $state): string => OrdreDepense::LIBELLES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        OrdreDepense::EXECUTE => 'success',
                        OrdreDepense::SIGNE => 'info',
                        OrdreDepense::REFUSE, OrdreDepense::ANNULE => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('statut')->label('État')->options(OrdreDepense::LIBELLES),
                SelectFilter::make('rattachement')->label('Rattachement')->options(OrdreDepense::RATTACHEMENTS),
            ])
            ->recordActions([
                Action::make('signer')->label('Signer')->icon('heroicon-o-pencil')->color('success')
                    ->visible(fn (OrdreDepense $record): bool => ($utilisateur = self::utilisateur()) !== null && app(OrdreDepenseService::class)->peutSigner($utilisateur, $record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (OrdreDepense $record): string => number_format($record->montant, 0, ',', ' ').' FCFA · '.$record->objet.' · '.$record->beneficiaire.' · '.$record->imputation())
                    ->action(fn (OrdreDepense $record) => self::executer(function (OrdreDepenseService $service, User $auteur) use ($record): void {
                        $service->signer($record, $auteur);
                        Notification::make()->title('Ordre signé : la Finance est prévenue.')->success()->send();
                    })),
                Action::make('refuser')->label('Refuser')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (OrdreDepense $record): bool => ($utilisateur = self::utilisateur()) !== null && app(OrdreDepenseService::class)->peutSigner($utilisateur, $record))
                    ->schema([Textarea::make('motif')->label('Motif du refus')->required()->maxLength(255)])
                    ->action(fn (OrdreDepense $record, array $data) => self::executer(function (OrdreDepenseService $service, User $auteur) use ($record, $data): void {
                        $service->refuser($record, (string) $data['motif'], $auteur);
                        Notification::make()->title('Ordre refusé.')->success()->send();
                    })),
                Action::make('executer')->label('Exécuter')->icon('heroicon-o-banknotes')->color('success')
                    ->visible(fn (OrdreDepense $record): bool => ($utilisateur = self::utilisateur()) !== null && app(OrdreDepenseService::class)->peutExecuter($utilisateur, $record))
                    ->modalDescription('L\'argent sort de la caisse choisie. Joignez la preuve : reçu, facture, capture du transfert.')
                    ->schema([
                        Select::make('caisse_id')->label('Payé depuis la caisse')->required()
                            ->options(fn (): array => Caisse::query()->enActivite()->orderBy('ordre')->get()->mapWithKeys(fn (Caisse $caisse): array => [$caisse->id => $caisse->nom.' · '.number_format($caisse->solde(), 0, ',', ' ').' FCFA'])->all()),
                        FileUpload::make('preuve')->label('Preuve du paiement')->required()->disk('local')->directory('depenses')->visibility('private')->maxSize(5120)->storeFiles(false),
                    ])
                    ->action(fn (OrdreDepense $record, array $data) => self::executer(function (OrdreDepenseService $service, User $auteur) use ($record, $data): void {
                        $preuve = $data['preuve'] ?? null;
                        abort_unless($preuve instanceof UploadedFile, 422);
                        $service->executer($record, Caisse::query()->whereKey($data['caisse_id'])->firstOrFail(), $preuve, $auteur);
                        Notification::make()->title('Ordre exécuté : la dépense est sortie de la caisse.')->success()->send();
                    })),
                Action::make('annuler')->label('Annuler')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                    ->visible(fn (OrdreDepense $record): bool => $record->statut === OrdreDepense::DEMANDE && $record->demande_par === self::utilisateur()?->id)
                    ->requiresConfirmation()
                    ->action(fn (OrdreDepense $record) => self::executer(fn (OrdreDepenseService $service, User $auteur) => $service->annuler($record, $auteur))),
                Action::make('devis')->label('Devis')->icon('heroicon-o-paper-clip')->color('gray')
                    ->visible(fn (OrdreDepense $record): bool => $record->devis_path !== null)
                    ->url(fn (OrdreDepense $record): string => route('ordres-depense.piece', [$record, 'devis']), true),
                Action::make('preuve')->label('Preuve')->icon('heroicon-o-document')->color('gray')
                    ->visible(fn (OrdreDepense $record): bool => $record->preuve_path !== null && Storage::disk('local')->exists($record->preuve_path))
                    ->url(fn (OrdreDepense $record): string => route('ordres-depense.piece', [$record, 'preuve']), true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListOrdresDepense::route('/')];
    }
}
