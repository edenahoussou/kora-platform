<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use function base64_decode;

class TicketsPublicKeyCommand extends Command
{
    protected $signature = 'tickets:public-key';

    protected $description = 'Export the Ed25519 public key for the KORA P501 boarding app (safe to distribute)';

    public function handle(): int
    {
        $publicKey = config('tickets.signing_public_key');

        if ($publicKey === null || $publicKey === '') {
            $this->error('KORA_TICKET_SIGNING_PUBLIC_KEY is not configured.');
            $this->info('Generate a keypair with:');
            $this->info('  php -r \'$kp=sodium_crypto_sign_keypair(); echo "PRIVATE=".base64_encode(sodium_crypto_sign_secretkey($kp))."\n"; echo "PUBLIC=".base64_encode(sodium_crypto_sign_publickey($kp))."\n";\'');

            return self::FAILURE;
        }

        $decoded = base64_decode((string) $publicKey, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $this->error('KORA_TICKET_SIGNING_PUBLIC_KEY is invalid.');

            return self::FAILURE;
        }

        $this->info('Ed25519 public key for KORA P501 boarding app:');
        $this->newLine();
        $this->line($publicKey);
        $this->newLine();
        $this->info('Base64url (for JSON config):');
        $this->line(rtrim(strtr($publicKey, '+/', '-_'), '='));
        $this->newLine();
        $this->info('Hex (for embedded configs):');
        $this->line(bin2hex($decoded));

        return self::SUCCESS;
    }
}
