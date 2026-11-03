<?php

namespace App\Modules\Wallet\Services;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;
use App\Modules\Notifications\Notifications\PassengerSmsNotification;
use App\Modules\Wallet\Data\LoyaltyBonusKind;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Notifications Wallet du voyageur : inbox app + SMS/WhatsApp, livraison
 * indépendante de l'écriture économique.
 */
final class WalletNotificationPublisher
{
    /**
     * Notifie un débit Wallet effectué au guichet.
     *
     * @param  Wallet  $wallet  Wallet débité
     * @param  int  $amount  Montant débité
     * @param  Reservation  $reservation  Billet réglé
     * @param  User  $agent  Agent au guichet
     * @param  string  $disputeReference  Référence de contestation
     */
    public function counterDebit(Wallet $wallet, int $amount, Reservation $reservation, User $agent, string $disputeReference): void
    {
        $this->publish(
            $wallet,
            'wallet_counter_debit',
            'Débit Wallet au guichet',
            sprintf(
                'Un débit de %s FCFA a été effectué sur votre Wallet pour le billet %s par %s. Référence de contestation : %s.',
                $this->money($amount),
                $reservation->reference,
                $agent->name,
                $disputeReference,
            ),
            $reservation->uuid,
            ['amount' => $amount, 'reservation_uuid' => $reservation->uuid, 'dispute_reference' => $disputeReference],
        );
    }

    /**
     * Notifie une recharge confirmée et le nouveau solde.
     *
     * @param  Wallet  $wallet  Wallet rechargé
     * @param  int  $amount  Montant crédité
     */
    public function topUpConfirmed(Wallet $wallet, int $amount): void
    {
        $this->publish(
            $wallet,
            'wallet_topup_confirmed',
            'Recharge Wallet confirmée',
            sprintf('Votre Wallet a été rechargé de %s FCFA. Nouveau solde : %s FCFA.', $this->money($amount), $this->money((int) $wallet->cash_balance)),
            null,
            ['amount' => $amount],
        );
    }

    /**
     * Notifie des points gagnés sur une jambe.
     *
     * @param  Wallet  $wallet  Wallet crédité
     * @param  int  $points  Points gagnés
     * @param  string|null  $legLabel  Libellé du trajet concerné
     */
    public function pointsCredited(Wallet $wallet, int $points, ?string $legLabel = null): void
    {
        $this->publish(
            $wallet,
            'wallet_points_earned',
            'Points fidélité crédités',
            sprintf(
                '%s points ont été crédités%s. Solde : %s points.',
                $this->money($points),
                $legLabel !== null ? ' pour votre trajet '.$legLabel : '',
                $this->money((int) $wallet->points_balance),
            ),
            null,
            ['points' => $points],
        );
    }

    /**
     * Notifie un bonus fidélité accordé.
     *
     * @param  Wallet  $wallet  Wallet bénéficiaire
     * @param  LoyaltyBonusKind  $kind  Type de bonus
     * @param  int  $points  Points versés
     */
    public function bonusGranted(Wallet $wallet, LoyaltyBonusKind $kind, int $points): void
    {
        $this->publish(
            $wallet,
            'wallet_bonus_'.$kind->value,
            'Bonus fidélité',
            sprintf('Vous avez reçu %s points de bonus. Solde : %s points.', $this->money($points), $this->money((int) $wallet->points_balance)),
            null,
            ['points' => $points, 'kind' => $kind->value],
        );
    }

    /**
     * Rappelle l'expiration prochaine des points avec une suggestion.
     *
     * @param  Wallet  $wallet  Wallet concerné
     * @param  Carbon  $expiresAt  Date d'expiration
     * @param  int  $daysBefore  Jalon du rappel en jours
     * @param  string  $suggestion  Service suggéré au voyageur
     */
    public function pointsExpiring(Wallet $wallet, Carbon $expiresAt, int $daysBefore, string $suggestion): void
    {
        $this->publish(
            $wallet,
            'wallet_points_expiring',
            'Vos points arrivent à expiration',
            sprintf(
                'Vous avez %s points qui expireront dans %d jour(s), le %s. %s',
                $this->money((int) $wallet->points_balance),
                $daysBefore,
                $expiresAt->format('d/m/Y'),
                $suggestion,
            ),
            null,
            ['points' => (int) $wallet->points_balance, 'expires_at' => $expiresAt->toIso8601String()],
        );
    }

    /**
     * Notifie l'expiration effective des points.
     *
     * @param  Wallet  $wallet  Wallet concerné
     * @param  int  $points  Points expirés
     */
    public function pointsExpired(Wallet $wallet, int $points): void
    {
        $this->publish(
            $wallet,
            'wallet_points_expired',
            'Points expirés',
            sprintf('%s points ont expiré. Utilisez votre Wallet pour maintenir vos points actifs.', $this->money($points)),
            null,
            ['points' => $points],
        );
    }

    /**
     * Notifie le versement d'une restitution.
     *
     * @param  Wallet  $wallet  Wallet concerné
     * @param  int  $amount  Montant versé
     * @param  string  $payoutReference  Référence du versement
     */
    public function restitutionPaid(Wallet $wallet, int $amount, string $payoutReference): void
    {
        $this->publish(
            $wallet,
            'wallet_restitution_paid',
            'Restitution Wallet versée',
            sprintf('Une restitution de %s FCFA vous a été versée. Référence : %s.', $this->money($amount), $payoutReference),
            null,
            ['amount' => $amount, 'payout_reference' => $payoutReference],
        );
    }

    /**
     * Envoie la notification inbox puis le SMS/WhatsApp du voyageur.
     *
     * @param  Wallet  $wallet  Wallet concerné
     * @param  string  $type  Type de notification
     * @param  string  $title  Titre affiché
     * @param  string  $message  Message du voyageur
     * @param  string|null  $resourceUuid  Ressource liée dans l'app
     * @param  array<string, mixed>  $data  Données additionnelles
     */
    private function publish(Wallet $wallet, string $type, string $title, string $message, ?string $resourceUuid, array $data): void
    {
        $wallet->loadMissing('user');
        $user = $wallet->user;

        if (! $user instanceof User) {
            return;
        }

        Notification::sendNow($user, new PassengerInboxNotification(
            dedupeKey: 'wallet:'.$type.':'.$wallet->uuid.':'.(string) now()->getTimestamp(),
            type: $type,
            title: $title,
            message: $message,
            resourceUuid: $resourceUuid,
            data: ['resource' => 'wallet', 'wallet_uuid' => $wallet->uuid, ...$data],
        ));

        if (filled($user->phone)) {
            Notification::route('sms', (string) $user->phone)->notify(new PassengerSmsNotification(
                content: $message,
                dedupeKey: 'wallet:sms:'.$type.':'.$wallet->uuid.':'.(string) now()->getTimestamp(),
                type: $type,
                referenceType: $wallet->getMorphClass(),
                referenceId: (int) $wallet->getKey(),
            ));
        }
    }

    /**
     * Formate un montant en FCFA.
     *
     * @param  int  $amount  Montant à formater
     * @return string Montant lisible
     */
    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ');
    }
}
