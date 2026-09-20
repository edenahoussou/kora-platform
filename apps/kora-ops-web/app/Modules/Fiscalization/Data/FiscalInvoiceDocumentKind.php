<?php

namespace App\Modules\Fiscalization\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum FiscalInvoiceDocumentKind: string
{
    use HasEnumUtilities;

    case Sale = 'sale';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Facture de vente',
            self::CreditNote => 'Facture d’avoir',
        };
    }
}
