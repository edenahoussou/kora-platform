<?php

namespace App\Modules\Boarding\Actions;

use App\Modules\Boarding\Exceptions\BoardingTicketUnknownException;
use App\Modules\Ticketing\Models\Ticket;

class ResolveTicketForBoardingAction
{
    public function __construct(
        private VerifyTicketQrSignatureAction $verifySignature,
    ) {}

    /**
     * @throws BoardingTicketUnknownException Signature invalide, contenu
     *                                        incohérent avec le billet en base, ou aucun billet trouvé.
     */
    public function execute(?string $qrPayload, ?string $shortCode): Ticket
    {
        if ($qrPayload !== null) {
            return $this->resolveFromQr($qrPayload);
        }

        $ticket = Ticket::query()->where('short_code', $shortCode)->first();

        if ($ticket === null) {
            throw new BoardingTicketUnknownException;
        }

        return $ticket;
    }

    private function resolveFromQr(string $qrPayload): Ticket
    {
        $payload = $this->verifySignature->execute($qrPayload);

        if ($payload === null || ! isset($payload['t']) || ! is_string($payload['t'])) {
            throw new BoardingTicketUnknownException;
        }

        $ticket = Ticket::query()->where('uuid', $payload['t'])->first();

        // Le contenu signé doit correspondre exactement au billet en base
        // (défense en profondeur contre un QR valide mais périmé/rejoué
        // avec des données divergentes) — le short_code fait foi.
        if ($ticket === null || (isset($payload['s']) && $ticket->short_code !== $payload['s'])) {
            throw new BoardingTicketUnknownException;
        }

        return $ticket;
    }
}
