<?php

namespace App\Modules\Booking\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatUnavailableException extends Exception
{
    public function __construct(
        string $message = 'Le siège n\'est plus disponible sur ce segment.',
        public readonly ?string $conflictingSeatUuid = null,
        public readonly ?string $seatCode = null,
        int $code = 409,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'conflicting_seat_uuid' => $this->conflictingSeatUuid,
            'seat_code' => $this->seatCode,
        ], 409);
    }
}
