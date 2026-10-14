<?php

namespace App\Filament\Resources\JournalExports;

use App\Filament\Resources\JournalExports\Pages\ListJournalExports;
use App\Models\JournalExport;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/** Qui a téléchargé quel document contenant des données personnelles, et quand. Lecture seule. */
class JournalExportResource extends Resource
{
    protected static ?string $model = JournalExport::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Journal des exports';

    protected static ?string $modelLabel = 'export';

    protected static ?string $pluralModelLabel = 'exports';

    public static function canViewAny(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->can('plateforme.administrer');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('user.name')->label('Par')->placeholder('—')->searchable(),
                TextColumn::make('document')->label('Document')->wrap()->searchable(),
                TextColumn::make('details')->label('Détails')->wrap()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListJournalExports::route('/')];
    }
}
