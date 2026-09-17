<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        DB::table('agency_payment_accounts')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->orderBy('agency_id')
            ->orderBy('network')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['id', 'agency_id', 'network'])
            ->each(function (object $account) use (&$seen): void {
                $key = $account->agency_id.'|'.$account->network;

                if (isset($seen[$key])) {
                    DB::table('agency_payment_accounts')
                        ->where('id', $account->id)
                        ->update(['status' => 'inactive', 'updated_at' => now()]);

                    return;
                }

                $seen[$key] = true;
            });
    }

    public function down(): void
    {
        // The previous active/inactive state cannot be inferred safely.
    }
};
