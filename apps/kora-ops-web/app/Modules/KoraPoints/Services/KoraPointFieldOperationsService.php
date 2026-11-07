<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointPlvOrder;
use App\Modules\KoraPoints\Models\KoraPointVisit;
use App\Modules\KoraPoints\Models\KoraPointVisitAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class KoraPointFieldOperationsService
{
    public function __construct(private readonly KoraPointAccess $access) {}

    /** @param array<string, mixed> $attributes */
    public function createVisit(User $actor, KoraPoint $point, array $attributes): KoraPointVisit
    {
        $this->access->authorizeInternal($actor, 'kora-points.manage');
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($actor, $point, $attributes, &$storedPaths): KoraPointVisit {
                $actions = $attributes['actions'] ?? [];
                $visit = KoraPointVisit::query()->create([
                    'point_id' => $point->getKey(),
                    'created_by' => $actor->getKey(),
                    'visit_type' => $attributes['visit_type'],
                    'score' => $attributes['score'],
                    'report' => $attributes['report'],
                    'status' => $actions === [] ? 'closed' : 'in_progress',
                ]);

                foreach ($actions as $index => $action) {
                    $assignee = User::query()->where('uuid', $action['assignee_uuid'])->firstOrFail();
                    $this->assertAssignable($assignee, $point, $index);
                    $visit->actions()->create([
                        'assigned_user_id' => $assignee->getKey(),
                        'label' => $action['label'],
                        'due_date' => $action['due_date'],
                    ]);
                }

                foreach ($attributes['photos'] ?? [] as $photo) {
                    if (! $photo instanceof UploadedFile) {
                        continue;
                    }

                    $path = $photo->store('kora-point-visits/'.$visit->uuid, 'private');
                    if ($path === false) {
                        throw new \RuntimeException('Impossible de stocker une photo de visite.');
                    }
                    $storedPaths[] = $path;
                    $visit->photos()->create([
                        'disk' => 'private',
                        'path' => $path,
                        'original_name' => $photo->getClientOriginalName(),
                        'mime_type' => $photo->getMimeType() ?: 'application/octet-stream',
                        'size' => $photo->getSize() ?: 0,
                    ]);
                }

                return $visit->load(['actions.assignee', 'photos']);
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($storedPaths);
            throw $exception;
        }
    }

    public function realizeAction(User $actor, KoraPointVisitAction $action, string $note): void
    {
        $point = $action->visit->point;
        $isAssignedPointAgent = $actor->hasRole('kora_point_agent')
            && $actor->status === UserStatus::Active
            && $actor->can('kora-points.visits.actions.update')
            && (int) $actor->agency_id === (int) $point->agency_id;
        $isInternalAssignee = ! $this->access->isPartner($actor)
            && $actor->status === UserStatus::Active
            && $actor->can('kora-points.manage');

        abort_unless(($isAssignedPointAgent || $isInternalAssignee) && $action->assigned_user_id === $actor->getKey(), 403);
        if (! in_array($action->status, ['pending', 'returned'], true)) {
            throw ValidationException::withMessages(['action' => 'Cette action ne peut pas encore être signalée comme réalisée.']);
        }

        $action->update(['status' => 'realized', 'realization_note' => $note, 'realized_at' => now()]);
    }

    public function reviewAction(User $actor, KoraPointVisitAction $action, string $operation, ?string $reason = null): void
    {
        $this->access->authorizeInternal($actor, 'kora-points.manage');
        if ($action->status !== 'realized') {
            throw ValidationException::withMessages(['action' => 'Seule une action signalée comme réalisée peut être vérifiée.']);
        }

        $action->update([
            'status' => $operation === 'validate' ? 'validated' : 'returned',
            'reviewed_by' => $actor->getKey(),
            'review_reason' => $operation === 'return' ? $reason : null,
            'reviewed_at' => now(),
        ]);

        $visit = $action->visit;
        $visit->update(['status' => $visit->actions()->where('status', '!=', 'validated')->doesntExist() ? 'closed' : 'in_progress']);
    }

    /** @param array<string, mixed> $attributes */
    public function createPlvOrder(User $actor, KoraPoint $point, array $attributes): KoraPointPlvOrder
    {
        $this->access->authorizeInternal($actor, 'kora-points.manage');

        return KoraPointPlvOrder::query()->create([
            'point_id' => $point->getKey(),
            'created_by' => $actor->getKey(),
            'support' => $attributes['support'],
            'quantity' => $attributes['quantity'],
            'status' => 'prepared',
        ]);
    }

    public function updatePlvOrder(User $actor, KoraPointPlvOrder $order, string $operation, ?string $reason = null): void
    {
        $this->access->authorizeInternal($actor, 'kora-points.manage');

        if ($operation === 'deliver' && $order->status === 'prepared') {
            $order->update(['status' => 'delivered', 'delivered_at' => now()]);

            return;
        }

        if ($operation === 'install' && $order->status === 'delivered') {
            $order->update(['status' => 'installed', 'installed_at' => now()]);

            return;
        }

        if ($operation === 'cancel' && in_array($order->status, ['prepared', 'delivered'], true)) {
            $order->update(['status' => 'cancelled', 'cancelled_by' => $actor->getKey(), 'cancellation_reason' => $reason, 'cancelled_at' => now()]);

            return;
        }

        throw ValidationException::withMessages(['operation' => 'Cette étape ne correspond pas à l’état actuel de la commande.']);
    }

    private function assertAssignable(User $assignee, KoraPoint $point, int $index): void
    {
        $isPointAgent = $assignee->hasRole('kora_point_agent') && (int) $assignee->agency_id === (int) $point->agency_id;
        $isKoraUser = ! $this->access->isPartner($assignee);

        if ($assignee->status !== UserStatus::Active || (! $isPointAgent && ! $isKoraUser)) {
            throw ValidationException::withMessages(["actions.{$index}.assignee_uuid" => 'Le responsable doit être un utilisateur KORA actif ou un agent actif de ce Point.']);
        }
    }
}
