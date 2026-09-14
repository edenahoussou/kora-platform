<?php

namespace App\Modules\IdentityAccess\Exceptions;

use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

use function Illuminate\Support\enum_value;

/**
 * Levée quand un terminal enregistré n'est pas dans un état permettant la
 * connexion ou une opération (inactif, en maintenance ou déclaré perdu).
 */
class TerminalNotUsableException extends RuntimeException
{
    public function __construct(
        public readonly TerminalP501 $terminal,
    ) {
        parent::__construct("Le terminal {$terminal->serial_number} n'est pas utilisable (statut : {$this->statusValue()}).");
    }

    public function functionalCode(): string
    {
        return match ($this->statusValue()) {
            TerminalP501Status::Lost->value => 'terminal_lost',
            TerminalP501Status::Maintenance->value => 'terminal_maintenance',
            default => 'terminal_inactive',
        };
    }

    private function statusValue(): string
    {
        return enum_value($this->terminal->status);
    }

    public function render(Request $request): JsonResponse
    {
        $messages = [
            'terminal_lost' => 'Ce terminal a été déclaré perdu. Il ne peut plus être utilisé.',
            'terminal_maintenance' => 'Ce terminal est en maintenance. Réessayez plus tard.',
            'terminal_inactive' => 'Ce terminal est désactivé. Contactez votre agence.',
        ];

        $code = $this->functionalCode();

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $messages[$code],
            ],
        ], 403);
    }
}
