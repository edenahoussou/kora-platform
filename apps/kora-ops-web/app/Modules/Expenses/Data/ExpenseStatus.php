<?php

namespace App\Modules\Expenses\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum ExpenseStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Approved => 'Approuvée',
            self::Rejected => 'Rejetée',
            self::Reversed => 'Contrepassée',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Approved, self::Rejected], true),
            self::Approved => $target === self::Reversed,
            self::Rejected, self::Reversed => false,
        };
    }
}
