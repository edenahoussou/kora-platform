<?php

namespace App\Modules\KoraPoints\Actions;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Services\KoraPointDocumentService;
use App\Modules\KoraPoints\Services\KoraPointRemittanceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DeclareKoraPointRemittanceAction
{
    public function __construct(
        private readonly KoraPointRemittanceService $remittances,
        private readonly KoraPointDocumentService $documents,
    ) {}

    /**
     * @param  list<string>  $saleUuids
     */
    public function execute(
        User $actor,
        array $saleUuids,
        string $method,
        string $reference,
        UploadedFile $proof,
        ?string $bankName = null,
        ?string $notes = null,
    ): KoraPointRemittance {
        $note = $notes;
        if ($bankName !== null && $bankName !== '') {
            $note = trim(($note ? $note.' · ' : '').'Banque : '.$bankName);
        }

        return DB::transaction(function () use ($actor, $saleUuids, $method, $reference, $proof, $note): KoraPointRemittance {
            $remittance = $this->remittances->create($actor, $saleUuids, $method, $reference);
            if ($note !== null) {
                $remittance->update(['note' => $note]);
            }

            $this->documents->store(
                $actor,
                $remittance,
                [
                    'title' => 'Justificatif de versement '.$reference,
                    'reference' => $reference,
                ],
                $proof,
            );
            $this->remittances->submit($actor, $remittance);

            return $remittance;
        });
    }
}
