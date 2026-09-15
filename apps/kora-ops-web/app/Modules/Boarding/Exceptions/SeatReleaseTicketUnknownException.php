<?php

namespace App\Modules\Boarding\Exceptions;

use App\Modules\Boarding\Data\SeatReleaseFunctionalCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SeatReleaseTicketUnknownException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(SeatReleaseFunctionalCode::TicketUnknown->message());
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => SeatReleaseFunctionalCode::TicketUnknown->value,
                'message' => SeatReleaseFunctionalCode::TicketUnknown->message(),
                'recommendedAction' => SeatReleaseFunctionalCode::TicketUnknown->recommendedAction(),
            ],
        ], SeatReleaseFunctionalCode::TicketUnknown->httpStatus());
    }
}
