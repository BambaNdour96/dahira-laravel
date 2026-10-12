<?php

namespace App\Filament\Resources\Projets\Widgets;

use App\Enums\TypeSuivi;
use App\Models\Projet;
use App\Models\SuiviProjet;
use App\Services\CollecteService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/** Le journal du projet : points d'avancement, risques, décisions et dépenses, datés et signés. */
class JournalDuProjet extends TableWidget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Journal de suivi')
            ->query(fn (): Builder => SuiviProjet::query()->where('projet_id', $this->projet()->id)->with('auteur'))
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('texte')->label('Contenu')->wrap()->limit(200),
                TextColumn::make('montant')->label('Montant')->placeholder('—')
                    ->formatStateUsing(fn (mixed $state): string => CollecteService::fcfa((int) $state)),
                TextColumn::make('auteur.name')->label('Par')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Type')->options(TypeSuivi::class),
            ])
            ->headerActions([
                Action::make('noter')
                    ->label('Ajouter au journal')
                    ->icon('heroicon-o-pencil')
                    ->visible(fn (): bool => Gate::allows('update', $this->projet()))
                    ->schema([
                        Select::make('type')->label('Type')->options(TypeSuivi::class)->default(TypeSuivi::Point->value)->required()->live(),
                        Textarea::make('texte')->label('Contenu')->rows(4)->required()->maxLength(5000),
                        TextInput::make('montant')
                            ->label('Montant (FCFA)')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => in_array($get('type'), [TypeSuivi::Depense, TypeSuivi::Depense->value], true))
                            ->required(fn (Get $get): bool => in_array($get('type'), [TypeSuivi::Depense, TypeSuivi::Depense->value], true)),
                    ])
                    ->action(function (array $data): void {
                        $this->projet()->suivis()->create([...$data, 'auteur_id' => Auth::id()]);
                        Notification::make()->title('Ajouté au journal du projet.')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private function projet(): Projet
    {
        abort_unless($this->record instanceof Projet, 404);

        return $this->record;
    }
}
