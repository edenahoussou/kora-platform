<?php

namespace App\Http\Controllers;

use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Passengers\Data\PassengerErasureStatus;
use App\Modules\Passengers\Models\PassengerErasureRequest;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Notifications\Passengers\PassengerDeletionRequestedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Pages publiques de conformité : politique de confidentialité et demande
 * de suppression de compte (exigences des stores applicatifs).
 */
class PublicLegalController extends Controller
{
    public function privacyPolicy(): Response
    {
        return Inertia::render('public/privacy-policy', $this->pageProps());
    }

    public function accountDeletion(): Response
    {
        return Inertia::render('public/account-deletion', $this->pageProps());
    }

    public function storeDeletionRequest(Request $request, PassengerIdentityResolver $identityResolver): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:6', 'max:20'],
            'name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'size:0'],
        ]);

        $phone = preg_replace('/[^0-9+]/', '', $validated['phone']);
        $normalizedPhone = $identityResolver->normalizePhone($phone) ?? $phone;

        PassengerErasureRequest::query()->updateOrCreate(
            ['phone_hash' => hash_hmac('sha256', $normalizedPhone, (string) config('app.key'))],
            [
                'uuid' => (string) Str::uuid(),
                'requested_by_user_id' => null,
                'actor_name' => $validated['name'] ?? null,
                'requester_phone' => $normalizedPhone,
                'requester_email' => $validated['email'] ?? null,
                'status' => PassengerErasureStatus::Requested,
                'scope' => array_filter([
                    'channel' => 'web_form',
                    'reason' => $validated['reason'] ?? null,
                ]),
                'executed_at' => null,
                'export_checksum' => null,
                'ip_address' => $request->ip(),
            ],
        );

        try {
            Notification::route('mail', (string) config('legal.support_email'))
                ->notify(new PassengerDeletionRequestedNotification(
                    $normalizedPhone,
                    $validated['name'] ?? null,
                    $validated['email'] ?? null,
                    $validated['reason'] ?? null,
                ));
        } catch (Throwable $exception) {
            report($exception);
        }

        Inertia::flash('deletion_submitted', true);

        return to_route('account-deletion');
    }

    /**
     * @return array{supportEmail: string, company: array{name: string, director: string, rccm: ?string, nim: ?string, address: string, phone: string}}
     */
    private function pageProps(): array
    {
        $settings = app(IntegrationsSettings::class);

        return [
            'supportEmail' => (string) config('legal.support_email'),
            'company' => [
                'name' => $settings->emecfShopName ?: 'KORA EXPRESS',
                'director' => (string) config('legal.company_director'),
                'rccm' => $settings->emecfRccm ?: 'RB/COT/26 B 43098',
                'nim' => $settings->emecfNim ?: 'TS01019552',
                'address' => trim(implode(', ', array_filter([
                    $settings->emecfAddress1,
                    $settings->emecfAddress3,
                ]))) ?: 'Gbegamey 2 C/960, Cotonou',
                'phone' => $settings->emecfContact1 ?: '+229 53 23 40 60',
            ],
        ];
    }
}
