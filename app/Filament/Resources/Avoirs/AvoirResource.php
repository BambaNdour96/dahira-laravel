<?php

namespace App\Filament\Resources\Avoirs;

use App\Filament\Resources\Avoirs\Pages\ListAvoirs;
use App\Models\Avoir;
use App\Models\Collecte;
use App\Models\User;
use App\Services\CorrectionPaiementService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les avoirs des membres : la Finance les impute à la main sur une cotisation ou un Jàyante. */
class AvoirResource extends Resource
{
    protected static ?string $model = Avoir::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'avoir';

    protected static ?string $pluralModelLabel = 'avoirs';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        return self::utilisateur()?->can('paiements.corriger') === true || self::utilisateur()?->can('finances.voir') === true;
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['membre.cellule', 'origine']))
            ->columns([
                TextColumn::make('created_at')->label('Créé le')->date('d/m/Y'),
                TextColumn::make('membre.nom_complet')->label('Membre'),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('montant')->label('Montant')->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' FCFA'),
                TextColumn::make('reste')->label('Reste à imputer')->formatStateUsing(fn (int $state): string => number_format($state, 0, ',', ' ').' FCFA')->weight('bold'),
                TextColumn::make('motif')->label('Motif')->limit(40),
                TextColumn::make('origine.numero_recu')->label('Paiement d\'origine')->placeholder('—'),
            ])
            ->filters([TernaryFilter::make('reste')->label('Reste')->placeholder('Tous')->trueLabel('À imputer')->falseLabel('Imputés')
                ->queries(true: fn (Builder $query) => $query->where('reste', '>', 0), false: fn (Builder $query) => $query->where('reste', 0))->default(true)])
            ->recordActions([
                Action::make('imputer')->label('Imputer')->icon('heroicon-o-arrow-down-on-square')->color('success')
                    ->visible(fn (Avoir $record): bool => $record->reste > 0 && self::utilisateur()?->can('paiements.corriger') === true)
                    ->fillForm(fn (Avoir $record): array => ['montant' => $record->reste])
                    ->schema([
                        Select::make('objet')->label('Imputer sur')->required()->live()->options(['cotisation' => 'Des mois de cotisation', 'collecte' => 'Un Jàyante (programme)']),
                        Select::make('collecte_id')->label('Programme')->options(fn (): array => Collecte::query()->latest('ouverture')->pluck('titre', 'id')->all())
                            ->visible(fn (Get $get): bool => $get('objet') === 'collecte'),
                        TextInput::make('montant')->label('Montant (FCFA)')->required()->numeric()->minValue(1),
                    ])
                    ->action(function (Avoir $record, array $data): void {
                        $auteur = self::utilisateur();
                        abort_unless($auteur !== null, 403);

                        try {
                            $paiement = app(CorrectionPaiementService::class)->imputerAvoir($record, ['objet' => (string) $data['objet'], 'collecte_id' => isset($data['collecte_id']) ? (int) $data['collecte_id'] : null], (int) $data['montant'], $auteur);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Avoir imputé : reçu '.$paiement->numero_recu.'.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListAvoirs::route('/')];
    }
}
