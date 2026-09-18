<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fiscal_sync_attempts', function (Blueprint $table): void {
            $table->text('response_payload_encrypted')->nullable()->after('response_payload');
            $table->string('error_code', 128)->nullable()->after('response_code');
            $table->text('error_message')->nullable()->after('error_code');
            $table->timestamp('completed_at')->nullable()->after('attempted_at');
        });

        DB::table('fiscal_sync_attempts')
            ->whereNotNull('response_payload')
            ->orderBy('id')
            ->chunkById(250, function ($attempts): void {
                foreach ($attempts as $attempt) {
                    $payload = is_string($attempt->response_payload)
                        ? json_decode($attempt->response_payload, true)
                        : $attempt->response_payload;

                    if (! is_array($payload)) {
                        continue;
                    }

                    DB::table('fiscal_sync_attempts')->where('id', $attempt->id)->update([
                        'response_payload' => json_encode(Arr::only($payload, ['success', 'uid', 'invoice_id', 'code', 'message', 'error', 'status'])),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_sync_attempts', function (Blueprint $table): void {
            $table->dropColumn(['response_payload_encrypted', 'error_code', 'error_message', 'completed_at']);
        });
    }
};
