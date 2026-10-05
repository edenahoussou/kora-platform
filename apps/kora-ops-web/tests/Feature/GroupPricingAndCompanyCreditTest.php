<?php

use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Services\GroupPricingService;
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditTransactionType;
use App\Modules\CompanyAccounts\Data\CompanyDiscountType;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\OperationsSettings;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    GroupBookingSetting::query()->create(['minimum_passengers' => 5, 'maximum_passengers' => 10]);
    $settings = app(OperationsSettings::class);
    $settings->groupDiscountEnabled = true;
    $settings->groupDiscountPassengerThreshold = 10;
    $settings->groupDiscountPercentage = 10;
    $settings->save();
});

it('classifies groups and applies the discount only at the configured threshold', function (): void {
    $pricing = app(GroupPricingService::class);

    expect($pricing->calculate([5000], 4, 'XOF')['group'])->toBeFalse()
        ->and($pricing->calculate([5000], 5, 'XOF')['group'])->toBeTrue()
        ->and($pricing->calculate([5000], 5, 'XOF')['group_discount']['applied'])->toBeFalse()
        ->and($pricing->calculate([5000], 10, 'XOF')['group_discount']['amount'])->toBe(5000)
        ->and(array_sum($pricing->calculate([5000], 10, 'XOF')['passenger_allocations']))->toBe(45000);
});

it('rejects a transaction above the configured maximum atomically', function (): void {
    expect(fn (): array => app(GroupPricingService::class)->calculate([5000], 11, 'XOF'))
        ->toThrow(ValidationException::class);
});

it('disables the automatic group discount when operations turn it off', function (): void {
    $settings = app(OperationsSettings::class);
    $settings->groupDiscountEnabled = false;
    $settings->save();

    $pricing = app(GroupPricingService::class)->calculate([5000], 10, 'XOF');

    expect($pricing['group_discount']['applied'])->toBeFalse()
        ->and($pricing['group_discount']['amount'])->toBe(0)
        ->and($pricing['total_amount'])->toBe(50000);
});

it('aggregates multiple legs before applying one global group discount', function (): void {
    $result = app(GroupPricingService::class)->calculate([5000, 3000], 10, 'XOF', [500, -200]);

    expect($result['subtotal_amount'])->toBe(80000)
        ->and($result['dynamic_adjustment_amount'])->toBe(3000)
        ->and($result['group_discount']['amount'])->toBe(8000)
        ->and($result['total_amount'])->toBe(72000)
        ->and(array_sum($result['passenger_allocations']))->toBe($result['total_amount']);
});

it('replaces the group discount with a negotiated percentage discount for a company account', function (): void {
    $account = CompanyAccount::query()->create([
        'name' => 'ACME',
        'type' => 'company',
        'reference' => 'ACME-DISCOUNT',
        'monthly_credit_limit' => 50000,
        'status' => 'active',
        'discount_type' => CompanyDiscountType::Percentage,
        'discount_value' => 10,
    ]);

    $pricing = app(GroupPricingService::class)->calculate(
        unitAmounts: [10000],
        passengerCount: 10,
        currency: 'XOF',
        promotionDiscount: 5000,
        companyAccount: $account,
    );

    expect($pricing['promotion_discount_amount'])->toBe(5000)
        ->and($pricing['group_discount']['applied'])->toBeFalse()
        ->and($pricing['group_discount']['amount'])->toBe(0)
        ->and($pricing['company_discount'])->toBe([
            'applied' => true,
            'type' => 'percentage',
            'value' => 10.0,
            'amount' => 9500,
        ])
        ->and($pricing['total_amount'])->toBe(85500)
        ->and(array_sum($pricing['passenger_allocations']))->toBe(85500);
});

it('caps a negotiated fixed discount at the remaining itinerary subtotal', function (): void {
    $account = CompanyAccount::query()->create([
        'name' => 'ACME',
        'type' => 'company',
        'reference' => 'ACME-FIXED',
        'monthly_credit_limit' => 50000,
        'status' => 'active',
        'discount_type' => CompanyDiscountType::Amount,
        'discount_value' => 30000,
    ]);

    $pricing = app(GroupPricingService::class)->calculate(
        unitAmounts: [10000],
        passengerCount: 2,
        currency: 'XOF',
        companyAccount: $account,
    );

    expect($pricing['group_discount']['applied'])->toBeFalse()
        ->and($pricing['company_discount']['amount'])->toBe(20000)
        ->and($pricing['total_amount'])->toBe(0)
        ->and($pricing['passenger_allocations'])->toBe([0, 0]);
});

it('does not apply a group discount when an enterprise account has no negotiated discount', function (): void {
    $account = CompanyAccount::query()->create([
        'name' => 'ACME',
        'type' => 'company',
        'reference' => 'ACME-NO-DISCOUNT',
        'monthly_credit_limit' => 50000,
        'status' => 'active',
    ]);

    $pricing = app(GroupPricingService::class)->calculate(
        unitAmounts: [10000],
        passengerCount: 10,
        currency: 'XOF',
        companyAccount: $account,
    );

    expect($pricing['group_discount']['applied'])->toBeFalse()
        ->and($pricing['company_discount']['applied'])->toBeFalse()
        ->and($pricing['company_discount']['amount'])->toBe(0)
        ->and($pricing['total_amount'])->toBe(100000);
});

it('reserves and converts company credit without creating a cash movement', function (): void {
    $account = CompanyAccount::query()->create([
        'name' => 'ACME',
        'type' => 'company',
        'reference' => 'ACME-001',
        'monthly_credit_limit' => 50000,
        'status' => 'active',
    ]);
    $reservation = Reservation::query()->create([
        'company_account_id' => $account->id,
        'payer_type' => 'company',
        'purchase_type' => 'group',
        'group_size' => 10,
        'status' => 'held',
        'total_amount' => 45000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
        'total_snapshot' => ['passenger_allocations' => array_fill(0, 10, 4500)],
    ]);

    $service = app(CompanyCreditService::class);
    $hold = $service->reserve($reservation, $account);
    expect($hold->status)->toBe(CompanyCreditHoldStatus::Reserved)
        ->and($service->availableCredit($account->fresh()))->toBe(5000);

    $payment = Payment::query()->create([
        'reservation_id' => $reservation->id,
        'method' => 'company_credit',
        'provider' => 'company_credit',
        'idempotency_key' => 'payment-'.fake()->uuid(),
        'amount' => 45000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);
    $transaction = $service->convert($hold, $payment);

    expect($transaction->type)->toBe(CompanyCreditTransactionType::Debit)
        ->and($service->availableCredit($account->fresh()))->toBe(5000)
        ->and($hold->fresh()->status)->toBe(CompanyCreditHoldStatus::Converted);
});
