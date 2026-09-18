<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Policies\Concerns\AuthorizesPermissions;

class FiscalInvoicePolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'fiscalization.view');
    }

    public function view(User $user, FiscalInvoice $fiscalInvoice): bool
    {
        return $this->allows($user, 'fiscalization.view');
    }

    public function retry(User $user, FiscalInvoice $fiscalInvoice): bool
    {
        return $this->allows($user, 'fiscalization.retry');
    }

    public function credit(User $user, FiscalInvoice $fiscalInvoice): bool
    {
        return $this->allows($user, 'fiscalization.credit');
    }

    public function viewPayload(User $user, FiscalInvoice $fiscalInvoice): bool
    {
        return $this->allows($user, 'fiscalization.payload.view');
    }
}
