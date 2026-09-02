<?php

namespace App\Modules\DynamicPricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Http\Requests\UpdateGroupBookingSettingRequest;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\DynamicPricing\Http\Requests\StoreGroupDiscountRuleRequest;
use App\Modules\DynamicPricing\Models\GroupDiscountRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Group Discounts
 *
 * Group discount rule management and group booking settings.
 */
class GroupDiscountRuleController extends Controller
{
    /**
     * List all group discount rules ordered by start date.
     *
     * @authenticated
     *
     * @response 200 scenario="Discount rule list" {
     *   "data": [
     *     {
     *       "uuid": "d1234567-89ab-cdef-0123-456789abcdef",
     *       "status": "active",
     *       "passenger_threshold": 5,
     *       "percentage": 10.0,
     *       "starts_at": "2026-09-01T00:00:00+00:00",
     *       "ends_at": null
     *     }
     *   ]
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('group-discounts.view'), 403);

        return response()->json(['data' => GroupDiscountRule::query()->latest('starts_at')->latest('id')->get()->map(fn (GroupDiscountRule $rule): array => $this->serialize($rule))]);
    }

    /**
     * Create a new group discount rule.
     *
     * The passenger threshold must be >= the configured minimum group size.
     * Only one active rule can cover a given period.
     *
     * @authenticated
     *
     * @response 201 scenario="Discount rule created" {
     *   "data": {
     *     "uuid": "d1234567-89ab-cdef-0123-456789abcdef",
     *     "status": "active",
     *     "passenger_threshold": 5,
     *     "percentage": 10.0,
     *     "starts_at": "2026-09-01T00:00:00+00:00",
     *     "ends_at": null
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     * @response 422 scenario="Threshold below minimum" {
     *   "message": "The given data was invalid.",
     *   "errors": {"passenger_threshold": ["Le seuil de remise doit être supérieur ou égal au minimum groupe configuré."]}
     * }
     * @response 422 scenario="Overlapping active rule" {
     *   "message": "The given data was invalid.",
     *   "errors": {"starts_at": ["Une règle groupe active couvre déjà cette période."]}
     * }
     */
    public function store(StoreGroupDiscountRuleRequest $request): JsonResponse
    {
        $this->validateThreshold((int) $request->integer('passenger_threshold'));
        $this->ensureNoOverlap($request);
        $rule = GroupDiscountRule::query()->create($request->validated() + ['created_by' => $request->user()->getKey(), 'updated_by' => $request->user()->getKey()]);
        activity()->performedOn($rule)->causedBy($request->user())->withProperties($rule->only(['passenger_threshold', 'percentage', 'status']))->log('group_discount_rule_created');

        return response()->json(['data' => $this->serialize($rule)], 201);
    }

    /**
     * Update an existing group discount rule.
     *
     * @authenticated
     *
     * @response 200 scenario="Discount rule updated" {
     *   "data": {
     *     "uuid": "d1234567-89ab-cdef-0123-456789abcdef",
     *     "status": "active",
     *     "passenger_threshold": 8,
     *     "percentage": 15.0,
     *     "starts_at": "2026-09-01T00:00:00+00:00",
     *     "ends_at": "2026-12-31T00:00:00+00:00"
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     * @response 422 scenario="Overlapping active rule" {
     *   "message": "The given data was invalid.",
     *   "errors": {"starts_at": ["Une règle groupe active couvre déjà cette période."]}
     * }
     */
    public function update(StoreGroupDiscountRuleRequest $request, GroupDiscountRule $groupDiscountRule): JsonResponse
    {
        $this->validateThreshold((int) $request->integer('passenger_threshold'));
        $this->ensureNoOverlap($request, $groupDiscountRule);
        $groupDiscountRule->update($request->validated() + ['updated_by' => $request->user()->getKey()]);
        activity()->performedOn($groupDiscountRule)->causedBy($request->user())->withProperties($groupDiscountRule->only(['passenger_threshold', 'percentage', 'status']))->log('group_discount_rule_updated');

        return response()->json(['data' => $this->serialize($groupDiscountRule->fresh())]);
    }

    /**
     * Get the current group booking settings (min/max passengers).
     *
     * @authenticated
     *
     * @response 200 scenario="Group booking settings" {
     *   "data": {
     *     "minimum_passengers": 5,
     *     "maximum_passengers": 10
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 403 scenario="Forbidden" {
     *   "message": "This action is unauthorized."
     * }
     */
    public function settings(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('group-discounts.view'), 403);
        $settings = GroupBookingSetting::current();

        return response()->json(['data' => $settings->only(['minimum_passengers', 'maximum_passengers'])]);
    }

    /**
     * Update the group booking settings (min/max passengers).
     *
     * @authenticated
     *
     * @response 200 scenario="Settings updated" {
     *   "data": {
     *     "minimum_passengers": 3,
     *     "maximum_passengers": 15
     *   }
     * }
     * @response 401 scenario="Unauthenticated" {
     *   "message": "Unauthenticated."
     * }
     * @response 422 scenario="Validation error" {
     *   "message": "The given data was invalid.",
     *   "errors": {"maximum_passengers": ["The maximum passengers must be greater than or equal to 3."]}
     * }
     */
    public function updateSettings(UpdateGroupBookingSettingRequest $request): JsonResponse
    {
        $settings = GroupBookingSetting::current();
        $settings->update($request->validated() + ['updated_by' => $request->user()->getKey()]);
        activity()->event('group_booking_settings_updated')->performedOn($settings)->causedBy($request->user())->withProperties($settings->only(['minimum_passengers', 'maximum_passengers']))->log('group_booking_settings_updated');

        return response()->json(['data' => $settings->fresh()->only(['minimum_passengers', 'maximum_passengers'])]);
    }

    private function validateThreshold(int $threshold): void
    {
        if ($threshold < GroupBookingSetting::current()->minimum_passengers) {
            throw ValidationException::withMessages(['passenger_threshold' => 'Le seuil de remise doit être supérieur ou égal au minimum groupe configuré.']);
        }
    }

    private function ensureNoOverlap(StoreGroupDiscountRuleRequest $request, ?GroupDiscountRule $except = null): void
    {
        if ($request->string('status')->value() !== 'active') {
            return;
        }

        $query = GroupDiscountRule::query()->where('status', 'active');
        if ($except !== null) {
            $query->where('id', '!=', $except->getKey());
        }
        $incomingStart = $request->date('starts_at') ?? now()->startOfDay();
        $incomingEnd = $request->date('ends_at');
        $overlap = $query->get()->contains(function (GroupDiscountRule $rule) use ($incomingStart, $incomingEnd): bool {
            $ruleEnd = $rule->ends_at ?? now()->addCentury();
            $incomingEnd ??= now()->addCentury();

            return ($rule->starts_at ?? now()->subCentury())->lte($incomingEnd) && $ruleEnd->gte($incomingStart);
        });
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Une règle groupe active couvre déjà cette période.']);
        }
    }

    /** @return array<string, mixed> */
    private function serialize(GroupDiscountRule $rule): array
    {
        return [
            'uuid' => $rule->uuid,
            'status' => $rule->status->value,
            'passenger_threshold' => $rule->passenger_threshold,
            'percentage' => (float) $rule->percentage,
            'starts_at' => $rule->starts_at?->toIso8601String(),
            'ends_at' => $rule->ends_at?->toIso8601String(),
        ];
    }
}
