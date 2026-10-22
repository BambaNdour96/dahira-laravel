<?php

namespace App\Filament\Resources\Instances\Pages;

use App\Enums\StatutMembre;
use App\Filament\Resources\Instances\InstanceResource;
use App\Filament\Resources\Instances\RelationManagers\CommissionsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\EngagementsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\OrdresDepenseRelationManager;
use App\Filament\Resources\Instances\RelationManagers\ReunionsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\TachesRelationManager;
use App\Filament\Resources\Instances\Widgets\AvancementCommissions;
use App\Filament\Resources\Instances\Widgets\BudgetCommissions;
use App\Filament\Resources\Instances\Widgets\IndicateursComite;
use App\Filament\Resources\Instances\Widgets\MembresInstance;
use App\Filament\Resources\Instances\Widgets\ReunionsInstance;
use App\Filament\Resources\Instances\Widgets\SuiviDepensesComite;
use App\Filament\Resources\Instances\Widgets\TableauDeBordComite;
use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\PropositionSuiviDepenses;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\ComiteService;
use App\Services\SuiviDepensesService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Un comité : ses membres, ses commissions et ses réunions. Un comité d'organisation (CDGMT, Grand Ziar…) a son espace
 * en onglets : Tableau de bord, Commissions et budget, Réunions et PV, Collecte, Argent, Tâches, Documents.
 */
class ViewInstance extends ViewRecord
{
    protected static string $resource = InstanceResource::class;

    private function estComiteOrganisation(): bool
    {
        $comite = $this->getRecord();

        return $comite instanceof Instance && $comite->estComiteOrganisation();
    }

    /** @return array<int, class-string> */
    public function getRelationManagers(): array
    {
        return $this->estComiteOrganisation() ? [
            CommissionsRelationManager::class, ReunionsRelationManager::class, EngagementsRelationManager::class,
            OrdresDepenseRelationManager::class, TachesRelationManager::class, DocumentsRelationManager::class,
        ] : [];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Tableau de bord';
    }

    /** @param  callable(SuiviDepensesService, User): void  $operation */
    private static function executer(callable $operation, string $succes): void
    {
        $auteur = self::utilisateur();
        abort_unless($auteur !== null, 403);

        try {
            $operation(app(SuiviDepensesService::class), $auteur);
            Notification::make()->title($succes)->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /** La proposition qui attend le Jewrine national (Jewrine du suivi, ou suppléant), la plus ancienne d'abord. */
    private static function enAttente(Instance $comite, ?string $poste = null): ?PropositionSuiviDepenses
    {
        return $comite->propositionsSuivi()->with('membre')->where('statut', PropositionSuiviDepenses::PROPOSEE)
            ->when($poste !== null, fn ($query) => $query->where('poste', $poste))->oldest('id')->first();
    }

    /**
     * Les actions du suivi des dépenses : activer, proposer, valider ou refuser, désigner.
     *
     * @return list<Action>
     */
    private function actionsSuivi(): array
    {
        $membre = fn (): Select => Select::make('membre_id')->label('Membre')->required()->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Membre::query()->where('statut', StatutMembre::Valide->value)->whereNotNull('user_id')
                ->where(fn ($query) => $query->whereLike('prenom', '%'.$search.'%')->orWhereLike('nom', '%'.$search.'%')->orWhereLike('matricule', '%'.$search.'%'))
                ->limit(20)->get()->mapWithKeys(fn (Membre $trouve): array => [$trouve->id => $trouve->nom_complet.' ('.($trouve->matricule ?? $trouve->telephone).')'])->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => Membre::query()->whereKey((int) $value)->first()?->nom_complet);
        $service = app(SuiviDepensesService::class);

        return [
            Action::make('activerSuivi')
                ->label(fn (Instance $record): string => $record->suivi_depenses ? 'Désactiver le comité de suivi' : 'Activer le comité de suivi des dépenses')
                ->icon('heroicon-o-scale')->color('gray')
                ->visible(fn (Instance $record): bool => $record->estComiteOrganisation() && self::utilisateur()?->can('comites.diriger') === true)
                ->requiresConfirmation()
                ->modalDescription(fn (Instance $record): string => $record->suivi_depenses
                    ? 'Le Jewrine national arbitrera le budget et signera les ordres de dépense.'
                    : 'Son Jewrine arbitrera le budget des commissions et signera les ordres de dépense de l\'événement.')
                ->action(fn (Instance $record) => self::executer(fn (SuiviDepensesService $suivi, User $auteur) => $suivi->activer($record, ! $record->suivi_depenses, $auteur), 'C\'est enregistré.')),
            Action::make('proposerSuivi')->label('Proposer le Jewrine du suivi des dépenses')->icon('heroicon-o-user-plus')
                ->visible(fn (Instance $record): bool => ($utilisateur = self::utilisateur()) !== null && $service->peutProposer($utilisateur, $record)
                    && self::enAttente($record, Poste::JEWRINE_SUIVI_DEPENSES) === null)
                ->modalDescription('Le Jewrine national valide votre proposition ; la nomination suit.')
                ->schema([$membre()])
                ->action(fn (Instance $record, array $data) => self::executer(
                    function (SuiviDepensesService $suivi, User $auteur) use ($record, $data): void {
                        $suivi->proposer($record, Membre::query()->whereKey((int) $data['membre_id'])->firstOrFail(), $auteur);
                    },
                    'Proposition envoyée au Jewrine national.',
                )),
            Action::make('validerSuivi')->label('Valider la proposition')->icon('heroicon-o-check')->color('success')
                ->visible(fn (Instance $record): bool => ($utilisateur = self::utilisateur()) !== null && ($proposition = self::enAttente($record)) !== null && $service->peutDecider($utilisateur, $proposition))
                ->requiresConfirmation()
                ->modalDescription(fn (Instance $record): string => (self::enAttente($record)?->membre->nom_complet ?? '')
                    .(self::enAttente($record)?->pourSuppleant() === true ? ' devient suppléant du Jewrine du suivi des dépenses : il signera les ordres quand le Jewrine est indisponible.' : ' devient Jewrine du suivi des dépenses de ce comité.'))
                ->action(fn (Instance $record) => self::executer(function (SuiviDepensesService $suivi, User $auteur) use ($record): void {
                    $proposition = self::enAttente($record) ?? throw new DomainException('Aucune proposition en attente.');
                    $suivi->valider($proposition, $auteur);
                }, 'Nomination faite : le Jewrine du suivi des dépenses est prévenu.')),
            Action::make('refuserSuivi')->label('Refuser la proposition')->icon('heroicon-o-x-mark')->color('danger')
                ->visible(fn (Instance $record): bool => ($utilisateur = self::utilisateur()) !== null && ($proposition = self::enAttente($record)) !== null && $service->peutDecider($utilisateur, $proposition))
                ->schema([Textarea::make('motif')->label('Motif du refus')->required()->maxLength(200)])
                ->action(fn (Instance $record, array $data) => self::executer(function (SuiviDepensesService $suivi, User $auteur) use ($record, $data): void {
                    $proposition = self::enAttente($record) ?? throw new DomainException('Aucune proposition en attente.');
                    $suivi->refuser($proposition, (string) $data['motif'], $auteur);
                }, 'Proposition refusée ; le président est prévenu.')),
            Action::make('proposerSuppleant')->label('Désigner mon suppléant')->icon('heroicon-o-user-group')
                ->visible(fn (Instance $record): bool => ($utilisateur = self::utilisateur()) !== null && $service->peutProposerSuppleant($utilisateur, $record)
                    && self::enAttente($record, Poste::SUPPLEANT_SUIVI_DEPENSES) === null)
                ->modalDescription('Un membre du comité, qui signera les ordres quand vous êtes indisponible. Le Jewrine national valide ; la suppléance précédente prend fin.')
                ->schema(fn (Instance $record): array => [
                    Select::make('membre_id')->label('Membre du comité')->required()->searchable()
                        ->options($record->membres()->whereNotNull('membres.user_id')->where('membres.user_id', '!=', self::utilisateur()?->id)->get()
                            ->mapWithKeys(fn (Membre $trouve): array => [$trouve->id => $trouve->nom_complet])->all()),
                ])
                ->action(fn (Instance $record, array $data) => self::executer(
                    function (SuiviDepensesService $suivi, User $auteur) use ($record, $data): void {
                        $suivi->proposerSuppleant($record, Membre::query()->whereKey((int) $data['membre_id'])->firstOrFail(), $auteur);
                    },
                    'Suppléant désigné : le Jewrine national valide.',
                )),
            Action::make('finSuppleance')->label('Mettre fin à la suppléance')->icon('heroicon-o-user-minus')->color('danger')
                ->visible(fn (Instance $record): bool => ($utilisateur = self::utilisateur()) !== null && $service->peutMettreFinSuppleance($utilisateur, $record))
                ->schema([Textarea::make('motif')->label('Motif')->required()->maxLength(200)])
                ->action(fn (Instance $record, array $data) => self::executer(
                    function (SuiviDepensesService $suivi, User $auteur) use ($record, $data): void {
                        $suivi->mettreFinSuppleance($record, (string) $data['motif'], $auteur);
                    },
                    'La suppléance a pris fin ; elle reste dans l\'historique.',
                )),
            Action::make('designerSuivi')->label('Désigner le Jewrine du suivi des dépenses')->icon('heroicon-o-identification')->color('gray')
                ->visible(fn (Instance $record): bool => $record->estComiteOrganisation() && $record->suivi_depenses && ($utilisateur = self::utilisateur()) !== null && CasquettesService::estSuperAdministrateur($utilisateur))
                ->modalDescription('Désignation directe par le super administrateur ; elle remplace le Jewrine en place.')
                ->schema([$membre()])
                ->action(fn (Instance $record, array $data) => self::executer(
                    function (SuiviDepensesService $suivi, User $auteur) use ($record, $data): void {
                        $suivi->designer($record, Membre::query()->whereKey((int) $data['membre_id'])->firstOrFail(), $auteur);
                    },
                    'Jewrine du suivi des dépenses désigné.',
                )),
        ];
    }

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    /** @return array<int, Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reunion')
                ->label('Nouvelle réunion')
                ->icon('heroicon-o-calendar-days')
                ->visible(fn (Instance $record): bool => ($utilisateur = self::utilisateur()) !== null && ComiteService::peutOrganiser($utilisateur, $record))
                ->url(fn (Instance $record): string => ReunionResource::getUrl('create', ['instance' => $record->id])),
            Action::make('reprendre')
                ->label('Reprendre les commissions précédentes')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (Instance $record): bool => ! $record->estComiteDirecteur() && $record->commissions()->doesntExist()
                    && ($utilisateur = self::utilisateur()) !== null && ComiteService::peutGerer($utilisateur, $record))
                ->requiresConfirmation()
                ->modalDescription('Les commissions du comité précédent du même type sont recopiées, sans leurs chefs : vous les désignerez ensuite.')
                ->action(function (Instance $record): void {
                    try {
                        $nombre = app(ComiteService::class)->reprendreCommissions($record);
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->warning()->send();

                        return;
                    }

                    Notification::make()->title($nombre.' commission(s) reprise(s). Désignez maintenant leurs chefs.')->success()->send();
                }),
            Action::make('bilan')
                ->label('Bilan (PDF)')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->visible(fn (Instance $record): bool => ! $record->estComiteDirecteur())
                ->url(fn (Instance $record): string => route('comites.bilan', $record)),
            EditAction::make(),
            ActionGroup::make($this->actionsSuivi())->label('Suivi des dépenses')->icon('heroicon-o-scale')->button()->color('gray')
                ->visible(fn (): bool => $this->estComiteOrganisation()),
        ];
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return $this->estComiteOrganisation()
            ? [TableauDeBordComite::class, IndicateursComite::class, SuiviDepensesComite::class]
            : [TableauDeBordComite::class];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return $this->estComiteOrganisation()
            ? [AvancementCommissions::class, MembresInstance::class]
            : [AvancementCommissions::class, BudgetCommissions::class, ReunionsInstance::class, MembresInstance::class];
    }
}
