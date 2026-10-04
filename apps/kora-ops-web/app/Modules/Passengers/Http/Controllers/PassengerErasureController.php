<?php

namespace App\Modules\Passengers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConfirmSensitiveActionRequest;
use App\Modules\Passengers\Actions\ErasePassengerAccountAction;
use App\Modules\Passengers\Actions\ExportPassengerDataAction;
use App\Modules\Passengers\Data\PassengerErasureStatus;
use App\Modules\Passengers\Models\PassengerErasureRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export et effacement définitif des données d'un passager (droit à l'oubli).
 */
class PassengerErasureController extends Controller
{
    public function __construct(
        private readonly ExportPassengerDataAction $exportPassengerData,
        private readonly ErasePassengerAccountAction $erasePassengerAccount,
    ) {}

    public function export(Request $request, string $phone): BinaryFileResponse
    {
        Gate::authorize('sensitive-data.export');

        $export = $this->exportPassengerData->execute($phone);

        if ($export === null) {
            abort(404, 'Passager non trouvé.');
        }

        $actor = $request->user();

        PassengerErasureRequest::query()->updateOrCreate(
            ['phone_hash' => hash_hmac('sha256', $phone, (string) config('app.key'))],
            [
                'requested_by_user_id' => $actor?->getKey(),
                'actor_name' => $actor?->name,
                'status' => PassengerErasureStatus::Exported,
                'scope' => $export['scope'],
                'export_checksum' => $export['checksum'],
                'ip_address' => $request->ip(),
            ],
        );

        activity('passengers')
            ->causedBy($actor)
            ->withProperties([
                'phone_hash' => hash_hmac('sha256', $phone, (string) config('app.key')),
                'scope' => $export['scope'],
            ])
            ->log('passenger_data_exported');

        return response()
            ->download($export['path'], 'kora-passenger-data.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function destroy(ConfirmSensitiveActionRequest $request, string $phone): RedirectResponse
    {
        Gate::authorize('passengers.erase');

        $erasure = $this->erasePassengerAccount->execute($phone, $request->user());

        if ($erasure === null) {
            abort(404, 'Passager non trouvé.');
        }

        return redirect()
            ->route('passengers.index')
            ->with('success', 'Les données personnelles du passager ont été définitivement effacées.');
    }
}
