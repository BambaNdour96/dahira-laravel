<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Models\Instance;
use App\Services\BilanComiteService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/** À la mise en place : les améliorations notées au dernier comité clos du même type d'événement. */
class AmeliorationsPrecedentes extends Widget
{
    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.ameliorations-precedentes';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['ameliorations' => $this->record instanceof Instance ? app(BilanComiteService::class)->ameliorationsPrecedentes($this->record) : []];
    }
}
