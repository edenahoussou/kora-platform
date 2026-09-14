<?php

namespace App\Modules\IdentityAccess\Http\Middleware;

use App\Modules\IdentityAccess\Actions\ResolveTerminalBySerialAction;
use App\Modules\IdentityAccess\Exceptions\TerminalNotUsableException;
use App\Modules\IdentityAccess\Exceptions\TerminalUnknownException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse toute opération OPS Mobile (sync, scan, impression) tant que le
 * terminal présenté n'est pas enregistré et actif. Le numéro de série est
 * attendu dans l'en-tête X-Terminal-Serial (à défaut, dans le payload
 * `serial_number`). Le terminal résolu est mis à disposition de la route
 * via $request->attributes->get('terminal').
 */
class EnsureTerminalIsUsable
{
    public function __construct(
        private ResolveTerminalBySerialAction $resolveTerminal,
    ) {}

    /**
     * @throws TerminalUnknownException|TerminalNotUsableException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $serialNumber = (string) ($request->header('X-Terminal-Serial') ?? $request->input('serial_number'));

        $terminal = $this->resolveTerminal->execute($serialNumber);

        if (! $terminal->isUsable()) {
            throw new TerminalNotUsableException($terminal);
        }

        $request->attributes->set('terminal', $terminal);

        return $next($request);
    }
}
