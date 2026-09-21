<?php

namespace App\Modules\Notifications\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Tentative de désactiver une alerte critique (terminal révoqué, conflit de
 * scan) : refusée, ces alertes doivent toujours parvenir à l'utilisateur.
 */
class OpsNotificationPreferenceException extends RuntimeException
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(public readonly array $types)
    {
        parent::__construct('Les alertes critiques ne peuvent pas être désactivées.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'notification_not_disableable',
                'message' => $this->getMessage(),
                'types' => $this->types,
            ],
        ], 422);
    }
}
