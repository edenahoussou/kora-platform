<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelHub;
use App\Policies\Concerns\AuthorizesPermissions;
use Illuminate\Database\Eloquent\Builder;

class ParcelAccess
{
    use AuthorizesPermissions;

    public function require(User $actor, string $permission): void
    {
        abort_unless($this->allows($actor, $permission), 403);
    }

    public function atHub(User $actor, ParcelHub $hub, string $permission): void
    {
        $this->require($actor, $permission);
        abort_unless($actor->agency_id === $hub->agency_id || $this->allows($actor, 'parcels.network'), 403);
    }

    /** @return Builder<Parcel> */
    public function operations(User $actor): Builder
    {
        $this->require($actor, 'parcels.view');

        return Parcel::query()->when(! $this->allows($actor, 'parcels.network'), function (Builder $query) use ($actor): void {
            abort_if($actor->agency_id === null, 403);
            $query->whereHas('corridor', fn (Builder $corridor) => $corridor
                ->whereRelation('originHub', 'agency_id', $actor->agency_id)
                ->orWhereRelation('destinationHub', 'agency_id', $actor->agency_id));
        });
    }

    /** @return Builder<Parcel> */
    public function customer(User $actor): Builder
    {
        $this->require($actor, 'parcels.customer');

        return Parcel::query()->where(function (Builder $query) use ($actor): void {
            $query->where('sender_user_id', $actor->id);
            if ($actor->phone !== null && $this->hasVerifiedPhone($actor)) {
                $query->orWhere(fn (Builder $received) => $received->whereNotNull('reference')
                    ->where('recipient_phone_hash', self::phoneHash($actor->phone)));
            }
        });
    }

    public function hasVerifiedPhone(User $actor): bool
    {
        return $actor->phone !== null && AuthOtpChallenge::query()->where('user_id', $actor->id)
            ->where('identifier', $actor->phone)->whereNotNull('verified_at')->whereNotNull('consumed_at')->exists();
    }

    public static function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }
}
