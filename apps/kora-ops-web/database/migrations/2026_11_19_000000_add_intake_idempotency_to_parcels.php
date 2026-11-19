<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->after('selling_agency_id');
            $table->string('intake_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->unique(['selling_agency_id', 'idempotency_key'], 'parcels_agency_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropUnique('parcels_agency_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'intake_fingerprint']);
        });
    }
};
