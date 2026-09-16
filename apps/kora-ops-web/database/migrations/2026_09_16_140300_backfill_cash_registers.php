<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agencyIds = DB::table('agencies')->whereNull('deleted_at')->pluck('id');

        foreach ($agencyIds as $agencyId) {
            DB::table('cash_registers')->insertOrIgnore([
                'uuid' => (string) str()->uuid(),
                'agency_id' => $agencyId,
                'code' => 'COFFRE',
                'label' => 'Coffre agence',
                'kind' => 'vault',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $legacySessions = DB::table('cash_sessions')->whereNull('cash_register_id')->get(['id', 'agency_id']);
        foreach ($legacySessions as $session) {
            $registerId = DB::table('cash_registers')->insertGetId([
                'uuid' => (string) str()->uuid(),
                'agency_id' => $session->agency_id,
                'code' => 'LEGACY-'.$session->id,
                'label' => 'Caisse historique #'.$session->id,
                'kind' => 'counter',
                'status' => 'inactive',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('cash_sessions')->where('id', $session->id)->update(['cash_register_id' => $registerId]);
        }

        DB::table('cash_movements')
            ->whereNull('cash_register_id')
            ->whereNotNull('cash_session_id')
            ->update([
                'cash_register_id' => DB::raw('(SELECT cash_register_id FROM cash_sessions WHERE cash_sessions.id = cash_movements.cash_session_id)'),
            ]);
    }

    public function down(): void
    {
        // Backfilled historical registers are deliberately retained. The
        // preceding schema migration remains reversible without losing the
        // audit mapping that protects those sessions.
    }
};
