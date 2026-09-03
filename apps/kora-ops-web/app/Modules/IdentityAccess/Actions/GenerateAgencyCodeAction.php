<?php

namespace App\Modules\IdentityAccess\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Generates a stable, unique agency code such as "AG-000042".
 *
 * The sequence is stored in a dedicated singleton row (agency_code_sequences,
 * id = 1) rather than derived from Agency::count()/max(id), so that:
 * - deleting (even soft-deleting) an agency never frees up / reissues a code,
 * - the code does not depend on the agencies table at all, avoiding any
 *   locking contention with reads on that table.
 *
 * Concurrency safety: the row is locked with SELECT ... FOR UPDATE inside a
 * transaction, so two requests generating a code at the same time will be
 * serialized by the database rather than racing on the same value. Callers
 * that also insert the Agency row should wrap the whole operation (generate
 * + create) in the same transaction so the lock is held until the new
 * agency row (and its unique code) is actually committed.
 */
class GenerateAgencyCodeAction
{
    private const string PREFIX = 'AG-';

    private const int PAD_LENGTH = 6;

    public function execute(): string
    {
        return DB::transaction(function (): string {
            $sequence = DB::table('agency_code_sequences')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            // Defensive fallback in case the singleton row is ever missing
            // (e.g. a database restored without running the seed insert).
            if ($sequence === null) {
                DB::table('agency_code_sequences')->insert(['id' => 1, 'next_value' => 1]);
                $next = 1;
            } else {
                $next = (int) $sequence->next_value;
            }

            DB::table('agency_code_sequences')
                ->where('id', 1)
                ->update(['next_value' => $next + 1]);

            return self::PREFIX.str_pad((string) $next, self::PAD_LENGTH, '0', STR_PAD_LEFT);
        });
    }
}
