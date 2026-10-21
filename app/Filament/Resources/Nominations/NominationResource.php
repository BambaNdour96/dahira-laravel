<?php

namespace App\Filament\Resources\Nominations;

use App\Enums\StatutMembre;
use App\Filament\Resources\Nominations\Pages\ManageNominations;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\User;
use App\Services\NominationService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Les nominations : qui occupe quel poste, depuis quand, désigné par qui, jusqu'à quand.
 * Le super administrateur et le Jewrine national nomment ; le Jewrine national, seul le super administrateur.
 */
class NominationResource extends Resource
{
    protected static ?string $model = Nomination::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Nominations';

    protected static ?string $modelLabel = 'nomination';

    protected static ?string $pluralModelLabel = 'nominations';

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    public static function canViewAny(): bool
    {
        return self::utilisateur()?->can('nominations.voir') ?? false;
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user.membre', 'poste', 'cellule', 'commission', 'designePar', 'finPar']))
            ->columns([
                TextColumn::make('user.name')->label('Personne')->searchable()->weight('bold'),
                TextColumn::make('user.membre.matricule')->label('Matricule')->placeholder('—'),
                TextColumn::make('poste.nom')->label('Poste')->badge(),
                TextColumn::make('lieu')->label('Cellule ou commission')->placeholder('—')
                    ->state(fn (Nomination $record): ?string => $record->cellule->nom ?? $record->commission->nom ?? null),
                TextColumn::make('debut_le')->label('Depuis le')->date('d/m/Y')->sortable(),
                TextColumn::make('designePar.name')->label('Désigné par')->placeholder('Reprise des données'),
                TextColumn::make('fin_le')->label('Fin le')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('motif_fin')->label('Motif de fin')->placeholder('—')->wrap(),
            ])
            ->filters([
                TernaryFilter::make('historique')
                    ->label('Nominations')
                    ->placeholder('Toutes')
                    ->trueLabel('Terminées')
                    ->falseLabel('En cours')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->withoutGlobalScope(Nomination::EN_COURS)->whereNotNull('fin_le'),
                        false: fn (Builder $query) => $query,
                        blank: fn (Builder $query) => $query->withoutGlobalScope(Nomination::EN_COURS),
                    ),
                SelectFilter::make('poste_id')->label('Poste')->relationship('poste', 'nom'),
            ])
            ->recordActions([self::mettreFin()])
            ->defaultSort('debut_le', 'desc');
    }

    public static function nommer(): Action
    {
        return Action::make('nommer')
            ->label('Nommer')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (): bool => self::utilisateur()?->can('nominations.gerer') ?? false)
            ->modalDescription('Seul un membre validé, avec un compte, peut être nommé. Sur un poste à titulaire unique, la nomination précédente prend fin.')
            ->schema([
                Select::make('user_id')
                    ->label('Personne')
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(fn (string $search): array => self::rechercher($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::libelle((int) $value))
                    ->helperText('Nom, matricule ou téléphone.'),
                Select::make('poste_id')
                    ->label('Poste')
                    ->options(fn (): array => self::postesNommables())
                    ->required()
                    ->live(),
                Select::make('cellule_id')
                    ->label('Cellule')
                    ->options(fn (): array => Cellule::query()->enActivite()->orderBy('nom')->pluck('nom', 'id')->all())
                    ->visible(fn (Get $get): bool => self::estDeCellule($get('poste_id')))
                    ->required(fn (Get $get): bool => self::estDeCellule($get('poste_id'))),
                Select::make('commission_id')
                    ->label('Commission')
                    ->options(fn (): array => Commission::query()->orderBy('nom')->pluck('nom', 'id')->all())
                    ->visible(fn (Get $get): bool => self::portee($get('poste_id')) === 'commission')
                    ->required(fn (Get $get): bool => self::portee($get('poste_id')) === 'commission'),
                DatePicker::make('debut_le')->label('À compter du')->default(now())->required()->native(false),
            ])
            ->action(function (array $data): void {
                $auteur = self::utilisateur();
                $personne = User::query()->whereKey($data['user_id'])->first();
                $poste = Poste::query()->whereKey($data['poste_id'])->first();

                if ($auteur === null || $personne === null || $poste === null) {
                    return;
                }

                $cellule = filled($data['cellule_id'] ?? null) ? Cellule::query()->whereKey($data['cellule_id'])->first() : null;
                $commission = filled($data['commission_id'] ?? null) ? Commission::query()->whereKey($data['commission_id'])->first() : null;

                try {
                    app(NominationService::class)->nommer($personne, $poste, $cellule, $auteur, Carbon::parse((string) $data['debut_le']), $commission);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($personne->name.' est nommé(e) '.$poste->nom.'.')
                    ->body('Ses droits sont mis à jour aussitôt ; il se connecte avec son numéro et son mot de passe habituels.')
                    ->success()
                    ->send();
            });
    }

    private static function mettreFin(): Action
    {
        return Action::make('mettreFin')
            ->label('Mettre fin')
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->visible(fn (Nomination $record): bool => ! $record->estTerminee()
                && ($auteur = self::utilisateur()) !== null && NominationService::peutNommer($auteur, $record->poste))
            ->modalDescription('La personne perd les droits de ce poste, pas ceux de ses autres casquettes. La nomination reste dans l\'historique.')
            ->schema([
                Textarea::make('motif')->label('Motif')->required()->maxLength(255)->rows(2)
                    ->placeholder('Exemple : fin de mandat, démission, remplacement'),
            ])
            ->action(function (Nomination $record, array $data): void {
                $auteur = self::utilisateur();

                if ($auteur === null) {
                    return;
                }

                try {
                    app(NominationService::class)->mettreFin($record, (string) $data['motif'], $auteur);
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('La nomination a pris fin.')->success()->send();
            });
    }

    /**
     * Les postes auxquels l'auteur peut nommer.
     *
     * @return array<int, string>
     */
    private static function postesNommables(): array
    {
        $auteur = self::utilisateur();

        // Le Jewrine du suivi des dépenses se nomme depuis l'espace de son comité (proposition du président).
        return Poste::query()->where('actif', true)->where('perimetre', '!=', 'comite')->orderBy('ordre')->get()
            ->filter(fn (Poste $poste): bool => $auteur !== null && NominationService::peutNommer($auteur, $poste))
            ->mapWithKeys(fn (Poste $poste): array => [$poste->id => $poste->nom])
            ->all();
    }

    /**
     * Les membres validés, avec un compte, qui correspondent à la recherche.
     *
     * @return array<int, string>
     */
    private static function rechercher(string $recherche): array
    {
        return Membre::query()
            ->with('cellule')
            ->where('statut', StatutMembre::Valide->value)
            ->whereNotNull('user_id')
            ->where(fn (Builder $query) => $query
                ->where('prenom', 'like', '%'.$recherche.'%')
                ->orWhere('nom', 'like', '%'.$recherche.'%')
                ->orWhere('matricule', 'like', '%'.$recherche.'%')
                ->orWhere('telephone', 'like', '%'.$recherche.'%'))
            ->orderBy('nom')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Membre $membre): array => [(int) $membre->user_id => self::texte($membre)])
            ->all();
    }

    private static function estDeCellule(mixed $posteId): bool
    {
        return self::portee($posteId) === 'cellule';
    }

    private static function portee(mixed $posteId): ?string
    {
        $perimetre = filled($posteId) ? Poste::query()->whereKey($posteId)->value('perimetre') : null;

        return is_string($perimetre) ? $perimetre : null;
    }

    private static function libelle(int $userId): ?string
    {
        $membre = Membre::query()->with('cellule')->where('user_id', $userId)->first();

        return $membre !== null ? self::texte($membre) : null;
    }

    private static function texte(Membre $membre): string
    {
        return $membre->prenom.' '.$membre->nom.' — '.($membre->matricule ?? $membre->telephone).' — '.$membre->cellule->nom;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageNominations::route('/')];
    }
}
