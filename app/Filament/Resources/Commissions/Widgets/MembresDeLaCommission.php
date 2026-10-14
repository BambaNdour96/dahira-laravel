<?php

namespace App\Filament\Resources\Commissions\Widgets;

use App\Enums\StatutMembre;
use App\Models\AppartenanceCommission;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\User;
use App\Services\CommissionService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Les membres d'une commission permanente et les souhaits à traiter, avec tout l'historique. */
class MembresDeLaCommission extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Membres et souhaits')
            ->query(fn (): Builder => AppartenanceCommission::query()
                ->where('commission_id', $this->commission()->id)
                ->with(['membre.cellule'])
                ->orderByRaw('debut_le is not null')
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('membre')->label('Membre')->weight('bold')
                    ->state(fn (AppartenanceCommission $record): string => $record->membre->prenom.' '.$record->membre->nom),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('etat')->label('État')->badge()
                    ->state(fn (AppartenanceCommission $record): string => self::etat($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Membre' => 'success',
                        'Souhait' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('debut_le')->label('Depuis le')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('fin')->label('Fin ou refus')->placeholder('—')
                    ->state(function (AppartenanceCommission $record): ?string {
                        $date = $record->fin_le ?? $record->refuse_le;
                        $motif = $record->motif_fin ?? $record->motif_refus;

                        return $date === null ? null : $date->format('d/m/Y').($motif !== null ? ' — '.$motif : '');
                    }),
            ])
            ->filters([
                SelectFilter::make('etat')
                    ->label('État')
                    ->options(['actifs' => 'Membres', 'souhaits' => 'Souhaits', 'historique' => 'Refus et départs'])
                    ->default('actifs')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'actifs' => $query->whereNotNull('debut_le')->whereNull('refuse_le')->whereNull('fin_le'),
                        'souhaits' => $query->whereNull('debut_le')->whereNull('refuse_le')->whereNull('fin_le'),
                        'historique' => $query->where(fn (Builder $passe) => $passe->whereNotNull('refuse_le')->orWhereNotNull('fin_le')),
                        default => $query,
                    }),
            ])
            ->headerActions([
                Action::make('ajouter')
                    ->label('Ajouter un membre')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => $this->peutDiriger())
                    ->schema([
                        Select::make('membre_id')->label('Membre validé')->searchable()->required()
                            ->getSearchResultsUsing(fn (string $search): array => Membre::query()
                                ->where('statut', StatutMembre::Valide->value)
                                ->where(fn (Builder $query) => $query->where('prenom', 'like', "%{$search}%")->orWhere('nom', 'like', "%{$search}%")
                                    ->orWhere('matricule', 'like', "%{$search}%")->orWhere('telephone', 'like', "%{$search}%"))
                                ->limit(30)->get()
                                ->mapWithKeys(fn (Membre $membre): array => [$membre->id => $membre->prenom.' '.$membre->nom.' — '.($membre->matricule ?? $membre->telephone)])
                                ->all())
                            ->getOptionLabelUsing(fn (mixed $value): ?string => ($membre = Membre::query()->whereKey($value)->first()) !== null ? $membre->prenom.' '.$membre->nom : null),
                    ])
                    ->action(fn (array $data) => $this->executer(
                        fn (User $auteur) => app(CommissionService::class)->ajouter($this->commission(), Membre::query()->whereKey($data['membre_id'])->firstOrFail(), $auteur),
                        'Membre ajouté : il est prévenu.',
                    )),
            ])
            ->recordActions([
                Action::make('accepter')
                    ->label('Accepter')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AppartenanceCommission $record): bool => $record->estSouhait() && $this->peutDiriger())
                    ->requiresConfirmation()
                    ->action(fn (AppartenanceCommission $record) => $this->executer(
                        fn (User $auteur) => app(CommissionService::class)->accepter($record, $auteur),
                        'Souhait accepté : le membre est prévenu.',
                    )),
                Action::make('refuser')
                    ->label('Refuser')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (AppartenanceCommission $record): bool => $record->estSouhait() && $this->peutDiriger())
                    ->schema([Textarea::make('motif')->label('Motif (visible par le membre)')->maxLength(255)->rows(2)])
                    ->action(fn (AppartenanceCommission $record, array $data) => $this->executer(
                        fn (User $auteur) => app(CommissionService::class)->refuser($record, (string) ($data['motif'] ?? ''), $auteur),
                        'Souhait refusé.',
                    )),
                Action::make('retirer')
                    ->label('Retirer')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (AppartenanceCommission $record): bool => $record->estActive() && $this->peutDiriger())
                    ->schema([Textarea::make('motif')->label('Motif')->required()->maxLength(255)->rows(2)])
                    ->action(fn (AppartenanceCommission $record, array $data) => $this->executer(
                        fn (User $auteur) => app(CommissionService::class)->retirer($record, (string) $data['motif'], $auteur),
                        'Le membre a quitté la commission ; l\'historique reste.',
                    )),
            ]);
    }

    public static function etat(AppartenanceCommission $appartenance): string
    {
        return match (true) {
            $appartenance->refuse_le !== null => 'Refusé',
            $appartenance->fin_le !== null => 'Parti',
            $appartenance->debut_le !== null => 'Membre',
            default => 'Souhait',
        };
    }

    private function commission(): Commission
    {
        abort_unless($this->record instanceof Commission, 404);

        return $this->record;
    }

    private function peutDiriger(): bool
    {
        return ($utilisateur = Auth::user()) instanceof User && CommissionService::peutDiriger($utilisateur, $this->commission());
    }

    private function executer(\Closure $operation, string $succes): void
    {
        $auteur = Auth::user();

        if (! $auteur instanceof User) {
            return;
        }

        try {
            $operation($auteur);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($succes)->success()->send();
    }
}
