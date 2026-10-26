<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\CompositionStatus;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Validation\ValidationException;

class ChangeSeatLayoutStatusAction
{
    public function execute(BusComposition $composition, CompositionStatus $status): BusComposition
    {
        if ($status !== CompositionStatus::Active && $composition->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Impossible de mettre en brouillon ou d’archiver un gabarit utilisé par au moins un bus.',
            ]);
        }

        $composition->update(['status' => $status->value]);

        return $composition->refresh();
    }
}
