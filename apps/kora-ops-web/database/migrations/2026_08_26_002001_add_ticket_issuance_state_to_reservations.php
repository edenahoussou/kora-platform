<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('ticket_issuance_status', 32)->default('not_started')->after('status')->index();
            $table->text('ticket_issuance_error')->nullable()->after('ticket_issuance_status');
            $table->timestamp('ticket_issuance_started_at')->nullable()->after('ticket_issuance_error');
            $table->timestamp('ticket_issued_at')->nullable()->after('ticket_issuance_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['ticket_issuance_status']);
            $table->dropColumn([
                'ticket_issuance_status',
                'ticket_issuance_error',
                'ticket_issuance_started_at',
                'ticket_issued_at',
            ]);
        });
    }
};
