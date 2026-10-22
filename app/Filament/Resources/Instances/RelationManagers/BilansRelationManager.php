<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Models\BilanComite;
use App\Models\CommissionComite;
use App\Models\Reunion;
use App\Models\User;
use App\Services\BilanComiteService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Onglet « Bilan » : les bilans des commissions, le bilan général, l'évaluation de Suivi et Évaluation, la réunion de
 * bilan, ce qui manque pour clôturer, et la clôture.
 */
class BilansRelationManager extends OngletComite
{
    protected static string $relationship = 'bilans';

    protected static ?string $title = 'Bilan';

    /** @param  callable(BilanComiteService, User): void  $operation */
    private static function executer(callable $operation, string $succes): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(BilanComiteService::class), $auteur);
            Notification::make()->title($succes)->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /**
     * Le formulaire d'un bilan : objectifs, forces, faiblesses, améliorations.
     *
     * @return list<Radio|Textarea>
     */
    public static function formulaire(): array
    {
        return [
            Radio::make('appreciation')->label('Les objectifs')->required()->options(BilanComite::APPRECIATIONS),
            Textarea::make('forces')->label('Forces : ce qui a bien marché')->rows(3)->maxLength(5000),
            Textarea::make('faiblesses')->label('Faiblesses : ce qui a manqué')->rows(3)->maxLength(5000),
            Textarea::make('ameliorations')->label('Améliorations pour la prochaine fois')->helperText('Elles s\'afficheront à la mise en place du prochain comité du même type.')->rows(3)->maxLength(5000),
        ];
    }

    private function etat(): string
    {
        return collect(app(BilanComiteService::class)->conditions($this->comite()))
            ->map(fn (array $condition): string => ($condition['fait'] ? '✔ ' : '✘ ').$condition['libelle'])
            ->implode(' · ');
    }

    public function table(Table $table): Table
    {
        $service = app(BilanComiteService::class);
        $rempli = fn (string $type): array => ($bilan = $this->comite()->bilans()->where('type', $type)->first()) !== null
            ? ['appreciation' => $bilan->appreciation, 'forces' => $bilan->forces, 'faiblesses' => $bilan->faiblesses, 'ameliorations' => $bilan->ameliorations]
            : [];

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['commission', 'auteur']))
            ->heading(fn (): string => $this->comite()->estClos() ? 'Comité clos le '.$this->comite()->cloture_le?->format('d/m/Y').' : archive en lecture seule' : 'Pour clôturer')
            ->description(fn (): string => $this->etat())
            ->columns([
                TextColumn::make('titre')->label('Bilan')->state(fn (BilanComite $record): string => $record->titre())->weight('bold'),
                TextColumn::make('appreciation')->label('Objectifs')->badge()
                    ->formatStateUsing(fn (?string $state): string => BilanComite::APPRECIATIONS[$state] ?? '—')
                    ->color(fn (?string $state): string => match ($state) {
                        'atteints' => 'success', 'partiels' => 'warning', default => 'danger',
                    }),
                TextColumn::make('auteur.name')->label('Rendu par'),
                TextColumn::make('updated_at')->label('Le')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('bilanGeneral')->label('Rendre le bilan général')->icon('heroicon-o-document-text')
                    ->visible(fn (): bool => ($utilisateur = self::utilisateur()) !== null && $service->peutRendreBilanGeneral($utilisateur, $this->comite()))
                    ->fillForm(fn (): array => $rempli(BilanComite::GENERAL))
                    ->schema(self::formulaire())
                    ->action(fn (array $data) => self::executer(function (BilanComiteService $bilans, User $auteur) use ($data): void {
                        $bilans->rendreBilanGeneral($this->comite(), $data, $auteur);
                    }, 'Bilan général enregistré.')),
                Action::make('bilanCommission')->label('Bilan d\'une commission')->icon('heroicon-o-clipboard-document-check')->color('gray')
                    ->visible(fn (): bool => ($utilisateur = self::utilisateur()) !== null && $this->comite()->commissions()->get()
                        ->contains(fn (CommissionComite $commission): bool => $service->peutRendreBilanCommission($utilisateur, $commission)))
                    ->schema(fn (): array => [
                        Select::make('commission_id')->label('Commission')->required()->options($this->comite()->commissions()->pluck('nom', 'id')->all()),
                        ...self::formulaire(),
                    ])
                    ->action(fn (array $data) => self::executer(function (BilanComiteService $bilans, User $auteur) use ($data): void {
                        $bilans->rendreBilanCommission(CommissionComite::query()->whereKey((int) $data['commission_id'])->firstOrFail(), $data, $auteur);
                    }, 'Bilan de la commission enregistré.')),
                Action::make('evaluer')->label('Rendre l\'évaluation')->icon('heroicon-o-check-badge')->color('gray')
                    ->visible(fn (): bool => ($utilisateur = self::utilisateur()) !== null && $service->peutEvaluer($utilisateur, $this->comite()))
                    ->fillForm(fn (): array => $rempli(BilanComite::EVALUATION))
                    ->schema(self::formulaire())
                    ->action(fn (array $data) => self::executer(function (BilanComiteService $bilans, User $auteur) use ($data): void {
                        $bilans->evaluer($this->comite(), $data, $auteur);
                    }, 'Évaluation enregistrée ; le président est prévenu.')),
                Action::make('reunionBilan')->label('Réunion de bilan')->icon('heroicon-o-calendar')->color('gray')
                    ->visible(fn (): bool => ($utilisateur = self::utilisateur()) !== null && $service->peutRendreBilanGeneral($utilisateur, $this->comite()))
                    ->schema(fn (): array => [
                        Select::make('reunion_id')->label('La réunion de bilan')->required()
                            ->options($this->comite()->reunions()->orderByDesc('debut')->get()->mapWithKeys(fn (Reunion $reunion): array => [$reunion->id => $reunion->titre.' · '.$reunion->debut->format('d/m/Y')])->all()),
                    ])
                    ->action(fn (array $data) => self::executer(function (BilanComiteService $bilans, User $auteur) use ($data): void {
                        $bilans->choisirReunionBilan($this->comite(), Reunion::query()->whereKey((int) $data['reunion_id'])->firstOrFail(), $auteur);
                    }, 'Réunion de bilan enregistrée.')),
                Action::make('cloturerComite')->label('Clôturer le comité')->icon('heroicon-o-lock-closed')->color('danger')
                    ->visible(fn (): bool => ($utilisateur = self::utilisateur()) !== null && $service->peutCloturer($utilisateur, $this->comite()))
                    ->requiresConfirmation()
                    ->modalDescription(fn (): string => $this->etat().'. Une fois clos, le comité reste en archive, en lecture seule.')
                    ->action(fn () => self::executer(function (BilanComiteService $bilans, User $auteur): void {
                        $bilans->cloturer($this->comite(), $auteur);
                    }, 'Comité clos : il reste en archive, en lecture seule.')),
            ])
            ->recordActions([
                Action::make('lire')->label('Lire')->icon('heroicon-o-eye')->color('gray')
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fermer')
                    ->modalHeading(fn (BilanComite $record): string => $record->titre())
                    ->modalContent(fn (BilanComite $record) => view('filament.bilan-comite', ['bilan' => $record])),
            ])
            ->emptyStateHeading('Aucun bilan rendu pour le moment')
            ->paginated(false);
    }
}
