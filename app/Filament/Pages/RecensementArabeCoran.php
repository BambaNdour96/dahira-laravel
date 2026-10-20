<?php

namespace App\Filament\Pages;

use App\Enums\NiveauComprehension;
use App\Enums\NiveauKhassaides;
use App\Enums\NiveauLecture;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\User;
use App\Services\ArabeCoranService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Le recensement Arabe et Coran dans le panneau, pour le super administrateur (la commission Culturelle l'a sur le téléphone). */
class RecensementArabeCoran extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Arabe et Coran';

    protected static ?string $title = 'Recensement Arabe et Coran';

    protected static ?string $slug = 'recensement-arabe-coran';

    protected string $view = 'filament.recensement-arabe-coran';

    public static function canAccess(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->can('arabe_coran.voir');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['chiffres' => app(ArabeCoranService::class)->chiffresParCellule()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(ArabeCoranService::class)->requete(null, null))
            ->columns([
                TextColumn::make('nom_complet')->label('Membre'),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('contact')->label('Téléphone')->state(fn (Membre $record): string => $record->telephone ?? $record->responsable->telephone ?? '—'),
                TextColumn::make('profilArabeCoran.lecture')->label('Lecture')->formatStateUsing(fn (?NiveauLecture $state): string => $state?->libelle() ?? '—'),
                TextColumn::make('profilArabeCoran.ecriture')->label('Écriture')->formatStateUsing(fn (?NiveauLecture $state): string => $state?->libelle(true) ?? '—'),
                TextColumn::make('profilArabeCoran.comprehension')->label('Compréhension')->formatStateUsing(fn (?NiveauComprehension $state): string => $state?->getLabel() ?? '—'),
                TextColumn::make('coran')->label('Coran')->state(fn (Membre $record): string => $record->profilArabeCoran?->libelleCoran() ?? '—'),
                TextColumn::make('profilArabeCoran.khassaides')->label('Khassaides')->formatStateUsing(fn (?NiveauKhassaides $state): string => $state?->getLabel() ?? '—'),
                TextColumn::make('apprendre')->label('Souhaite apprendre')->state(fn (Membre $record): string => match ($record->profilArabeCoran?->souhaite_apprendre) {
                    true => 'Oui', false => 'Non', default => '—',
                }),
            ])
            ->filters([
                SelectFilter::make('cellule_id')->label('Cellule')->options(fn (): array => Cellule::query()->enActivite()->orderBy('nom')->pluck('nom', 'id')->all()),
                SelectFilter::make('critere')->label('Critère')->options(ArabeCoranService::CRITERES)
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereIn('membres.id', app(ArabeCoranService::class)->requete(null, (string) $data['value'])->select('membres.id'))),
            ]);
    }
}
