<?php

namespace App\Modules\Fiscalization\Services;

use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DgiFiscalTestInvoiceGateway implements FiscalTestInvoiceGateway
{
    public function __construct(private readonly IntegrationsSettings $settings) {}

    public function submit(array $payload): array
    {
        return $this->send('post', 'invoice', $payload);
    }

    public function finalize(string $uid): array
    {
        return $this->send('put', 'invoice/'.$uid.'/confirm');
    }

    /** @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, ?array $payload = null): array
    {
        if (! $this->settings->emecfTestMode) {
            throw new \RuntimeException('Les factures de campagne sont autorisées uniquement en mode e‑MECeF TEST.');
        }

        $token = (string) ($this->settings->emecfToken ?? '');
        $ifu = $this->settings->emecfDefaultIfu;
        $url = $this->settings->emecfTestInvoiceUrl;
        $host = parse_url($url, PHP_URL_HOST);
        if ($token === '' || $ifu === '' || $ifu === '0000000000000') {
            throw new \RuntimeException('Le token de test et l’IFU de l’émetteur doivent être configurés dans les paramètres e‑MECeF.');
        }
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $host !== 'developper.impots.bj') {
            throw new \RuntimeException('L’URL e‑MECeF de test doit utiliser le domaine de test officiel de la DGI.');
        }

        /** @var PendingRequest $request */
        $request = Http::baseUrl(rtrim($url, '/').'/')
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout(min($this->settings->emecfHttpTimeout, 30))
            ->connectTimeout(min($this->settings->emecfConnectTimeout, 10));

        $response = $method === 'post'
            ? $request->post($path, $payload ?? [])
            : $request->put($path);
        $data = $response->json();

        if (! $response->successful()) {
            $message = is_array($data)
                ? (string) ($data['message'] ?? $data['error'] ?? 'Erreur e‑MECeF')
                : 'Erreur HTTP e‑MECeF ('.$response->status().').';

            throw new \RuntimeException($message);
        }

        if (! is_array($data)) {
            throw new \RuntimeException('La réponse e‑MECeF n’est pas un document JSON valide.');
        }

        return $data;
    }
}
