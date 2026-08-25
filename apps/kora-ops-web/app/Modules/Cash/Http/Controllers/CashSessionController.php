<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Actions\OpenCashSessionAction;
use App\Modules\Cash\Http\Requests\StoreCashSessionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CashSessionController extends Controller
{
    public function store(
        StoreCashSessionRequest $request,
        OpenCashSessionAction $openCashSession,
    ): RedirectResponse {
        $openCashSession->execute($request->user(), $request->openingFloat());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Caisse ouverte. Vous pouvez confirmer le paiement.'),
        ]);

        return back();
    }
}
