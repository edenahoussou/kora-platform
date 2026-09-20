<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assisted_bookings', function (Blueprint $table): void {
            $table->foreignId('preferred_agency_id')->nullable()->change();
            $table->index(
                ['status', 'preferred_agency_id', 'created_at'],
                'assisted_queue_status_agency_created_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assisted_bookings', function (Blueprint $table): void {
            $table->dropIndex('assisted_queue_status_agency_created_idx');
        });
    }
};
