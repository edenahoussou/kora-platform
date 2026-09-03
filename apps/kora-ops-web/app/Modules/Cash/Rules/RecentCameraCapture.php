<?php

namespace App\Modules\Cash\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Validates that a client-supplied capture timestamp is recent.
 *
 * Nothing in the bytes of an uploaded image can prove, on its own, that the
 * photo was taken live rather than picked from an existing file. The app's
 * enforcement of "evidence must come from a live photo" therefore has two
 * layers:
 *
 * 1. The UI never exposes a file picker for cash evidence — only a
 *    getUserMedia-based live camera flow (see CameraCapture.tsx), which
 *    records the exact moment the snapshot was taken.
 * 2. This rule rejects that timestamp if it is not recent, so a captured
 *    photo cannot be cached and replayed long after the fact (e.g. via a
 *    direct API call bypassing the UI, or a stale browser tab).
 *
 * This is a reasonable technical proxy, not a cryptographic guarantee.
 */
class RecentCameraCapture implements ValidationRule
{
    public function __construct(
        private readonly int $toleranceMinutes = 15,
        private readonly int $clockSkewMinutes = 2,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('L’horodatage de la prise de vue est manquant.');

            return;
        }

        try {
            $capturedAt = CarbonImmutable::parse($value);
        } catch (Throwable) {
            $fail('L’horodatage de la prise de vue est invalide.');

            return;
        }

        $now = CarbonImmutable::now();

        if ($capturedAt->isAfter($now->addMinutes($this->clockSkewMinutes))) {
            $fail('L’horodatage de la prise de vue est postérieur à l’envoi de la requête.');

            return;
        }

        if ($capturedAt->isBefore($now->subMinutes($this->toleranceMinutes))) {
            $fail('La photo doit être prise en direct juste avant l’envoi : celle-ci est trop ancienne, veuillez reprendre la photo.');
        }
    }
}
