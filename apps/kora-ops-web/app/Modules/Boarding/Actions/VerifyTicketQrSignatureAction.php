<?php

namespace App\Modules\Boarding\Actions;

/**
 * Vérifie la signature Ed25519 d'un token QR de billet
 * (`base64url(payload).base64url(signature)`, voir BuildTicketQrPayloadAction)
 * avec la clé publique de signature, et décode le payload JSON.
 */
class VerifyTicketQrSignatureAction
{
    /**
     * @return array<string, mixed>|null Le payload décodé si la signature est valide, sinon null.
     */
    public function execute(string $token): ?array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;

        $payloadJson = $this->base64UrlDecode($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($payloadJson === null || $signature === null) {
            return null;
        }

        $publicKey = $this->signingPublicKey();

        // ✅ CORRECTION : Vérification stricte pour garantir à PHPStan que $publicKey est une "non-empty-string"
        if (! is_string($publicKey) || $publicKey === '' || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($signature, $payloadJson, $publicKey)) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($payloadJson, true);

        return is_array($payload) ? $payload : null;
    }

    private function signingPublicKey(): ?string
    {
        $key = config('tickets.signing_public_key');

        if ($key === null || $key === '') {
            return null;
        }

        $decoded = base64_decode((string) $key, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        return $decoded;
    }

    private function base64UrlDecode(string $data): ?string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
