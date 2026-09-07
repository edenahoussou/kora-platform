<?php

use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;

it('allows only the approved fiscal invoice transitions', function (): void {
    expect(FiscalInvoiceStatus::Pending->canTransitionTo(FiscalInvoiceStatus::Certified))->toBeTrue()
        ->and(FiscalInvoiceStatus::Pending->canTransitionTo(FiscalInvoiceStatus::Failed))->toBeTrue()
        ->and(FiscalInvoiceStatus::Failed->canTransitionTo(FiscalInvoiceStatus::Pending))->toBeTrue()
        ->and(FiscalInvoiceStatus::Certified->canTransitionTo(FiscalInvoiceStatus::Pending))->toBeFalse();
});

it('rejects mutations of a certified invoice', function (): void {
    expect(fn (): FiscalInvoiceStatus => FiscalInvoiceStatus::Certified->transitionTo(FiscalInvoiceStatus::Canceled))
        ->toThrow(LogicException::class);
});
