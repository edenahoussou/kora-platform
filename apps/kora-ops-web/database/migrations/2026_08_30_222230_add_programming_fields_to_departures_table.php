<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->string('departure_timezone', 64)->nullable();
            $table->json('stop_times')->nullable();
            $table->foreignId('secondary_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mechanic_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('secondary_driver_id');
            $table->dropConstrainedForeignId('mechanic_id');
            $table->dropColumn(['scheduled_at', 'departure_timezone', 'stop_times']);
        });
    }
};
