<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The signed QR payload (base64url(json).base64url(hmac)) can exceed
     * 255 characters when the ticket has multiple legs or long passenger
     * names. Switch to TEXT so the payload is never truncated.
     *
     * SQLite does not enforce VARCHAR length, so this is a no-op there.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tickets ALTER COLUMN qr_code TYPE TEXT');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tickets ALTER COLUMN qr_code TYPE VARCHAR(255)');
        }
    }
};
