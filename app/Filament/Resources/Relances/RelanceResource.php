<?php

namespace App\Filament\Resources\Relances;

use App\Enums\StatutMembre;
use App\Filament\Resources\Membres\MembreResource;
use App\Filament\Resources\Relances\Pages\ListRelances;
use App\Models\Membre;
use App\Models\User;
use App\Services\CotisationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Les membres en retard de cotisation, à relancer.
 * Chaque Jewrine de cellule ne voit que les membres de sa cellule.
 */
class RelanceResource extends Resource
{
    protected static ?string $model = Membre::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'relances';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Trésorerie';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'relance';

    protected static ?string $pluralModelLabel = 'relances';

    protected static ?string $navigationLabel = 'Relances';

    public static function canViewAny(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && $utilisateur->hasAnyRole(['super_administrateur', 'tresorier', 'responsable_section']);
    }

    /**
     * Identifiants des membres visibles ayant au moins ce nombre de mois de retard.
     *
     * @return list<int>
     */
    public static function idsEnRetard(int $minimum = 1): array
    {
        $valides = Membre::query()
            ->whereIn('id', MembreResource::getEloquentQuery()->select('membres.id'))
            ->where('statut', StatutMembre::Valide->value)
            ->get();

        $retards = app(CotisationService::class)->retards($valides);

        return array_keys(array_filter($retards, fn (int $nombre): bool => $nombre >= $minimum));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn('membres.id', self::idsEnRetard())->with('cellule'))
            ->columns([
                TextColumn::make('prenom')->label('Prénom')->searchable(),
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('cellule.nom')->label('Cellule'),
                TextColumn::make('telephone')->label('Téléphone'),
                TextColumn::make('retard')
                    ->label('Retard')
                    ->state(fn (Membre $record): string => count(app(CotisationService::class)->moisEnRetard($record)).' mois')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('du')
                    ->label('Montant dû')
                    ->state(function (Membre $record): string {
                        $service = app(CotisationService::class);
                        $montant = count($service->moisEnRetard($record)) * $service->montantMensuel();

                        return number_format($montant, 0, ',', ' ').' FCFA';
                    }),
                TextColumn::make('relance_le')->label('Dernière relance')->dateTime('d/m/Y H:i')->placeholder('Jamais'),
            ])
            ->filters([
                Filter::make('gros_retard')
                    ->label('2 mois de retard ou plus')
                    ->query(fn ($query) => $query->whereIn('membres.id', self::idsEnRetard(2))),
                Filter::make('jamais_relance')
                    ->label('Pas encore relancés ce mois-ci')
                    ->query(fn ($query) => $query->where(fn ($sous) => $sous
                        ->whereNull('relance_le')
                        ->orWhere('relance_le', '<', now()->startOfMonth()))),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('Relancer sur WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Membre $record): string => route('relances.whatsapp', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('nom');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRelances::route('/'),
        ];
    }
}
