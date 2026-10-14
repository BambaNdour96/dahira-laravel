<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Models\Instance;
use App\Models\Membre;
use App\Models\User;
use App\Services\ComiteService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Les membres d'un comité. Pour le Comité directeur, seul le super administrateur ajoute et retire ;
 * pour un CDGMT, les organisateurs du comité.
 */
class MembresInstance extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    private function instance(): Instance
    {
        abort_unless($this->record instanceof Instance, 404);

        return $this->record;
    }

    private function peutModifier(): bool
    {
        $utilisateur = Auth::user();

        if (! $utilisateur instanceof User) {
            return false;
        }

        // Les membres de l'espace d'une commission permanente suivent la commission : ils se gèrent dans la commission.
        if ($this->instance()->estCommission()) {
            return false;
        }

        return $this->instance()->estComiteDirecteur()
            ? $utilisateur->can('comite_directeur.composer')
            : ComiteService::peutGerer($utilisateur, $this->instance());
    }

    public function table(Table $table): Table
    {
        $instance = $this->instance();
        $chefs = $instance->commissions()->whereNotNull('chef_id')->pluck('nom', 'chef_id');

        return $table
            ->heading('Membres ('.$instance->membres()->count().')')
            ->query(fn (): Builder => Membre::query()
                ->whereIn('id', $this->instance()->membres()->select('membres.id'))
                ->with('cellule')
                ->orderBy('nom'))
            ->columns([
                TextColumn::make('prenom')->label('Prénom')->searchable(),
                TextColumn::make('nom')->label('Nom')->searchable(),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('role_comite')->label('Rôle')->badge()
                    ->state(fn (Membre $record): string => match (true) {
                        $instance->president_id === $record->id => 'Président',
                        $chefs->has($record->id) => 'Chef : '.$chefs->get($record->id),
                        default => 'Membre',
                    })
                    ->color(fn (string $state): string => $state === 'Président' ? 'warning' : ($state === 'Membre' ? 'gray' : 'success')),
            ])
            ->headerActions([
                Action::make('ajouter')
                    ->label('Ajouter des membres')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (): bool => $this->peutModifier())
                    ->schema([
                        Select::make('membres')->label('Membres')->multiple()->searchable()->required()
                            ->options(fn (): array => ComiteService::choixMembres()),
                    ])
                    ->action(function (array $data): void {
                        $utilisateur = Auth::user();
                        abort_unless($utilisateur instanceof User, 403);
                        $ids = array_map('intval', (array) $data['membres']);

                        if ($this->instance()->estComiteDirecteur()) {
                            $actuels = $this->instance()->membres()->pluck('membres.id')->map(fn (mixed $id): int => (int) $id)->all();
                            app(ComiteService::class)->definirComiteDirecteur(array_values(array_unique([...$actuels, ...$ids])), $utilisateur);
                        } else {
                            $this->instance()->membres()->syncWithoutDetaching($ids);
                        }

                        Notification::make()->title(count($ids).' membre(s) ajouté(s).')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('retirer')
                    ->label('Retirer')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (Membre $record): bool => $this->peutModifier() && $this->instance()->president_id !== $record->id)
                    ->requiresConfirmation()
                    ->action(function (Membre $record): void {
                        $utilisateur = Auth::user();
                        abort_unless($utilisateur instanceof User, 403);

                        try {
                            app(ComiteService::class)->retirerDuComite($this->instance(), $record, $utilisateur);
                        } catch (DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title($record->prenom.' '.$record->nom.' retiré(e).')->success()->send();
                    }),
            ]);
    }
}
