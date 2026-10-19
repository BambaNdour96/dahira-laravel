<?php

namespace App\Filament\Resources\Membres\Tables;

use App\Enums\NiveauEtudes;
use App\Enums\Sexe;
use App\Enums\SituationProfessionnelle;
use App\Enums\StatutMembre;
use App\Filament\Actions\EnTantQueAction;
use App\Filament\Actions\MotDePasseAction;
use App\Filament\Resources\Membres\Actions\ChangerStatutAction;
use App\Filament\Resources\Paiements\PaiementActions;
use App\Models\CampagneRenouvellement;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\Renouvellement;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('telephone')->label('Téléphone')->searchable()->placeholder('—'),
                TextColumn::make('responsable.nom_complet')->label('Fiche gérée par')->placeholder('—')->toggleable(),
                TextColumn::make('cellule.nom')->label('Cellule')->sortable(),
                TextColumn::make('poste')->label('Poste')->placeholder('—')->toggleable()
                    ->state(fn (Membre $record): ?string => $record->postePrincipal()?->nom),
                TextColumn::make('commissions.nom')->label('Commissions')->badge()->placeholder('—')->toggleable(),
                TextColumn::make('situation_professionnelle')->label('Situation')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('niveau_etudes')->label('Études')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('statut')->label('Statut')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Statut')->options(StatutMembre::class),
                SelectFilter::make('cellule')->label('Cellule')->relationship('cellule', 'nom'),
                SelectFilter::make('commissions')->label('Commission')->relationship('commissions', 'nom'),
                SelectFilter::make('poste')
                    ->label('Poste')
                    ->options(fn (): array => Poste::query()->where('actif', true)->orderBy('ordre')->pluck('nom', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereIn('user_id', Nomination::query()->where('poste_id', $data['value'])->select('user_id'))),
                SelectFilter::make('situation_professionnelle')->label('Situation professionnelle')->options(SituationProfessionnelle::class),
                SelectFilter::make('niveau_etudes')->label('Niveau d\'études')->options(NiveauEtudes::class),
                SelectFilter::make('sexe')->label('Sexe')->options(Sexe::class),
                SelectFilter::make('renouvellement')
                    ->label('Renouvellement (dernière campagne)')
                    ->options(Renouvellement::LIBELLES)
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereIn('id', Renouvellement::query()
                            ->where('campagne_id', CampagneRenouvellement::query()->latest('ouverte_le')->value('id') ?? 0)
                            ->where('statut', $data['value'])
                            ->select('membre_id'))),
                SelectFilter::make('competences')->label('Savoir-faire')->relationship('competences', 'nom')->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
                EnTantQueAction::pourMembre(),
                MotDePasseAction::pourMembre(),
                PaiementActions::especes(),
                PaiementActions::cotisationEspeces(),
                PaiementActions::renouvellementEspeces(),
                PaiementActions::cartePhysiqueEspeces(),
                PaiementActions::remettreCartePhysique(),
                PaiementActions::exoneration(),
                ActionGroup::make(ChangerStatutAction::toutes())
                    ->label('Statut')
                    ->icon('heroicon-o-arrows-right-left'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
