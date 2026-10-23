<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\SeatStatus;
use App\Modules\BusNetwork\Data\SeatType;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use Illuminate\Support\Facades\DB;

class SyncBusSeatsAction
{
    public function execute(Bus $bus, BusComposition $composition): void
    {
        DB::transaction(function () use ($bus, $composition): void {
            $bus->seats()->delete();

            $bus->seats()->createMany(
                is_array($composition->grid) && ($composition->grid['cells'] ?? []) !== []
                    ? $this->seatsFromGrid($composition)
                    : $this->seatsFromLegacyLayout($composition),
            );
        });
    }

    /**
     * Cellules siège de la grille → sièges matérialisés du bus.
     *
     * @return list<array{code: string, type: string, sellable: bool, status: string, status_reason: string|null}>
     */
    private function seatsFromGrid(BusComposition $composition): array
    {
        $seats = [];
        foreach ($composition->seatGrid()->seatCells() as $cell) {
            $type = (string) ($cell['seatKind'] ?? SeatType::Standard->value);
            $isCrew = $type === SeatType::Crew->value;
            $isMaintenance = ($cell['maintenance'] ?? false) === true;
            $seats[] = [
                'code' => (string) ($cell['code'] ?? ''),
                'type' => $type,
                'sellable' => ! $isCrew && ! $isMaintenance,
                'status' => $isMaintenance
                    ? SeatStatus::Maintenance->value
                    : ($isCrew ? SeatStatus::Blocked->value : SeatStatus::Active->value),
                'status_reason' => $isMaintenance ? 'Siège marqué hors service dans le gabarit.' : null,
            ];
        }

        return $seats;
    }

    /** @return list<array{code: string, type: string, sellable: bool, status: string}> */
    private function seatsFromLegacyLayout(BusComposition $composition): array
    {
        $seats = [];
        foreach ($composition->layout as $row) {
            $type = (string) ($row['type'] ?? SeatType::Standard->value);
            $isCrew = $type === SeatType::Crew->value;
            $isMaintenance = $type === 'maintenance';
            foreach ($row['codes'] as $code) {
                $seats[] = [
                    'code' => $code,
                    'type' => $isMaintenance ? SeatType::Standard->value : $type,
                    'sellable' => ! $isCrew && ! $isMaintenance,
                    'status' => $isMaintenance
                        ? SeatStatus::Maintenance->value
                        : ($isCrew ? SeatStatus::Blocked->value : SeatStatus::Active->value),
                ];
            }
        }

        return $seats;
    }
}
