<?php

namespace App\Filament\Resources\Membres\Tables;

use App\Enums\NiveauEtudes;
use App\Enums\Sexe;
use App\Enums\SituationProfessionnelle;
use App\Enums\StatutMembre;
use App\Filament\Actions\EnTantQueAction;
use App\Filament\Resources\Membres\Actions\ChangerStatutAction;
use App\Filament\Resources\Paiements\PaiementActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MembresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')->label('Photo')->disk('local')->visibility('private')->circular(),
                TextColumn::make('matricule')->label('Matricule')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('prenom')->label('Prénom')->searchable()->sortable(),
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('telephone')->label('Téléphone')->searchable(),
                TextColumn::make('cellule.nom')->label('Cellule')->sortable(),
                TextColumn::make('poste.nom')->label('Poste')->placeholder('—')->toggleable(),
                TextColumn::make('commissions.nom')->label('Commissions')->badge()->placeholder('—')->toggleable(),
                TextColumn::make('situation_professionnelle')->label('Situation')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('niveau_etudes')->label('Études')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('statut')->label('Statut')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutMembre::class),
                SelectFilter::make('cellule')->label('Cellule')->relationship('cellule', 'nom'),
                SelectFilter::make('commissions')->label('Commission')->relationship('commissions', 'nom'),
                SelectFilter::make('poste')->label('Poste')->relationship('poste', 'nom'),
                SelectFilter::make('situation_professionnelle')->label('Situation professionnelle')->options(SituationProfessionnelle::class),
                SelectFilter::make('niveau_etudes')->label('Niveau d\'études')->options(NiveauEtudes::class),
                SelectFilter::make('sexe')->label('Sexe')->options(Sexe::class),
            ])
            ->recordActions([
                EditAction::make(),
                EnTantQueAction::pourMembre(),
                PaiementActions::especes(),
                ActionGroup::make(ChangerStatutAction::toutes())
                    ->label('Statut')
                    ->icon('heroicon-o-arrows-right-left'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
