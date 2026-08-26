<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Actions\ChangeAgencyPaymentAccountStatusAction;
use App\Modules\Payments\Actions\CreateAgencyPaymentAccountAction;
use App\Modules\Payments\Http\Requests\StoreAgencyPaymentAccountRequest;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AgencyPaymentAccountController extends Controller
{
    public function store(
        StoreAgencyPaymentAccountRequest $request,
        CreateAgencyPaymentAccountAction $createAccount,
    ): RedirectResponse {
        $createAccount->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Compte Mobile Money ajouté.')]);

        return to_route('agencies.index');
    }

    public function changeStatus(
        Agency $agency,
        AgencyPaymentAccount $account,
        ChangeAgencyPaymentAccountStatusAction $changeStatus,
    ): RedirectResponse {
        Gate::authorize('update', $agency);
        abort_unless($account->agency_id === $agency->getKey(), 404);

        $status = request()->string('status')->value();
        abort_unless(in_array($status, ['active', 'inactive'], true), 422);
        $changeStatus->execute($account, $status);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut du compte Mobile Money mis à jour.')]);

        return to_route('agencies.index');
    }
}
