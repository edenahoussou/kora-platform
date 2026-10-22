<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Contracts\IntegrationConnectionProbe;
use App\Modules\Settings\Data\ConnectionProbeResult;
use App\Modules\Settings\Services\Probes\ConexteoConnectionProbe;
use App\Modules\Settings\Services\Probes\EmecfConnectionProbe;
use App\Modules\Settings\Services\Probes\FedaPayConnectionProbe;
use App\Modules\Settings\Services\Probes\KKiaPayConnectionProbe;
use App\Modules\Settings\Services\Probes\MtnMomoConnectionProbe;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class IntegrationConnectionProbeManager
{
    /** @var array<string, IntegrationConnectionProbe> */
    private array $probes;

    public function __construct(
        EmecfConnectionProbe $emecf,
        FedaPayConnectionProbe $fedapay,
        MtnMomoConnectionProbe $mtnMomo,
        KKiaPayConnectionProbe $kkiapay,
        ConexteoConnectionProbe $conexteo,
    ) {
        $this->probes = [
            $emecf->provider() => $emecf,
            $fedapay->provider() => $fedapay,
            $mtnMomo->provider() => $mtnMomo,
            $kkiapay->provider() => $kkiapay,
            $conexteo->provider() => $conexteo,
        ];
    }

    public function test(string $provider): ConnectionProbeResult
    {
        $provider = (string) Str::of($provider)->lower()->replace('-', '_');

        if (! isset($this->probes[$provider])) {
            throw ValidationException::withMessages([
                'provider' => ['Ce fournisseur ne prend pas en charge de test de connexion.'],
            ]);
        }

        return $this->probes[$provider]->test();
    }

    /** @return list<string> */
    public function supportedProviders(): array
    {
        return array_keys($this->probes);
    }
}
