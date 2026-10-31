<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KoraPointDocumentService
{
    public function __construct(private readonly KoraPointAccess $access, private readonly KoraPointRemittanceService $remittances, private readonly KoraPointRefundService $refunds) {}

    /** @param array<string, mixed> $data */
    public function store(User $actor, KoraPoint|KoraPointOrganization|KoraPointRemittance|KoraPointRefund $subject, array $data, UploadedFile $file): KoraPartnerDocument
    {
        return DB::transaction(function () use ($actor, $subject, $data, $file): KoraPartnerDocument {
            $subject = $subject->newQuery()->lockForUpdate()->whereKey($subject->getKey())->firstOrFail();
            $owner = [];
            if ($subject instanceof KoraPointRemittance) {
                $this->remittances->requireView($actor, $subject);
                $this->access->authorize($actor, 'kora-points.remit');
                abort_unless($subject->declared_by === $actor->getKey(), 403);
                if ($subject->status !== 'draft') {
                    throw ValidationException::withMessages(['file' => 'Le bordereau est déjà soumis.']);
                }
                $point = $subject->lines()->firstOrFail()->sale->point;
                $owner = ['remittance_id' => $subject->getKey(), 'point_id' => $subject->organization_id === null ? $point->getKey() : null, 'organization_id' => $subject->organization_id];
                $data['kind'] = 'remittance_proof';
            } elseif ($subject instanceof KoraPointRefund) {
                $this->refunds->requireExecutor($actor, $subject);
                if ($subject->status !== 'authorized') {
                    throw ValidationException::withMessages(['file' => 'Autorisation de remboursement requise.']);
                }
                $owner = ['refund_id' => $subject->getKey(), 'point_id' => $subject->sale->point_id];
                $data['kind'] = 'refund_proof';
            } else {
                $this->access->authorize($actor, 'kora-points.manage');
                $owner = $subject instanceof KoraPoint ? ['point_id' => $subject->getKey()] : ['organization_id' => $subject->getKey()];
                if (in_array($data['kind'], ['remittance_proof', 'refund_proof'], true)) {
                    throw ValidationException::withMessages(['kind' => 'Utilisez le dossier financier concerné pour cette preuve.']);
                }
            }
            $document = KoraPartnerDocument::query()->create($owner + Arr::only($data, ['kind', 'title', 'reference', 'signed_at', 'expires_at']) + ['uploaded_by' => $actor->getKey()]);
            $document->addMedia($file)->usingFileName($document->uuid.'.'.$file->extension())->toMediaCollection('proof');
            activity('kora-points')->performedOn($document)->causedBy($actor)->log('document.uploaded');

            return $document;
        });
    }

    public function authorizeDownload(User $actor, KoraPartnerDocument $document): void
    {
        if ($document->refund_id !== null) {
            $this->access->requireSale($actor, KoraPointRefund::query()->whereKey($document->refund_id)->firstOrFail()->sale);
        } elseif ($document->remittance_id !== null) {
            $this->remittances->requireView($actor, KoraPointRemittance::query()->whereKey($document->remittance_id)->firstOrFail());
        } elseif ($document->point_id !== null) {
            $this->access->requirePoint($actor, $document->point);
        } elseif ($document->organization_id !== null) {
            if ($actor->hasRole('kora_point_agent') && $this->access->points($actor)->where('organization_id', $document->organization_id)->exists()) {
                return;
            }
            $this->access->requireOrganization($actor, $document->organization);
        } else {
            abort(403);
        }
    }
}
