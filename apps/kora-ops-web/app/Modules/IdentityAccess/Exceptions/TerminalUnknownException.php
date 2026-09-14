<?php

namespace App\Modules\IdentityAccess\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Levée quand le numéro de série présenté ne correspond à aucun terminal
 * P501 enregistré.
 */
class TerminalUnknownException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Terminal inconnu ou non enregistré.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'terminal_unknown',
                'message' => 'Ce terminal n’est pas enregistré. Contactez votre agence.',
            ],
        ], 404);
    }
}
