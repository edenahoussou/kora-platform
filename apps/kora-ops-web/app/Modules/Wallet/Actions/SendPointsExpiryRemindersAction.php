<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletPointsExpiryReminder;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
use Illuminate\Support\Carbon;

/**
 * Envoie les rappels J-x avant expiration des points, une seule fois par jalon.
 */
final class SendPointsExpiryRemindersAction
{
    public function __construct(
        private readonly WalletSettings $settings,
        private readonly ExpireWalletPointsAction $expireWalletPoints,
        private readonly WalletNotificationPublisher $notifications,
    ) {}

    public function handle(): int
    {
        $sent = 0;
        $today = Carbon::now('Africa/Porto-Novo')->startOfDay();

        Wallet::query()
            ->where('points_balance', '>', 0)
            ->chunkById(200, function ($wallets) use (&$sent, $today): void {
                foreach ($wallets as $wallet) {
                    $expiresAt = $this->expireWalletPoints->expiresAt($wallet);

                    if ($expiresAt->isPast()) {
                        continue;
                    }

                    $expiryDay = $expiresAt->copy()->setTimezone('Africa/Porto-Novo')->startOfDay();

                    foreach ($this->settings->pointsExpiryReminderDays as $daysBefore) {
                        $milestone = $expiryDay->copy()->subDays($daysBefore);

                        if (! $milestone->equalTo($today)) {
                            continue;
                        }

                        $alreadySent = WalletPointsExpiryReminder::query()
                            ->where('wallet_id', $wallet->getKey())
                            ->whereDate('expires_on', $expiryDay->toDateString())
                            ->where('days_before', $daysBefore)
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        WalletPointsExpiryReminder::query()->create([
                            'wallet_id' => $wallet->getKey(),
                            'days_before' => $daysBefore,
                            'expires_on' => $expiryDay->toDateString(),
                            'sent_at' => now(),
                        ]);

                        $this->notifications->pointsExpiring($wallet, $expiresAt, $daysBefore, $this->suggestion());
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function suggestion(): string
    {
        return 'Utilisez vos points sur votre prochain billet KORA BUS pour les conserver.';
    }
}
