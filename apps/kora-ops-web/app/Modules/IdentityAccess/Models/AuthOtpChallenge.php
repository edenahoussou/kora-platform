<?php

namespace App\Modules\IdentityAccess\Models;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property int|null $user_id
 * @property OtpPurpose $purpose
 * @property string $code_hash
 * @property string|null $verification_token_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $consumed_at
 * @property OtpChallengeStatus $status
 */
class AuthOtpChallenge extends KoraModel
{
    protected $table = 'auth_otp_challenges';

    protected $fillable = [
        'uuid',
        'email',
        'user_id',
        'purpose',
        'code_hash',
        'verification_token_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'consumed_at',
        'status',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
            'status' => OtpChallengeStatus::class,
        ];
    }
}
