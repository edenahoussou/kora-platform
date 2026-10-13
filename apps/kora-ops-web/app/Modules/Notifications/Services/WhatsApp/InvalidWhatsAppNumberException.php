<?php

namespace App\Modules\Notifications\Services\WhatsApp;

use RuntimeException;

/**
 * WAHA « no LID found » : le numéro n'est pas enregistré sur WhatsApp.
 * Erreur définitive — inutile de retenter ni de remonter à Sentry.
 */
class InvalidWhatsAppNumberException extends RuntimeException
{
    public function __construct(public readonly ?string $phone = null, string $message = '')
    {
        parent::__construct($message ?: "Le numéro {$phone} n'est pas un numéro WhatsApp valide.");
    }
}
