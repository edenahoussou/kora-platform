<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Exceptions\TerminalNotUsableException;
use App\Modules\IdentityAccess\Exceptions\TerminalUnknownException;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Support\Str;

use function activity;

class LogOpsSessionRefusalAction
{
    /**
     * Journalise un refus d'ouverture ou d'usage de session OPS
     * (`ops_session_refused`) avec le code fonctionnel renvoyé au mobile.
     * Le numéro de série présenté est conservé même pour un terminal inconnu
     * (utile à l'audit), tronqué à 64 caractères.
     */
    public function execute(
        OpsSessionException|TerminalNotUsableException|TerminalUnknownException $exception,
        ?User $user,
        ?TerminalP501 $terminal,
        string $serialNumber,
        ?string $ipAddress = null,
    ): void {
        $code = match (true) {
            $exception instanceof OpsSessionException, $exception instanceof TerminalNotUsableException => $exception->functionalCode(),
            default => 'terminal_unknown',
        };

        $properties = array_filter([
            'code' => $code,
            'reason' => $exception instanceof OpsSessionException ? $exception->reason : null,
            'serial_number' => Str::limit(trim($serialNumber), 64, ''),
            'ip' => $ipAddress,
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $logger = activity();

        if ($terminal !== null) {
            $logger->performedOn($terminal);
        }

        if ($user !== null) {
            $logger->causedBy($user);
        }

        $logger->withProperties($properties)->log('ops_session_refused');
    }
}
