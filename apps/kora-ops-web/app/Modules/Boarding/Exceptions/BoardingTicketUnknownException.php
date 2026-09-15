<?php

namespace App\Modules\Boarding\Exceptions;

use App\Modules\Boarding\Data\BoardingFunctionalCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Aucun billet résolu (signature invalide, uuid/short_code inconnu, ou
 * incohérence entre le contenu du QR et le billet en base). Seul cas des
 * six codes fonctionnels qui n'est jamais persisté dans boarding_events :
 * sans billet résolu, il n'y a pas de ticket_id valide (colonne NOT NULL).
 */
class BoardingTicketUnknownException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(BoardingFunctionalCode::QrUnknown->message());
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => BoardingFunctionalCode::QrUnknown->value,
                'message' => BoardingFunctionalCode::QrUnknown->message(),
            ],
        ], BoardingFunctionalCode::QrUnknown->httpStatus());
    }
}
