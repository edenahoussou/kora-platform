<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Actions\HandshakeTerminalAction;
use App\Modules\IdentityAccess\Actions\LogTerminalActivityAction;
use App\Modules\IdentityAccess\Actions\RecordTerminalSyncAction;
use App\Modules\IdentityAccess\Actions\ResolveTerminalBySerialAction;
use App\Modules\IdentityAccess\Exceptions\TerminalNotUsableException;
use App\Modules\IdentityAccess\Http\Requests\TerminalActivityRequest;
use App\Modules\IdentityAccess\Http\Requests\TerminalHandshakeRequest;
use App\Modules\IdentityAccess\Http\Resources\TerminalP501Resource;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group OPS Mobile — Terminal
 *
 * Handshake et opérations liées au terminal P501 pour l'application OPS
 * Mobile. L'authentification de l'utilisateur (login, PIN, OTP) reste celle
 * de l'app KORA existante (auth:sanctum) ; ces endpoints ajoutent le
 * contrôle du terminal par-dessus une session déjà ouverte.
 */
class TerminalOpsMobileController extends Controller
{
    /**
     * Handshake du terminal : à appeler juste après le login pour lier la
     * session au terminal physique utilisé.
     *
     * @authenticated
     *
     * @response 200 scenario="Terminal actif" {
     *   "data": {
     *     "uuid": "b3091549-9179-454e-8440-58179f2208df",
     *     "serial_number": "P501-000123",
     *     "status": "active",
     *     "last_sync_at": null,
     *     "last_handshake_at": "2026-09-14T09:00:00.000Z"
     *   }
     * }
     * @response 404 scenario="Terminal unknown" {
     *   "error": { "code": "terminal_unknown", "message": "Ce terminal n’est pas enregistré. Contactez votre agence." }
     * }
     * @response 403 scenario="Terminal lost" {
     *   "error": { "code": "terminal_lost", "message": "Ce terminal a été déclaré perdu. Il ne peut plus être utilisé." }
     * }
     */
    public function handshake(
        TerminalHandshakeRequest $request,
        ResolveTerminalBySerialAction $resolveTerminal,
        HandshakeTerminalAction $handshakeTerminal,
    ): JsonResponse {
        $terminal = $this->resolveUsableTerminal($resolveTerminal, $request->serialNumber());

        $terminal = $handshakeTerminal
            ->execute($terminal, $request->user(), $this->currentTokenId($request))
            ->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    /**
     * Accuse réception d'une synchronisation réussie côté terminal et met
     * à jour last_sync_at. Le traitement du contenu du lot de
     * synchronisation (sync_batches/sync_operations) est hors périmètre.
     *
     * @authenticated
     */
    public function sync(Request $request, RecordTerminalSyncAction $recordSync): JsonResponse
    {
        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');

        $terminal = $recordSync
            ->execute($terminal, $request->user(), (array) $request->input('summary', []))
            ->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    /**
     * Journalise un scan, une impression ou une erreur pour le terminal en
     * cours d'utilisation.
     *
     * @authenticated
     */
    public function activity(TerminalActivityRequest $request, LogTerminalActivityAction $logActivity): JsonResponse
    {
        /** @var TerminalP501 $terminal */
        $terminal = $request->attributes->get('terminal');

        $logActivity->execute($terminal, $request->user(), $request->type(), $request->context());

        return response()->json(status: 204);
    }

    private function resolveUsableTerminal(ResolveTerminalBySerialAction $resolveTerminal, string $serialNumber): TerminalP501
    {
        $terminal = $resolveTerminal->execute($serialNumber);

        if (! $terminal->isUsable()) {
            throw new TerminalNotUsableException($terminal);
        }

        return $terminal;
    }

    /**
     * Extrait l'identifiant numérique (clé primaire) du jeton Sanctum
     * courant à partir du header Authorization, de la même façon que
     * Sanctum le fait en interne pour retrouver le jeton
     * (`{id}|{plainTextToken}`). Volontairement indépendant de
     * `currentAccessToken()`/`HasApiTokens`, pour rester correct même si
     * l'app personnalise le modèle de jeton Sanctum ou le guard utilisé.
     */
    private function currentTokenId(Request $request): ?int
    {
        $bearer = $request->bearerToken();

        if (! $bearer || ! str_contains($bearer, '|')) {
            return null;
        }

        [$id] = explode('|', $bearer, 2);

        return ctype_digit($id) ? (int) $id : null;
    }
}
