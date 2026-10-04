<?php

namespace App\Modules\IdentityAccess\Actions;

class ParseSessionUserAgentAction
{
    /**
     * Extrait navigateur, OS et type d'appareil d'un User-Agent stocké
     * en table `sessions` (parsing léger, sans dépendance externe).
     *
     * @return array{device: string, browser: string, type: 'desktop'|'mobile'}
     */
    public function execute(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        return [
            'device' => $this->device($ua),
            'browser' => $this->browser($ua),
            'type' => $this->type($ua),
        ];
    }

    private function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA/') => 'Microsoft Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'CriOS/') => 'Chrome (iOS)',
            str_contains($ua, 'Chrome/') => 'Google Chrome',
            str_contains($ua, 'FxiOS/') => 'Firefox (iOS)',
            str_contains($ua, 'Firefox/') => 'Mozilla Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Navigateur inconnu',
        };
    }

    private function device(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Appareil Android',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Windows') => 'PC Windows',
            str_contains($ua, 'Linux') => 'Poste Linux',
            default => 'Appareil inconnu',
        };
    }

    /**
     * @return 'desktop'|'mobile'
     */
    private function type(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'mobile',
            str_contains($ua, 'Android') && str_contains($ua, 'Mobile') => 'mobile',
            default => 'desktop',
        };
    }
}
