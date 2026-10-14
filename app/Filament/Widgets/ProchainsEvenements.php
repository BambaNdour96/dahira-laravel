<?php

namespace App\Filament\Widgets;

use App\Models\Evenement;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/** Les prochains rendez-vous de la Daara, et la présence des derniers. */
class ProchainsEvenements extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && $utilisateur->hasAnyRole(['super_administrateur', 'jewrine_national', 'secretaire_general', 'suivi_evaluation']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Événements : à venir et récents')
            ->query(fn () => Evenement::query()
                ->maintenus()
                ->where('debut', '>=', now()->subDays(30))
                ->withCount('presents'))
            ->columns([
                TextColumn::make('titre')->label('Événement')->weight('bold'),
                TextColumn::make('debut')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('lieu')->label('Lieu'),
                TextColumn::make('presents_count')->label('Présents'),
                TextColumn::make('quand')->label('')->badge()
                    ->state(fn (Evenement $record): string => $record->debut->isFuture() ? 'À venir' : 'Passé')
                    ->color(fn (string $state): string => $state === 'À venir' ? 'info' : 'gray'),
            ])
            ->defaultSort('debut')
            ->paginated([5]);
    }
}
