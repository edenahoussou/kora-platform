<?php

namespace App\Modules\BusNetwork\Actions;

use App\Models\User;
use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Seat;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class UpdateSeatStatusAction
{
    /** Change le statut opérationnel d'un siège et trace l'événement avec son motif. */
    public function execute(Bus $bus, Seat $seat, SeatStatus $status, ?string $reason, ?User $actor): Seat
    {
        return DB::transaction(function () use ($bus, $seat, $status, $reason, $actor): Seat {
            $seat = Seat::query()->whereKey($seat->id)->lockForUpdate()->firstOrFail();

            if ($seat->bus_id !== $bus->id) {
                throw (new ModelNotFoundException)->setModel(Seat::class, [$seat->uuid]);
            }

            $seat->statusEvents()->create([
                'bus_id' => $bus->id,
                'from_status' => (string) $seat->getRawOriginal('status'),
                'to_status' => $status->value,
                'reason' => $reason,
                'changed_by' => $actor?->id,
            ]);

            $seat->update([
                'status' => $status->value,
                'status_reason' => $status === SeatStatus::Active ? null : $reason,
            ]);

            return $seat->refresh();
        });
    }
}
