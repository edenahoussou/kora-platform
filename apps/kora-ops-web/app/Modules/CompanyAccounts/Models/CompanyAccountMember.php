<?php

namespace App\Modules\CompanyAccounts\Models;

use App\Models\User;
use App\Modules\CompanyAccounts\Data\CompanyMemberRole;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CompanyMemberRole $role
 * @property CompanyMemberStatus $status
 */
class CompanyAccountMember extends KoraModel
{
    protected $fillable = [
        'uuid',
        'company_account_id',
        'user_id',
        'role',
        'status',
    ];

    protected function casts(): array
    {
        return ['role' => CompanyMemberRole::class, 'status' => CompanyMemberStatus::class];
    }

    /** @return BelongsTo<CompanyAccount, $this> */
    public function companyAccount(): BelongsTo
    {
        return $this->belongsTo(CompanyAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
