<?php

namespace App\Modules\AssistedBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Http\JsonResponse;

/**
 * @group Assisted Bookings
 *
 * Assisted booking handoff endpoints for the mobile app.
 */
class AssistedBookingAgencyController extends Controller
{
    /**
     * List agencies eligible for assisted booking.
     *
     * Returns active agencies that expose at least one public contact
     * channel (call or WhatsApp). The mobile client only enables a channel
     * button when the matching phone number is present.
     *
     * @response 200 scenario="Eligible agencies" {
     *   "data": [
     *     {
     *       "uuid": "9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f",
     *       "code": "AG-000001",
     *       "name": "Agence Dantokpa",
     *       "city": "Cotonou",
     *       "call_phone": "+229010000001",
     *       "whatsapp_phone": "+229010000002"
     *     }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        $agencies = Agency::query()
            ->where('status', AgencyStatus::Active)
            ->where(function ($query): void {
                $query->whereNotNull('call_phone')->orWhereNotNull('whatsapp_phone');
            })
            ->orderBy('city')
            ->orderBy('name')
            ->get()
            ->map(fn (Agency $agency): array => [
                'uuid' => $agency->uuid,
                'code' => $agency->code,
                'name' => $agency->name,
                'city' => $agency->city,
                'call_phone' => $agency->call_phone,
                'whatsapp_phone' => $agency->whatsapp_phone,
            ]);

        return response()->json(['data' => $agencies]);
    }
}
