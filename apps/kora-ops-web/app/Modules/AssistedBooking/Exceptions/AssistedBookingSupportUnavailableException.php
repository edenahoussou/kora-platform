<?php

namespace App\Modules\AssistedBooking\Exceptions;

use App\Modules\AssistedBooking\Data\AssistedBookingChannel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistedBookingSupportUnavailableException extends Exception
{
    public function __construct(public readonly AssistedBookingChannel $channel)
    {
        parent::__construct('Le canal de support sélectionné n’est pas configuré.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'assisted_booking_support_unavailable',
                'message' => $this->getMessage(),
                'channel' => $this->channel->value,
            ],
        ], 503);
    }
}
