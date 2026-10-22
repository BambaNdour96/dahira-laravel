<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Models\CommissionComite;
use App\Models\User;
use App\Services\CollecteService;
use App\Services\SuiviDepensesService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Onglet « Commissions et budget » : chaque commission, son chef, ses besoins, son budget arbitré et ce qu'elle a dépensé. */
class CommissionsRelationManager extends OngletComite
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Commissions et budget';

    private function peutArbitrer(): bool
    {
        return ($utilisateur = self::utilisateur()) !== null && app(SuiviDepensesService::class)->peutArbitrer($utilisateur, $this->comite());
    }

    /** @param  callable(SuiviDepensesService, User): void  $operation */
    private static function executer(callable $operation): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(SuiviDepensesService::class), $auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function table(Table $table): Table
    {
        $fcfa = fn (?int $montant): string => $montant === null ? '—' : CollecteService::fcfa($montant);

        return $table
            ->recordTitleAttribute('nom')
            ->modifyQueryUsing(fn ($query) => $query->with('chef'))
            ->columns([
                TextColumn::make('nom')->label('Commission')->weight('bold'),
                TextColumn::make('chef.nom')->label('Chef')->formatStateUsing(fn (CommissionComite $record): string => $record->chef !== null ? $record->chef->prenom.' '.$record->chef->nom : '—'),
                TextColumn::make('besoins')->label('Besoins retenus')->state(fn (CommissionComite $record): string => $fcfa($record->besoinsRetenus())),
                TextColumn::make('budget')->label('Budget arbitré')->state(fn (CommissionComite $record): string => $fcfa($record->budget_arrete))->weight('bold'),
                TextColumn::make('decaisse')->label('Décaissé')->state(fn (CommissionComite $record): string => $fcfa($record->depense())),
                TextColumn::make('justifie')->label('Justifié')->state(fn (CommissionComite $record): string => $fcfa($record->justifie())),
                TextColumn::make('a_justifier')->label('Reste à justifier')->badge()
                    ->state(fn (CommissionComite $record): string => $fcfa($record->resteAJustifier()))
                    ->color(fn (CommissionComite $record): string => $record->resteAJustifier() > 0 ? 'warning' : 'gray'),
                TextColumn::make('reste')->label('Reste')->badge()
                    ->state(fn (CommissionComite $record): string => $fcfa($record->budget() - $record->depense()))
                    ->color(fn (CommissionComite $record): string => $record->depense() > $record->budget() ? 'danger' : 'success'),
            ])
            ->headerActions([
                Action::make('repartir')->label('Répartir')->icon('heroicon-o-arrows-right-left')->color('gray')
                    ->visible(fn (): bool => $this->peutArbitrer() && $this->comite()->commissions()->count() > 1)
                    ->modalDescription('Une part du budget d\'une commission passe à une autre. Les deux révisions sont tracées.')
                    ->schema(fn (): array => [
                        Select::make('depuis')->label('Depuis la commission')->required()->options($this->comite()->commissions()->pluck('nom', 'id')->all()),
                        Select::make('vers')->label('Vers la commission')->required()->different('depuis')->options($this->comite()->commissions()->pluck('nom', 'id')->all()),
                        TextInput::make('montant')->label('Montant (FCFA)')->required()->numeric()->minValue(1),
                        Textarea::make('motif')->label('Motif')->required()->maxLength(200),
                    ])
                    ->action(fn (array $data) => self::executer(function (SuiviDepensesService $service, User $auteur) use ($data): void {
                        $service->repartir(
                            CommissionComite::query()->whereKey((int) $data['depuis'])->firstOrFail(),
                            CommissionComite::query()->whereKey((int) $data['vers'])->firstOrFail(),
                            (int) $data['montant'], (string) $data['motif'], $auteur,
                        );
                        Notification::make()->title('Budget réparti.')->success()->send();
                    })),
            ])
            ->recordActions([
                Action::make('arbitrer')->label('Arbitrer')->icon('heroicon-o-scale')
                    ->visible(fn (): bool => $this->peutArbitrer())
                    ->modalDescription(fn (CommissionComite $record): string => 'Besoins retenus : '.$fcfa($record->besoinsRetenus()).' · budget actuel : '.$fcfa($record->budget_arrete)
                        .'. Retenir, réduire ou augmenter, dans l\'enveloppe de l\'événement.')
                    ->fillForm(fn (CommissionComite $record): array => ['budget' => $record->budget_arrete ?? $record->besoinsRetenus()])
                    ->schema([
                        TextInput::make('budget')->label('Budget de la commission (FCFA)')->required()->numeric()->minValue(0),
                        Textarea::make('motif')->label('Motif de la révision')->required()->maxLength(200),
                    ])
                    ->action(fn (CommissionComite $record, array $data) => self::executer(function (SuiviDepensesService $service, User $auteur) use ($record, $data): void {
                        $service->arbitrer($record, (int) $data['budget'], (string) $data['motif'], $auteur);
                        Notification::make()->title('Budget de la commission '.$record->nom.' arrêté ; le chef est prévenu.')->success()->send();
                    })),
                Action::make('revisions')->label('Révisions')->icon('heroicon-o-clock')->color('gray')
                    ->visible(fn (CommissionComite $record): bool => $record->revisions()->exists())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(fn (CommissionComite $record) => view('filament.revisions-budget', ['revisions' => $record->revisions()->with('auteur')->get()])),
            ])
            ->paginated(false);
    }
}
