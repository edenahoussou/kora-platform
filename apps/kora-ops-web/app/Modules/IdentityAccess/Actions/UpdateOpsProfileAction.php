<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Models\UserPreference;
use Illuminate\Support\Facades\DB;

use function activity;

class UpdateOpsProfileAction
{
    public function __construct(
        private readonly PassengerIdentityResolver $identityResolver,
    ) {}

    /**
     * Met à jour l'identité OPS de l'utilisateur (jamais l'email, verrouillé DSI)
     * et journalise les changements significatifs.
     *
     * @param  array{name: string, phone?: string|null, country_code?: string|null, job_title?: string|null, department?: string|null, location?: string|null, timezone?: string|null}  $attributes
     * @return array{user: User, phone_changed: bool}
     */
    public function execute(User $user, array $attributes): array
    {
        $before = [
            'name' => $user->name,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'location' => $user->location,
        ];

        $updated = DB::transaction(function () use ($user, $attributes): User {
            $fill = ['name' => $attributes['name']];

            foreach (['job_title', 'department', 'location'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $fill[$field] = $attributes[$field];
                }
            }

            if (array_key_exists('phone', $attributes)) {
                $fill['phone'] = $this->identityResolver->normalizePhone($attributes['phone'], $attributes['country_code'] ?? null);
                $fill['country_code'] = $attributes['country_code'] ?? null;
            }

            $user->forceFill($fill)->save();

            if (array_key_exists('timezone', $attributes) && $attributes['timezone'] !== null) {
                UserPreference::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['locale' => 'fr', 'theme' => 'light'],
                )->update(['timezone' => $attributes['timezone']]);
            }

            return $user->refresh();
        });

        $phoneChanged = $before['phone'] !== $updated->phone;

        activity('identity-access')
            ->performedOn($updated)
            ->causedBy($updated)
            ->withProperties([
                'before' => $before,
                'after' => [
                    'name' => $updated->name,
                    'phone' => $updated->phone,
                    'job_title' => $updated->job_title,
                    'department' => $updated->department,
                    'location' => $updated->location,
                ],
            ])
            ->log('profile_updated');

        return ['user' => $updated, 'phone_changed' => $phoneChanged];
    }
}
