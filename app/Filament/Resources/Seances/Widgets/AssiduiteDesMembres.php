<?php

namespace App\Filament\Resources\Seances\Widgets;

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\User;
use App\Services\AssiduiteService;
use App\Services\PointageService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * L'assiduité de chaque membre sur 3 mois. Un Jewrine voit sa cellule ; la Jewrine des femmes, les sœurs de sa cellule.
 */
class AssiduiteDesMembres extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $assiduite = app(AssiduiteService::class);

        return $table
            ->heading('Assiduité des membres (3 derniers mois)')
            ->query(function (): Builder {
                $query = Membre::query()->where('statut', StatutMembre::Valide->value)->with('cellule');
                $utilisateur = Auth::user();

                if ($utilisateur instanceof User && ! $utilisateur->hasAnyRole(['super_administrateur', 'jewrine_national', 'secretaire_general', 'suivi_evaluation'])) {
                    $query->whereIn('cellule_id', PointageService::cellulesDirigees($utilisateur));

                    $seulementSoeurs = Cellule::query()->where('jewrine_femmes_id', $utilisateur->id)->exists()
                        && ! Cellule::query()->where('responsable_id', $utilisateur->id)->exists();

                    if ($seulementSoeurs) {
                        $query->where('sexe', Sexe::Femme->value);
                    }
                }

                return $query->orderBy('nom');
            })
            ->columns([
                TextColumn::make('prenom')->label('Prénom')->searchable(),
                TextColumn::make('nom')->label('Nom')->searchable(),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('presences_3_mois')->label('Présent')
                    ->state(fn (Membre $record): string => $assiduite->pour($record)['presents'].' / '.$assiduite->pour($record)['seances']),
                TextColumn::make('excuses_3_mois')->label('Excusé')
                    ->state(fn (Membre $record): int => $assiduite->pour($record)['excuses']),
                TextColumn::make('taux_assiduite')->label('Assiduité')->badge()
                    ->state(fn (Membre $record): string => $assiduite->pour($record)['taux'].' %')
                    ->color(fn (string $state): string => (int) $state >= 75 ? 'success' : ((int) $state >= 50 ? 'warning' : 'danger')),
                TextColumn::make('absences_de_suite')->label('Absences de suite')->badge()
                    ->state(fn (Membre $record): int => $assiduite->pour($record)['absences_de_suite'])
                    ->color(fn (int $state): string => $state >= 3 ? 'danger' : ($state > 0 ? 'warning' : 'gray')),
            ])
            ->filters([
                SelectFilter::make('sexe')->label('Frères / sœurs')->options(['H' => 'Frères', 'F' => 'Sœurs']),
            ]);
    }
}
