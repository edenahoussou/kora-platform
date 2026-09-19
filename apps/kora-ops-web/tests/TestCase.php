<?php

namespace Tests;

use App\Models\User;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Laravel\Sanctum\PersonalAccessToken;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ouvre automatiquement une session OPS avant chaque requête OPS Mobile
     * qui présente un jeton ET un terminal actif.
     *
     * Les routes OPS exigent depuis MEZ-95 une session ouverte (handshake) sur
     * le terminal présenté. Les tests métier (scan, libération de siège,
     * manifeste, synchronisation…) ne testent pas l'authentification : ce
     * raccourci leur évite de rejouer un handshake avant chaque appel. Les
     * tests qui portent sur la session elle-même le désactivent
     * (`$this->autoOpenOpsSession = false`).
     */
    protected bool $autoOpenOpsSession = true;

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $server
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->autoOpenOpsSession) {
            $this->openOpsSessionForRequest((string) $uri, $parameters, $server, $content);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $server
     */
    private function openOpsSessionForRequest(string $uri, array $parameters, array $server, ?string $content): void
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);

        if (! preg_match('#/api/v1/(ops/|ops-mobile/terminal/(sync|activity))#', $path)) {
            return;
        }

        $authorization = (string) ($server['HTTP_AUTHORIZATION'] ?? '');

        if (! preg_match('/^Bearer (\d+)\|/', $authorization, $matches)) {
            return;
        }

        $token = PersonalAccessToken::query()->find((int) $matches[1]);
        $user = $token?->tokenable;

        if (! $token instanceof PersonalAccessToken || ! $user instanceof User) {
            return;
        }

        $serial = (string) ($server['HTTP_X_TERMINAL_SERIAL'] ?? $parameters['serial_number'] ?? '');

        if ($serial === '' && $content !== null && $content !== '') {
            $decoded = json_decode($content, true);
            $serial = is_array($decoded) ? (string) ($decoded['serial_number'] ?? '') : '';
        }

        $terminal = TerminalP501::query()
            ->whereRaw('LOWER(serial_number) = ?', [mb_strtolower(trim($serial))])
            ->first();

        if ($terminal === null || ! $terminal->isUsable()) {
            return;
        }

        $alreadyOpen = TerminalSession::query()
            ->active()
            ->where('personal_access_token_id', $token->getKey())
            ->where('terminal_p501_id', $terminal->getKey())
            ->where('expires_at', '>', now())
            ->exists();

        if ($alreadyOpen && $terminal->assigned_user_id === $user->getKey()) {
            return;
        }

        TerminalSession::query()
            ->active()
            ->where(fn ($query) => $query
                ->where('personal_access_token_id', $token->getKey())
                ->orWhere('terminal_p501_id', $terminal->getKey()))
            ->update(['revoked_at' => now(), 'revoked_reason' => 'replaced']);

        $terminal->forceFill(['assigned_user_id' => $user->getKey()])->save();

        TerminalSession::query()->create([
            'terminal_p501_id' => $terminal->getKey(),
            'user_id' => $user->getKey(),
            'personal_access_token_id' => $token->getKey(),
            'started_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addHours(12),
            'absolute_expires_at' => now()->addHours(24),
            'renewal_count' => 0,
        ]);
    }
}
