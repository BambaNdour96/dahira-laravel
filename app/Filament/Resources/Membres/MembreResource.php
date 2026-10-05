<?php

namespace App\Filament\Resources\Membres;

use App\Filament\Resources\Membres\Pages\CreateMembre;
use App\Filament\Resources\Membres\Pages\EditMembre;
use App\Filament\Resources\Membres\Pages\ListMembres;
use App\Filament\Resources\Membres\Schemas\MembreForm;
use App\Filament\Resources\Membres\Tables\MembresTable;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MembreResource extends Resource
{
    protected static ?string $model = Membre::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Registre';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'membre';

    protected static ?string $pluralModelLabel = 'membres';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return MembreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembresTable::configure($table);
    }

    /**
     * Un Jewrine de cellule ne voit que les membres de ses cellules.
     * L'administrateur et le trésorier voient tout le monde.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $requete = parent::getEloquentQuery();
        $utilisateur = Auth::user();

        if ($utilisateur instanceof User && ! $utilisateur->hasAnyRole(['administrateur', 'tresorier'])) {
            $requete->whereHas('cellule', fn ($cellule) => $cellule->where('responsable_id', $utilisateur->id));
        }

        return $requete;
    }

    /**
     * Limite la liste des cellules proposées dans le formulaire.
     *
     * @param  Builder<Cellule>  $requete
     * @return Builder<Cellule>
     */
    public static function limiterCellules(Builder $requete): Builder
    {
        $utilisateur = Auth::user();

        if ($utilisateur instanceof User && ! $utilisateur->hasRole('administrateur')) {
            $requete->where('responsable_id', $utilisateur->id);
        }

        return $requete;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListMembres::route('/'),
            'create' => CreateMembre::route('/create'),
            'edit' => EditMembre::route('/{record}/edit'),
        ];
    }
}
