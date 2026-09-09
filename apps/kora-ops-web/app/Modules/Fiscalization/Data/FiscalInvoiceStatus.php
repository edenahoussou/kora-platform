<?php

namespace App\Modules\Fiscalization\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;
use LogicException;

enum FiscalInvoiceStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Certified = 'certified';
    case Failed = 'failed';
    // Placeholder pour les factures d'avoir (FA/EA) — non implémenté dans le MVP Bus.
    // Sera activé quand le workflow d'annulation/avoir e-MECeF sera câblé.
    case Canceled = 'canceled';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Certified, self::Failed, self::Canceled], true),
            self::Failed => $target === self::Pending,
            self::Certified, self::Canceled => false,
        };
    }

    public function transitionTo(self $target): self
    {
        if (! $this->canTransitionTo($target)) {
            throw new LogicException("Transition fiscale impossible : {$this->value} -> {$target->value}.");
        }

        return $target;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente', self::Certified => 'Certifiée', self::Failed => 'Échec', self::Canceled => 'Annulée',
        };
    }
}
