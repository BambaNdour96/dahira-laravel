<?php

namespace App\Filament\Resources\Instances\RelationManagers;

use App\Filament\Resources\OrdresDepense\OrdreDepenseResource;
use App\Models\OrdreDepense;
use App\Services\CollecteService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Onglet « Argent » : les ordres de dépense de l'événement, avec leur commission et leur état. */
class OrdresDepenseRelationManager extends OngletComite
{
    protected static string $relationship = 'ordresDepense';

    protected static ?string $title = 'Argent';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass) && ($utilisateur = self::utilisateur()) !== null
            && ($utilisateur->can('comites.budget_voir') || $utilisateur->can('finances.voir'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['commissionComite', 'commission', 'libelle']))
            ->columns([
                TextColumn::make('numero')->label('Ordre')->weight('bold'),
                TextColumn::make('created_at')->label('Demandé le')->date('d/m/Y'),
                TextColumn::make('objet')->label('Objet')->limit(40),
                TextColumn::make('commission')->label('Commission')->state(fn (OrdreDepense $record): string => $record->commissionComite->nom ?? $record->commission->nom ?? '—'),
                TextColumn::make('montant')->label('Montant')->formatStateUsing(fn (int $state): string => CollecteService::fcfa($state))->alignEnd(),
                TextColumn::make('statut')->label('État')->badge()->formatStateUsing(fn (string $state): string => OrdreDepense::LIBELLES[$state] ?? $state),
            ])
            ->headerActions([
                Action::make('tous')->label('Tous les ordres de dépense')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->url(OrdreDepenseResource::getUrl('index')),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
