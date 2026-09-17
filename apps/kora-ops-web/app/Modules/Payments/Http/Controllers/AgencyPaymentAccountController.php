<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Actions\ChangeAgencyPaymentAccountStatusAction;
use App\Modules\Payments\Actions\CreateAgencyPaymentAccountAction;
use App\Modules\Payments\Actions\UpdateAgencyPaymentAccountAction;
use App\Modules\Payments\Http\Requests\ChangeAgencyPaymentAccountStatusRequest;
use App\Modules\Payments\Http\Requests\StoreAgencyPaymentAccountRequest;
use App\Modules\Payments\Http\Requests\UpdateAgencyPaymentAccountRequest;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AgencyPaymentAccountController extends Controller
{
    public function store(
        StoreAgencyPaymentAccountRequest $request,
        Agency $agency,
        CreateAgencyPaymentAccountAction $createAccount,
    ): RedirectResponse {
        $createAccount->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Compte Mobile Money ajouté.')]);

        return to_route('agencies.show', $agency);
    }

    public function update(
        UpdateAgencyPaymentAccountRequest $request,
        Agency $agency,
        AgencyPaymentAccount $account,
        UpdateAgencyPaymentAccountAction $updateAccount,
    ): RedirectResponse {
        abort_unless($account->agency_id === $agency->getKey(), 404);
        $updateAccount->execute($account, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Libellé du compte Mobile Money mis à jour.')]);

        return to_route('agencies.show', $agency);
    }

    public function changeStatus(
        ChangeAgencyPaymentAccountStatusRequest $request,
        Agency $agency,
        AgencyPaymentAccount $account,
        ChangeAgencyPaymentAccountStatusAction $changeStatus,
    ): RedirectResponse {
        abort_unless($account->agency_id === $agency->getKey(), 404);
        $changeStatus->execute($account, $request->status(), $request->replacementUuid());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut du compte Mobile Money mis à jour.')]);

        return to_route('agencies.show', $agency);
    }
}
