<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Modules\Booking\Models\Reservation;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\IdentityAccess\Models\UserPreference;
use App\Modules\IdentityAccess\Models\UserSocialAccount;
use App\Modules\IdentityAccess\Observers\UserOpsSessionObserver;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\IdentityAccess\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $country_code
 * @property string|null $birth_date
 * @property UserStatus $status
 * @property string|null $pin_hash
 * @property int $failed_pin_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['uuid', 'name', 'email', 'phone', 'country_code', 'birth_date', 'status', 'pin_hash', 'password'])]
#[Hidden(['id', 'password', 'pin_hash', 'failed_pin_attempts', 'locked_until', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy([UserOpsSessionObserver::class])]
class User extends Authenticatable implements HasMedia, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicUuid, HasRoles, InteractsWithMedia, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * @return HasOne<UserPreference, $this>
     */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return HasMany<UserSocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'created_by_user_id');
    }

    /**
     * Media collections pour l'avatar utilisateur (Spatie Media Library).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    /**
     * Conversions d'image pour l'avatar.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 200, 200)
            ->nonQueued(); // @phpstan-ignore-line — nonQueued() est défini sur Conversion, pas sur le retour de fit() selon PHPStan
    }

    /**
     * Vrai si l'utilisateur est rattaché à une agence (rôle terrain).
     * Un utilisateur de la direction n'a pas d'agence.
     */
    public function belongsToAgency(): bool
    {
        return $this->agency_id !== null;
    }

    /**
     * Vrai si l'utilisateur est un personnel de direction (non rattaché à une agence).
     */
    public function isManagementStaff(): bool
    {
        return $this->agency_id === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'locked_until' => 'datetime',
            'failed_pin_attempts' => 'integer',
            'password' => 'hashed',
            'pin_hash' => 'hashed',
            'status' => UserStatus::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Send the password reset notification (KORA branded, French).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
