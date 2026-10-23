<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Actions\UpdateSeatStatusAction;
use App\Modules\BusNetwork\Http\Requests\UpdateSeatStatusRequest;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Seat;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BusSeatStatusController extends Controller
{
    public function update(
        UpdateSeatStatusRequest $request,
        Bus $bus,
        Seat $seat,
        UpdateSeatStatusAction $updateSeatStatus,
    ): RedirectResponse {
        $updateSeatStatus->execute($bus, $seat, $request->seatStatus(), $request->reason(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut du siège :code mis à jour.', ['code' => $seat->code])]);

        return back();
    }
}
