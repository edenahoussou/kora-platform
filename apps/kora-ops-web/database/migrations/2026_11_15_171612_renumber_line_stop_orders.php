<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repairs stop_order drift left by the +1000 shift in SyncLineStopsAction:
     * every line's stops are renumbered sequentially from 1, preserving order.
     */
    public function up(): void
    {
        DB::table('stops')
            ->select('line_id')
            ->distinct()
            ->pluck('line_id')
            ->each(function (int|string $lineId): void {
                $order = 1;

                DB::table('stops')
                    ->where('line_id', $lineId)
                    ->whereNull('deleted_at')
                    ->orderBy('stop_order')
                    ->pluck('id')
                    ->each(function (int|string $stopId) use (&$order): void {
                        DB::table('stops')->where('id', $stopId)->update(['stop_order' => $order++]);
                    });
            });
    }
};
