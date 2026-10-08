<?php

namespace App\Modules\CompanyAccounts\Models;

use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property CompanyTravelerStatus $status
 * @property PassengerDocumentType|null $document_type
 * @property Carbon|null $birth_date
 * @property bool|null $yellow_fever_vaccinated
 */
class CompanyTraveler extends KoraModel
{
    protected $fillable = [
        'uuid',
        'company_account_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'document_type',
        'document_number',
        'nationality',
        'birth_date',
        'yellow_fever_vaccinated',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => PassengerDocumentType::class,
            'status' => CompanyTravelerStatus::class,
            'birth_date' => 'date',
            'yellow_fever_vaccinated' => 'boolean',
        ];
    }

    /** @return BelongsTo<CompanyAccount, $this> */
    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class);
    }
}
