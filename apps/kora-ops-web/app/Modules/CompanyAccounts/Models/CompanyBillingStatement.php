<?php

namespace App\Modules\CompanyAccounts\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use App\Modules\CompanyAccounts\Data\CompanyBillingStatementStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CompanyBillingStatementStatus $status
 * @property array<int, array<string, mixed>>|null $lines
 * @property Carbon|null $generated_at
 * @property Carbon|null $emailed_at
 */
class CompanyBillingStatement extends KoraModel
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'company_account_id',
        'period_key',
        'reference',
        'status',
        'currency',
        'debit_total',
        'refund_total',
        'net_total',
        'reservations_count',
        'invoices_count',
        'lines',
        'generated_at',
        'emailed_at',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyBillingStatementStatus::class,
            'debit_total' => 'integer',
            'refund_total' => 'integer',
            'net_total' => 'integer',
            'reservations_count' => 'integer',
            'invoices_count' => 'integer',
            'lines' => 'array',
            'generated_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CompanyAccount, $this> */
    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
