<?php

namespace App\Modules\CompanyAccounts\Services;

use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use App\Modules\CompanyAccounts\Data\CompanyBillingStatementStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use App\Modules\Support\Services\DocumentBrandingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Construit le payload de la vue Blade `company-accounts.statement` —
 * relevé de facturation mensuel brandé KORA (PDF A4 + export Excel).
 */
class CompanyBillingStatementDocumentService
{
    public function __construct(
        private readonly DocumentBrandingService $branding,
    ) {}

    /** @return array<string, mixed> */
    public function payload(CompanyBillingStatement $statement): array
    {
        $statement->loadMissing('companyAccount');
        $account = $statement->companyAccount;

        return [
            'logoDataUri' => $this->branding->logoDataUri(),
            'company' => $this->branding->company(),
            'document' => [
                'reference' => $statement->reference,
                'generatedAt' => $statement->generated_at?->format('d/m/Y à H:i') ?? now()->format('d/m/Y à H:i'),
                'emailedAt' => $statement->emailed_at?->format('d/m/Y à H:i'),
                'statusLabel' => $statement->status->label(),
                'statusTone' => $statement->status === CompanyBillingStatementStatus::Available ? 'green' : 'gold',
            ],
            'account' => $this->account($account),
            'statement' => [
                'periodLabel' => $this->periodLabel($statement->period_key),
                'periodKey' => $statement->period_key,
                'currency' => $statement->currency,
                'debitTotal' => (int) $statement->debit_total,
                'refundTotal' => (int) $statement->refund_total,
                'netTotal' => (int) $statement->net_total,
                'reservationsCount' => (int) $statement->reservations_count,
                'invoicesCount' => (int) $statement->invoices_count,
            ],
            'lines' => collect($statement->lines ?? [])->map(
                fn (array $line): array => $this->line($line),
            )->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function account(CompanyAccount $account): array
    {
        return [
            'name' => $account->name,
            'reference' => $account->reference,
            'typeLabel' => match ($account->type) {
                CompanyAccountType::Company => 'Entreprise',
                CompanyAccountType::Ngo => 'ONG',
                CompanyAccountType::Embassy => 'Ambassade',
            },
            'email' => $account->email,
            'phone' => $account->phone,
            'addressLines' => array_values(array_filter([
                $account->address,
                trim($account->city.($account->country !== null ? ' ('.$account->country.')' : '')),
            ])),
            'ifu' => $account->ifu,
            'rccm' => $account->rccm,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function line(array $line): array
    {
        $isRefund = ($line['type'] ?? 'debit') === 'refund';
        $reservation = is_array($line['reservation'] ?? null) ? $line['reservation'] : null;
        $invoice = is_array($line['invoice'] ?? null) ? $line['invoice'] : null;

        return [
            'type' => $isRefund ? 'refund' : 'debit',
            'typeLabel' => $isRefund ? 'Avoir' : 'Débit',
            'typeTone' => $isRefund ? 'gold' : 'navy',
            'reference' => (string) ($line['reference'] ?? '—'),
            'occurredAt' => isset($line['occurredAt']) && is_string($line['occurredAt'])
                ? Carbon::parse($line['occurredAt'])->format('d/m/Y H:i')
                : '—',
            'reservation' => $reservation === null ? null : [
                'reference' => (string) ($reservation['reference'] ?? '—'),
                'itinerary' => (string) ($reservation['itinerary'] ?? ''),
                'passengers' => (int) ($reservation['passengers'] ?? 0),
            ],
            'invoice' => $invoice === null ? null : [
                'koraReference' => (string) ($invoice['koraReference'] ?? '—'),
                'status' => (string) ($invoice['status'] ?? ''),
            ],
            'amount' => (int) ($line['amount'] ?? 0),
            'signedAmount' => $isRefund ? -1 * (int) ($line['amount'] ?? 0) : (int) ($line['amount'] ?? 0),
        ];
    }

    private function periodLabel(string $periodKey): string
    {
        return Str::ucfirst(
            (new CarbonImmutable($periodKey.'-01'))
                ->settings(['locale' => 'fr'])
                ->isoFormat('MMMM YYYY'),
        );
    }
}
