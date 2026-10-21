<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Models\Engagement;
use App\Services\CollecteService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Onglet « Collecte » : les engagements de l'événement (le détail et les actions sont sur la fiche du programme). */
class EngagementsRelationManager extends OngletComite
{
    protected static string $relationship = 'engagements';

    protected static ?string $title = 'Collecte';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass) && ($utilisateur = self::utilisateur()) !== null && $utilisateur->can('programmes.voir');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['membre.cellule', 'collecte']))
            ->columns([
                TextColumn::make('membre.nom')->label('Membre')->formatStateUsing(fn (Engagement $record): string => $record->membre->prenom.' '.$record->membre->nom)->searchable(['prenom', 'nom']),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('montant')->label('Engagement')->formatStateUsing(fn (int $state): string => CollecteService::fcfa($state)),
                TextColumn::make('verse')->label('Versé')->state(fn (Engagement $record): string => CollecteService::fcfa($record->verse())),
                TextColumn::make('reste')->label('Reste')->state(fn (Engagement $record): string => CollecteService::fcfa($record->reste())),
                TextColumn::make('situation')->label('Situation')->badge()->state(fn (Engagement $record): string => $record->situation()),
            ])
            ->filters([SelectFilter::make('cellule')->label('Cellule')->relationship('membre.cellule', 'nom')]);
    }
}
