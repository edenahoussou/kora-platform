<?php

namespace App\Modules\IdentityAccess\Services;

use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use Illuminate\Support\Str;

/**
 * Porte d'entrée du compte de revue store (MEZ-152).
 *
 * Whitelist fermée : seuls les identifiants configurés, et uniquement quand
 * la fonctionnalité est explicitement activée, peuvent utiliser le code fixe.
 * Le code lui-même n'est jamais stocké en base : il est comparé à la
 * configuration avec `hash_equals` (timing-safe).
 */
class StoreReviewGate
{
    public function enabled(): bool
    {
        return (bool) config('services.store_review.enabled', false)
            && $this->otpCode() !== null
            && $this->identifiers() !== [];
    }

    public function isReviewIdentifier(?string $identifier): bool
    {
        if (! $this->enabled() || $identifier === null || trim($identifier) === '') {
            return false;
        }

        return in_array(Str::lower(trim($identifier)), $this->identifiers(), true);
    }

    /**
     * Le challenge porte le téléphone du compte quand il existe : on teste
     * donc aussi l'email du compte pour ne pas rater le compte de revue.
     */
    public function isReviewChallenge(AuthOtpChallenge $challenge): bool
    {
        return $this->isReviewIdentifier($challenge->identifier)
            || $this->isReviewIdentifier($challenge->user?->email);
    }

    public function acceptsCode(AuthOtpChallenge $challenge, string $code): bool
    {
        $expected = $this->otpCode();

        return $expected !== null
            && $this->isReviewChallenge($challenge)
            && hash_equals($expected, $code);
    }

    private function otpCode(): ?string
    {
        $code = (string) config('services.store_review.otp_code', '');

        return preg_match('/^\d{6}$/', $code) === 1 ? $code : null;
    }

    /**
     * @return list<string>
     */
    private function identifiers(): array
    {
        $identifiers = config('services.store_review.identifiers', []);

        if (is_string($identifiers)) {
            $identifiers = explode(',', $identifiers);
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => Str::lower(trim((string) $value)),
            (array) $identifiers,
        ), static fn (string $value): bool => $value !== ''));
    }
}
