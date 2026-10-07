<?php

namespace App\Filament\Resources\Paiements;

use App\Enums\MoyenPaiement;
use App\Enums\StatutPaiement;
use App\Filament\Resources\Paiements\Pages\ManagePaiements;
use App\Models\Paiement;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaiementResource extends Resource
{
    protected static ?string $model = Paiement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'paiement';

    protected static ?string $pluralModelLabel = 'paiements';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['membre.cellule', 'traitePar']))
            ->columns([
                TextColumn::make('numero_recu')->label('Reçu')->fontFamily('mono')->placeholder('—')->searchable(),
                TextColumn::make('declare_le')->label('Déclaré le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('membre.prenom')->label('Prénom')->searchable(),
                TextColumn::make('membre.nom')->label('Nom')->searchable(),
                TextColumn::make('membre.cellule.nom')->label('Cellule'),
                TextColumn::make('objet')
                    ->label('Objet')
                    ->state(fn (Paiement $record): string => $record->libelleObjet())
                    ->badge()
                    ->color(fn (Paiement $record): string => $record->objet === 'cotisation' ? 'info' : 'primary'),
                TextColumn::make('montant')
                    ->label('Montant')
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', ' ').' FCFA'),
                TextColumn::make('moyen')->label('Moyen')->badge(),
                TextColumn::make('reference')->label('Référence Wave')->fontFamily('mono')->copyable()->searchable()->placeholder('—'),
                TextColumn::make('numero_wave')->label('Numéro Wave')->placeholder('—')->searchable(),
                TextColumn::make('statut')->label('Statut')->badge(),
                TextColumn::make('motif_refus')->label('Motif du refus')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('traitePar.name')->label('Traité par')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutPaiement::class)->default(StatutPaiement::Declare->value),
                SelectFilter::make('objet')->label('Objet')->options(['frais_carte' => 'Frais de carte', 'cotisation' => 'Cotisation']),
                SelectFilter::make('moyen')->label('Moyen')->options(MoyenPaiement::class),
            ])
            ->recordActions([
                PaiementActions::confirmer(),
                PaiementActions::refuser(),
                PaiementActions::recu(),
            ])
            ->defaultSort('declare_le', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManagePaiements::route('/'),
        ];
    }
}
