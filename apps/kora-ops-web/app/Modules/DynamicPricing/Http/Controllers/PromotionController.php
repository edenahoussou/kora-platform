<?php

namespace App\Modules\DynamicPricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Actions\ChangePromotionStatusAction;
use App\Modules\DynamicPricing\Actions\CreatePromotionAction;
use App\Modules\DynamicPricing\Actions\DetectPromotionConflictsAction;
use App\Modules\DynamicPricing\Actions\UpdatePromotionAction;
use App\Modules\DynamicPricing\Data\PromotionBenefitType;
use App\Modules\DynamicPricing\Data\PromotionChannel;
use App\Modules\DynamicPricing\Data\PromotionScope;
use App\Modules\DynamicPricing\Data\PromotionStatus;
use App\Modules\DynamicPricing\Http\Requests\ChangePromotionStatusRequest;
use App\Modules\DynamicPricing\Http\Requests\StorePromotionRequest;
use App\Modules\DynamicPricing\Http\Requests\UpdatePromotionRequest;
use App\Modules\DynamicPricing\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Promotion::class);

        $query = Promotion::query()
            ->with(['line:id,uuid,code,name'])
            ->withCount('redemptions')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('scope'), fn ($query) => $query->where('scope_type', $request->string('scope')))
            ->when($request->filled('line'), function ($query) use ($request): void {
                $line = Line::query()->where('uuid', $request->string('line')->value())->first();
                $query->where('line_id', $line?->getKey() ?? 0);
            })
            ->orderBy('status')
            ->orderBy('priority')
            ->orderByDesc('starts_at');

        $promotions = $query->paginate(15)->withQueryString();
        $promotions->setCollection($promotions->getCollection()->map(
            fn (Promotion $promotion): array => $this->serializePromotion($promotion),
        ));

        return Inertia::render('promotions', [
            'promotions' => $promotions,
            'statuses' => PromotionStatus::translatedOptions(),
            'scopes' => PromotionScope::translatedOptions(),
            'benefitTypes' => PromotionBenefitType::translatedOptions(),
            'channels' => PromotionChannel::translatedOptions(),
            'lines' => Line::query()
                ->with(['stops' => fn ($query) => $query->orderBy('stop_order')])
                ->orderBy('code')
                ->get()
                ->map(fn (Line $line): array => [
                    'uuid' => $line->uuid,
                    'code' => $line->code,
                    'name' => $line->name,
                    'stops' => $line->stops->map(fn (Stop $stop): array => [
                        'uuid' => $stop->uuid,
                        'name' => $stop->name,
                        'city' => $stop->city,
                    ])->values()->all(),
                ]),
            'permissions' => [
                'canCreate' => $request->user()?->can('create', Promotion::class) ?? false,
                'canUpdate' => $request->user()?->can('pricing.manage') ?? false,
            ],
            'promotionDetail' => Inertia::optional(
                fn (): ?array => $this->serializeDetail($request->string('promotion')->value()),
            ),
        ]);
    }

    public function store(
        StorePromotionRequest $request,
        DetectPromotionConflictsAction $detectConflicts,
        CreatePromotionAction $createPromotion,
    ): RedirectResponse {
        $payload = $request->payload();
        $this->rejectOnConflicts($detectConflicts, $payload, $request->boolean('force'));

        $promotion = $createPromotion->execute($payload);
        activity()->performedOn($promotion)->causedBy($request->user())->log('promotion_created');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promotion créée en brouillon.')]);

        return to_route('promotions.index');
    }

    public function update(
        UpdatePromotionRequest $request,
        Promotion $promotion,
        DetectPromotionConflictsAction $detectConflicts,
        UpdatePromotionAction $updatePromotion,
    ): RedirectResponse {
        $payload = $request->payload();
        $this->rejectOnConflicts($detectConflicts, $payload, $request->boolean('force'), $promotion);

        $promotion = $updatePromotion->execute($promotion, $payload);
        activity()->performedOn($promotion)->causedBy($request->user())->log('promotion_updated');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promotion mise à jour.')]);

        return to_route('promotions.index');
    }

    public function changeStatus(
        ChangePromotionStatusRequest $request,
        Promotion $promotion,
        ChangePromotionStatusAction $changeStatus,
    ): RedirectResponse {
        $status = $request->status();
        $promotion = $changeStatus->execute($promotion, $status);
        activity()->performedOn($promotion)->causedBy($request->user())->log('promotion_status_changed');

        $message = match ($status) {
            PromotionStatus::Active => __('Promotion activée. Elle est applicable aux nouvelles ventes selon sa période.'),
            PromotionStatus::Inactive => __('Promotion désactivée.'),
            PromotionStatus::Archived => __('Promotion archivée.'),
            PromotionStatus::Draft => __('Promotion replacée en brouillon.'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('promotions.index');
    }

    /**
     * Signale les conflits de périmètre/période/priorité avant enregistrement.
     * Inertia n'expose que le premier message par champ : la charge utile est
     * encodée en JSON dans `errors.conflicts`, décodée côté formulaire pour
     * afficher la liste et demander confirmation (resoumission `force`).
     *
     * @param  array{scope_type: string, channel: string|null, line_id: int|null, origin_stop_id: int|null, destination_stop_id: int|null, priority: int, starts_at: string, ends_at: string|null, ...}  $payload
     *
     * @throws ValidationException
     */
    private function rejectOnConflicts(
        DetectPromotionConflictsAction $detectConflicts,
        array $payload,
        bool $force,
        ?Promotion $except = null,
    ): void {
        if ($force) {
            return;
        }

        $conflicts = $detectConflicts->execute($payload, $except);
        if ($conflicts->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'conflicts' => json_encode($conflicts->map(fn (Promotion $conflict): array => [
                'uuid' => $conflict->uuid,
                'name' => $conflict->name,
                'code' => $conflict->code,
                'priority' => $conflict->priority,
                'status' => $conflict->displayStatus(),
                'startsAt' => $conflict->starts_at->toIso8601String(),
                'endsAt' => $conflict->ends_at?->toIso8601String(),
            ])->values()->all(), JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Détail du drawer : utilisations réelles (ventes payées) et historique
     * d'activité, chargés uniquement quand le frontend demande la prop.
     *
     * @return array<string, mixed>|null
     */
    private function serializeDetail(string $uuid): ?array
    {
        $promotion = Promotion::query()
            ->where('uuid', $uuid)
            ->with(['line', 'originStop', 'destinationStop', 'redemptions.reservation'])
            ->first();

        if (! $promotion instanceof Promotion) {
            return null;
        }

        return [
            'promotion' => $this->serializePromotion($promotion),
            'redemptions' => $promotion->redemptions
                ->sortByDesc('created_at')
                ->take(50)
                ->map(fn ($redemption): array => [
                    'uuid' => $redemption->uuid,
                    'reservationUuid' => $redemption->reservation?->uuid,
                    'discountAmount' => (int) $redemption->discount_amount,
                    'createdAt' => $redemption->created_at?->toIso8601String(),
                ])->values()->all(),
            'history' => Activity::query()
                ->where('subject_type', $promotion->getMorphClass())
                ->where('subject_id', $promotion->getKey())
                ->with('causer')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (Activity $activity): array => [
                    'event' => $activity->description,
                    'at' => $activity->created_at?->toIso8601String(),
                    'actor' => $activity->causer instanceof User ? $activity->causer->name : null,
                ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializePromotion(Promotion $promotion): array
    {
        return [
            'uuid' => $promotion->uuid,
            'name' => $promotion->name,
            'code' => $promotion->code,
            'benefitType' => $promotion->benefit_type->value,
            'benefitValue' => (float) $promotion->benefit_value,
            'scopeType' => $promotion->scope_type->value,
            'channel' => $promotion->channel?->value,
            'line' => $promotion->line ? [
                'uuid' => $promotion->line->uuid,
                'code' => $promotion->line->code,
                'name' => $promotion->line->name,
            ] : null,
            'originStopUuid' => $promotion->originStop?->uuid,
            'originStopName' => $promotion->originStop?->name,
            'destinationStopUuid' => $promotion->destinationStop?->uuid,
            'destinationStopName' => $promotion->destinationStop?->name,
            'priority' => $promotion->priority,
            'startsAt' => $promotion->starts_at->toIso8601String(),
            'endsAt' => $promotion->ends_at?->toIso8601String(),
            'status' => $promotion->status->value,
            'displayStatus' => $promotion->displayStatus(),
            'maxUses' => $promotion->max_uses,
            'usedCount' => $promotion->used_count,
            'redemptionsCount' => (int) ($promotion->redemptions_count ?? 0),
            'createdAt' => $promotion->created_at?->toIso8601String(),
        ];
    }
}
