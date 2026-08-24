<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->unsignedSmallInteger('delay_minutes')->default(0)->after('departure_time');
            $table->string('delay_reason', 160)->nullable()->after('delay_minutes');
            $table->timestamp('delay_recorded_at')->nullable()->after('delay_reason');
            $table->foreignId('delay_recorded_by')
                ->nullable()
                ->after('delay_recorded_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['status', 'departure_date', 'departure_time']);
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table): void {
            $table->dropForeign(['delay_recorded_by']);
            $table->dropIndex(['status', 'departure_date', 'departure_time']);
            $table->dropColumn([
                'delay_minutes',
                'delay_reason',
                'delay_recorded_at',
                'delay_recorded_by',
            ]);
        });
    }
};
