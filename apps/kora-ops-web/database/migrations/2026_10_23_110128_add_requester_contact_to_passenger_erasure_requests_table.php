<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passenger_erasure_requests', function (Blueprint $table) {
            $table->string('requester_phone', 32)->nullable()->after('actor_name');
            $table->string('requester_email')->nullable()->after('requester_phone');
        });
    }

    public function down(): void
    {
        Schema::table('passenger_erasure_requests', function (Blueprint $table) {
            $table->dropColumn(['requester_phone', 'requester_email']);
        });
    }
};
