<?php

namespace App\Filament\Resources\ReglesValidation;

use App\Filament\Resources\ReglesValidation\Pages\ManageReglesValidation;
use App\Models\Poste;
use App\Models\RegleValidation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Qui valide les paiements des postes clés du bureau. Réservé au super administrateur.
 */
class RegleValidationResource extends Resource
{
    protected static ?string $model = RegleValidation::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'validation-bureau';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 12;

    protected static ?string $modelLabel = 'règle de validation';

    protected static ?string $pluralModelLabel = 'règles de validation';

    protected static ?string $navigationLabel = 'Validation du bureau';

    private static function estSuperAdmin(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasRole('super_administrateur');
    }

    public static function canViewAny(): bool
    {
        return self::estSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return self::estSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::estSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return self::estSuperAdmin();
    }

    /** @return array<int, string> */
    private static function postes(): array
    {
        return Poste::query()->orderBy('id')->pluck('nom', 'id')->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('poste_id')
                ->label('Paiements du poste')
                ->options(fn (): array => self::postes())
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Le poste dont les paiements demandent une validation particulière.'),
            Select::make('validateurs')
                ->label('Validés par')
                ->multiple()
                ->options(fn (): array => self::postes())
                ->required()
                ->helperText('Un poste ne peut jamais valider ses propres paiements. Le super administrateur peut toujours valider, en secours.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('poste.nom')->label('Paiements du poste'),
                TextColumn::make('validateurs')
                    ->label('Validés par')
                    ->state(fn (RegleValidation $record): string => $record->libelleValidateurs()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageReglesValidation::route('/'),
        ];
    }
}
