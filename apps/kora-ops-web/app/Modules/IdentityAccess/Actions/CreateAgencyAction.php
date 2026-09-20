<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\Cash\Actions\EnsureAgencyCashRegistersAction;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Support\Facades\DB;

class CreateAgencyAction
{
    public function __construct(
        private readonly GenerateAgencyCodeAction $generateAgencyCode,
        private readonly EnsureAgencyCashRegistersAction $ensureAgencyCashRegisters,
    ) {}

    /**
     * @param  array{name: string, city: string, country: string, head_user_id: int|null, status: string}  $attributes
     */
    public function execute(array $attributes): Agency
    {
        // The code generation (which locks the sequence row) and the agency
        // insert (which enforces the unique index on `code`) happen in the
        // same transaction, so the sequence lock is held until the new row
        // is actually committed. This is what makes the generated code safe
        // under concurrent creations, not just the increment by itself.
        return DB::transaction(function () use ($attributes): Agency {
            $attributes['code'] = $this->generateAgencyCode->execute();

            $agency = Agency::query()->create($attributes);
            $this->ensureAgencyCashRegisters->execute($agency);

            return $agency;
        });
    }
}
