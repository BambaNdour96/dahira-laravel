<?php

namespace App\Filament\Resources\Dahiras\Widgets;

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\User;
use App\Services\AssiduiteService;
use App\Services\CasquettesService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * L'assiduité de chaque membre sur 3 mois. Un Jewrine voit sa cellule ; la Jewrine des Sokhnas, les Sokhnas de sa cellule.
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

                if ($utilisateur instanceof User && ! $utilisateur->can('dahiras.voir')) {
                    $query->whereIn('cellule_id', app(CasquettesService::class)->cellulesDirigees($utilisateur));
                }

                return $query->orderBy('nom');
            })
            ->columns([
                TextColumn::make('prenom')->label('Prénom')->searchable(),
                TextColumn::make('nom')->label('Nom')->searchable(),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('presences_3_mois')->label('Présent')
                    ->state(fn (Membre $record): string => $assiduite->pour($record)['presents'].' / '.$assiduite->pour($record)['dahiras']),
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
                SelectFilter::make('sexe')->label('Goor Yalla / Sokhnas')->options(['H' => 'Goor Yalla', 'F' => 'Sokhnas']),
            ]);
    }
}
