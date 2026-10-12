<?php

namespace App\Filament\Resources\Collectes\Widgets;

use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Engagement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Policies\CollectePolicy;
use App\Services\CollecteService;
use App\Support\MessagesRelance;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Chaque engagement : montant, versé, reste, situation ; attribution, relance, modification tracée, espèces.
 * Un Jewrine de cellule ne voit que les membres de sa cellule.
 */
class EngagementsDesMembres extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Engagements des membres')
            ->query(function (): Builder {
                $query = Engagement::query()
                    ->where('collecte_id', $this->collecte()->id)
                    ->with(['membre.cellule', 'collecte']);

                $utilisateur = Auth::user();

                if ($utilisateur instanceof User && CollectePolicy::limiteeACellule($utilisateur)) {
                    $query->whereIn('membre_id', Membre::query()->select('id')->whereIn('cellule_id', Cellule::query()->select('id')->where('responsable_id', $utilisateur->id)));
                }

                return $query;
            })
            ->columns([
                TextColumn::make('membre.prenom')->label('Prénom')->searchable(),
                TextColumn::make('membre.nom')->label('Nom')->searchable(),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('montant')->label('Engagement')->sortable()
                    ->formatStateUsing(fn (mixed $state): string => CollecteService::fcfa((int) $state)),
                TextColumn::make('fixe_par')->label('Fixé par')
                    ->formatStateUsing(fn (mixed $state, Engagement $record): string => $record->libelleFixePar()),
                TextColumn::make('verse')->label('Versé')
                    ->state(fn (Engagement $record): string => CollecteService::fcfa($record->verse())),
                TextColumn::make('reste')->label('Reste')
                    ->state(fn (Engagement $record): string => CollecteService::fcfa($record->reste())),
                TextColumn::make('situation')->label('Situation')->badge()
                    ->state(fn (Engagement $record): string => $record->situation())
                    ->color(fn (string $state): string => match ($state) {
                        'À jour' => 'success',
                        'En retard' => 'danger',
                        'En cours' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('cellule')
                    ->label('Cellule')
                    ->options(fn (): array => Cellule::query()->orderBy('nom')->pluck('nom', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('membre', fn (Builder $membre) => $membre->where('cellule_id', $data['value']))
                        : $query),
                Filter::make('pas_a_jour')
                    ->label('Pas encore à jour')
                    ->query(fn (Builder $query): Builder => $query->whereRaw(
                        'engagements.montant > (select coalesce(sum(p.montant), 0) from paiements p where p.collecte_id = engagements.collecte_id and p.membre_id = engagements.membre_id and p.statut = ?)',
                        ['confirme'],
                    )),
            ])
            ->headerActions([$this->messageGroupe(), $this->attribuer()])
            ->recordActions([$this->relancer(), $this->modifier(), $this->especes(), $this->historique()])
            ->defaultSort('montant', 'desc');
    }

    private function collecte(): Collecte
    {
        abort_unless($this->record instanceof Collecte, 404);

        return $this->record;
    }

    private static function auteur(): User
    {
        $auteur = Auth::user();
        abort_unless($auteur instanceof User, 403);

        return $auteur;
    }

    private function attribuer(): Action
    {
        return Action::make('attribuer')
            ->label('Attribuer un montant')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (): bool => Gate::allows('attribuer', $this->collecte()))
            ->schema([
                Select::make('membres')
                    ->label('Membres')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(fn (): array => $this->collecte()->membresConcernes()->with('cellule')->orderBy('nom')->get()
                        ->mapWithKeys(fn (Membre $membre): array => [$membre->id => $membre->prenom.' '.$membre->nom.' — '.$membre->cellule->nom])
                        ->all()),
                TextInput::make('montant')->label('Montant')->numeric()->minValue(1)->required()->suffix('FCFA'),
                Textarea::make('motif')->label('Motif')->default('Montant attribué par le bureau')->required()->maxLength(255),
            ])
            ->action(function (array $data): void {
                $service = app(CollecteService::class);
                $nombre = 0;

                foreach ((array) $data['membres'] as $id) {
                    $membre = Membre::query()->whereKey((int) $id)->first();

                    if ($membre !== null) {
                        $service->attribuer($this->collecte(), $membre, (int) $data['montant'], self::auteur(), (string) $data['motif']);
                        $nombre++;
                    }
                }

                Notification::make()->title($nombre.' engagement(s) attribué(s). Les membres sont prévenus.')->success()->send();
            });
    }

    private function messageGroupe(): Action
    {
        return Action::make('messageGroupe')
            ->label('Message pour les groupes')
            ->icon('heroicon-o-megaphone')
            ->color('gray')
            ->visible(fn (): bool => ! self::auteur()->hasRole('suivi_evaluation') || self::auteur()->hasAnyRole(CollectePolicy::BUREAU))
            ->modalDescription('Copiez ce message et publiez-le dans les groupes WhatsApp de la Daara.')
            ->fillForm(fn (): array => ['message' => MessagesRelance::collecteGroupe($this->collecte())])
            ->schema([
                Textarea::make('message')->label('Message')->rows(6)->readOnly(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer');
    }

    private function relancer(): Action
    {
        return Action::make('relancer')
            ->label('Relancer')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->visible(fn (Engagement $record): bool => $record->reste() > 0 && CollectePolicy::peutRelancer(self::auteur(), $record))
            ->url(fn (Engagement $record): string => route('relances.collecte', $record))
            ->openUrlInNewTab();
    }

    private function modifier(): Action
    {
        return Action::make('modifier')
            ->label('Modifier')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (Engagement $record): bool => Gate::allows('modifierEngagement', $record->collecte))
            ->fillForm(fn (Engagement $record): array => ['montant' => $record->montant])
            ->schema([
                TextInput::make('montant')->label('Nouveau montant')->numeric()->minValue(1)->required()->suffix('FCFA'),
                Textarea::make('motif')
                    ->label('Motif (gardé dans l\'historique, visible par le membre)')
                    ->placeholder('Exemple : demande du membre, situation familiale')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Engagement $record, array $data): void {
                try {
                    app(CollecteService::class)->modifier($record, (int) $data['montant'], (string) $data['motif'], self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Engagement modifié. La modification est tracée et le membre est prévenu.')->success()->send();
            });
    }

    private function especes(): Action
    {
        return Action::make('especes')
            ->label('Espèces')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (): bool => Gate::allows('create', Paiement::class))
            ->fillForm(fn (Engagement $record): array => ['montant' => $record->reste()])
            ->schema([
                TextInput::make('montant')->label('Montant reçu')->numeric()->minValue(1)->required()->suffix('FCFA'),
            ])
            ->action(function (Engagement $record, array $data): void {
                try {
                    app(CollecteService::class)->enregistrerEspeces($record->collecte, $record->membre, (int) $data['montant'], self::auteur());
                } catch (DomainException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Versement en espèces enregistré, avec son reçu.')->success()->send();
            });
    }

    private function historique(): Action
    {
        return Action::make('historique')
            ->label('Historique')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->modalHeading(fn (Engagement $record): string => 'Historique — '.$record->membre->prenom.' '.$record->membre->nom)
            ->modalContent(fn (Engagement $record) => view('filament.historique-engagement', [
                'lignes' => $record->historique()->with('auteur')->get(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer');
    }
}
