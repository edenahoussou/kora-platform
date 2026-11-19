<?php

namespace App\Modules\Parcels\Services;

use App\Modules\Parcels\Models\Parcel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ParcelReference
{
    /**
     * Assign a public logistics reference once. Existing references are immutable.
     */
    public function assign(Parcel $parcel): string
    {
        if (is_string($parcel->reference) && $parcel->reference !== '') {
            return $parcel->reference;
        }

        $date = now()->format('Ymd');

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $reference = sprintf('KC-%s-%05d', $date, random_int(0, 99999));

            try {
                DB::transaction(fn () => $parcel->update(['reference' => $reference]));

                return $reference;
            } catch (UniqueConstraintViolationException) {
                $parcel->refresh();
                if (is_string($parcel->reference) && $parcel->reference !== '') {
                    return $parcel->reference;
                }
            }
        }

        throw new RuntimeException('Unable to allocate a unique parcel reference.');
    }
}
