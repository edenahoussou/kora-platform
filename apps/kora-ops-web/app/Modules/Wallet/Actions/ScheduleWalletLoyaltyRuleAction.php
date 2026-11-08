<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScheduleWalletLoyaltyRuleAction
{
    public function execute(WalletLoyaltyRuleVersion $version, User $actor, Carbon $effectiveAt): WalletLoyaltyRuleVersion
    {
        if ($version->status !== WalletLoyaltyRuleVersionStatus::Draft) {
            throw ValidationException::withMessages(['version' => 'Seul un brouillon peut être programmé.']);
        }

        if (! $effectiveAt->isFuture()) {
            throw ValidationException::withMessages(['effective_at' => 'La date d’effet doit être postérieure à maintenant.']);
        }

        return DB::transaction(function () use ($version, $actor, $effectiveAt): WalletLoyaltyRuleVersion {
            $active = WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($active === null) {
                throw ValidationException::withMessages([
                    'version' => 'La version active de fidélité est introuvable.',
                ]);
            }

            $scheduled = WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Scheduled->value)
                ->lockForUpdate()
                ->exists();

            if ($scheduled) {
                throw ValidationException::withMessages([
                    'effective_at' => 'Une autre version de fidélité est déjà programmée.',
                ]);
            }

            $locked = WalletLoyaltyRuleVersion::query()->whereKey($version->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== WalletLoyaltyRuleVersionStatus::Draft) {
                throw ValidationException::withMessages(['version' => 'Ce brouillon a déjà été publié.']);
            }

            $locked->update([
                'status' => WalletLoyaltyRuleVersionStatus::Scheduled,
                'scheduled_by_user_id' => $actor->getKey(),
                'effective_at' => $effectiveAt,
                'published_at' => now(),
            ]);

            activity('wallet')
                ->performedOn($locked)
                ->causedBy($actor)
                ->event('wallet.loyalty-rules.scheduled')
                ->withProperties([
                    'version_uuid' => $locked->uuid,
                    'version' => $locked->version,
                    'effective_at' => $effectiveAt->toIso8601String(),
                ])
                ->log('wallet.loyalty-rules.scheduled');

            return $locked->refresh();
        });
    }
}
