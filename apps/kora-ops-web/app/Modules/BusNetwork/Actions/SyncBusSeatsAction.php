<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Facades\DB;

class SyncBusSeatsAction
{
    public function execute(Bus $bus, BusComposition $composition): void
    {
        DB::transaction(function () use ($bus, $composition): void {
            $bus->seats()->delete();

            $seats = [];
            foreach ($composition->layout as $row) {
                foreach ($row['codes'] as $code) {
                    $isCrew = ($row['type'] ?? 'standard') === 'crew';
                    $seats[] = [
                        'code' => $code,
                        'type' => $row['type'] ?? 'standard',
                        'sellable' => ! $isCrew,
                        'status' => $isCrew ? 'blocked' : 'active',
                    ];
                }
            }

            $bus->seats()->createMany($seats);
        });
    }
}
