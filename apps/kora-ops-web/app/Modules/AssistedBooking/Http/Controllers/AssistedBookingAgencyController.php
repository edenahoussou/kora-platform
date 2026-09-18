<?php

namespace App\Modules\AssistedBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Http\JsonResponse;

class AssistedBookingAgencyController extends Controller
{
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
