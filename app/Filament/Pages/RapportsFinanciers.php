<?php

namespace App\Filament\Pages;

use App\Models\Exercice;
use App\Models\User;
use App\Services\FinanceRapportService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Les rapports financiers d'une période (un exercice, ou des dates libres), tirés du journal des caisses. */
class RapportsFinanciers extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Rapports financiers';

    protected static ?string $title = 'Rapports financiers';

    protected static ?string $slug = 'rapports-financiers';

    protected string $view = 'filament.rapports-financiers';

    public ?int $exercice = null;

    public string $debut = '';

    public string $fin = '';

    public static function canAccess(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->can('finances.voir');
    }

    public function mount(): void
    {
        $this->exercice = Exercice::enCours()->id ?? Exercice::query()->latest('date_debut')->value('id');
        $this->choisirExercice();
    }

    public function updatedExercice(): void
    {
        $this->choisirExercice();
    }

    private function choisirExercice(): void
    {
        $exercice = $this->exercice !== null ? Exercice::query()->whereKey($this->exercice)->first() : null;
        $this->debut = ($exercice->date_debut ?? now()->startOfYear())->toDateString();
        $this->fin = ($exercice->date_fin ?? now()->endOfYear())->toDateString();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $utilisateur = Auth::user();
        $service = app(FinanceRapportService::class);

        try {
            $debut = Carbon::parse($this->debut);
            $fin = Carbon::parse($this->fin);
        } catch (\Throwable) {
            $debut = now()->startOfYear();
            $fin = now()->endOfYear();
        }

        if ($fin->lt($debut)) {
            $fin = $debut->copy();
        }

        $rapport = $service->rapport($debut, $fin);
        $mois = array_values(array_unique([...array_keys($rapport['recettes_par_mois']), ...array_keys($rapport['depenses_par_mois'])]));
        sort($mois);
        $plusGrand = max(1, ...array_values($rapport['recettes_par_mois']), ...array_values($rapport['depenses_par_mois']));
        $detail = $utilisateur instanceof User && $utilisateur->can('finances.detail_membres');

        return [
            'exercices' => Exercice::query()->orderByDesc('date_debut')->pluck('libelle', 'id')->all(),
            'rapport' => $rapport,
            'mois' => $mois,
            'plusGrand' => $plusGrand,
            'parMembre' => $detail ? $service->detailParMembre($debut, $fin) : null,
            'sources' => FinanceRapportService::SOURCES,
            'lienExcel' => route('finances.excel', ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString()]),
            'lienExcelMembres' => $detail ? route('finances.excel', ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString(), 'membres' => 1]) : null,
        ];
    }
}
