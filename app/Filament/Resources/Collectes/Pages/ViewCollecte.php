<?php

namespace App\Filament\Resources\Collectes\Pages;

use App\Filament\Resources\Collectes\CollecteResource;
use App\Filament\Resources\Collectes\Widgets\CollecteAnalyse;
use App\Filament\Resources\Collectes\Widgets\CollecteChiffres;
use App\Filament\Resources\Collectes\Widgets\CollecteEvolution;
use App\Filament\Resources\Collectes\Widgets\CollecteParCellule;
use App\Filament\Resources\Collectes\Widgets\EngagementsDesMembres;
use App\Filament\Resources\Collectes\Widgets\JourJDuProgramme;
use App\Models\Collecte;
use App\Models\User;
use App\Policies\CollectePolicy;
use App\Services\JourJService;
use App\Support\Confidentiel;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Component;

/** Le tableau de bord d'un programme : la collecte (argent), le jour J (présence), puis chaque engagement. */
class ViewCollecte extends ViewRecord
{
    protected static string $resource = CollecteResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        $lecteur = fn (): bool => auth()->user() instanceof User
            && auth()->user()->hasAnyRole([...CollectePolicy::BUREAU, 'suivi_evaluation']);

        return [
            Action::make('exporter')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->visible($lecteur)
                ->modalHeading('Exporter les engagements')
                ->modalDescription(Confidentiel::AVERTISSEMENT)
                ->modalSubmitActionLabel('Télécharger')
                ->schema([
                    Checkbox::make('telephones')->label('Inclure les numéros de téléphone')->live(),
                    Textarea::make('motif')
                        ->label('Pourquoi les numéros sont-ils nécessaires ?')
                        ->placeholder('Exemple : appels de relance par la cellule avant la date limite')
                        ->maxLength(200)
                        ->visible(fn (Get $get): bool => (bool) $get('telephones'))
                        ->required(fn (Get $get): bool => (bool) $get('telephones')),
                ])
                ->action(function (Collecte $record, array $data, Component $livewire): void {
                    $livewire->redirect(route('programmes.export', array_filter([
                        'collecte' => $record,
                        'telephones' => ($data['telephones'] ?? false) ? 1 : null,
                        'motif' => ($data['telephones'] ?? false) ? (string) ($data['motif'] ?? '') : null,
                    ], fn (mixed $valeur): bool => $valeur !== null)));
                }),
            Action::make('rapport')
                ->label('Rapport (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible($lecteur)
                ->requiresConfirmation()
                ->modalHeading('Télécharger le rapport du programme')
                ->modalDescription(Confidentiel::AVERTISSEMENT)
                ->modalSubmitActionLabel('Télécharger')
                ->action(fn (Collecte $record, Component $livewire) => $livewire->redirect(route('programmes.rapport', $record))),
            CollecteResource::cloturer(),
            CollecteResource::rouvrir(),
            EditAction::make()->label('Paramètres'),
            CollecteResource::annuler(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $collecte = $this->getRecord();

        return $collecte instanceof Collecte ? JourJService::remplir($collecte, $data) : $data;
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [CollecteChiffres::class, CollecteAnalyse::class, JourJDuProgramme::class, CollecteEvolution::class, CollecteParCellule::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [EngagementsDesMembres::class];
    }
}
