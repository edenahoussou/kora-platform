<?php

namespace App\Modules\Notifications\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum WhatsAppDeliveryStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case InvalidNumber = 'invalid_number';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente', self::Sent => 'Envoyé', self::Failed => 'Échec', self::Skipped => 'Ignoré',
            self::InvalidNumber => 'Numéro invalide',
        };
    }
}
