<?php

namespace App\Modules\CompanyAccounts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyPortalStatementController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $this->account($request);

        $statements = $account->billingStatements()
            ->orderByDesc('period_key')
            ->paginate(12);

        $statements->setCollection($statements->getCollection()->map(
            fn (CompanyBillingStatement $statement): array => $this->serialize($statement),
        )->values());

        return Inertia::render('company-portal/statements', [
            'account' => $this->accountProps($account),
            'statements' => $statements,
        ]);
    }

    public function show(Request $request, CompanyBillingStatement $statement): Response
    {
        $account = $this->account($request);
        abort_unless($statement->company_account_id === $account->getKey(), 404);

        return Inertia::render('company-portal/statement-detail', [
            'account' => $this->accountProps($account),
            'statement' => $this->serialize($statement),
        ]);
    }

    /** @return array<string, mixed> */
    private function serialize(CompanyBillingStatement $statement): array
    {
        return [
            'uuid' => $statement->uuid,
            'reference' => $statement->reference,
            'periodKey' => $statement->period_key,
            'periodLabel' => $this->periodLabel($statement->period_key),
            'status' => $statement->status->value,
            'statusLabel' => $statement->status->label(),
            'currency' => $statement->currency,
            'debitTotal' => $statement->debit_total,
            'refundTotal' => $statement->refund_total,
            'netTotal' => $statement->net_total,
            'reservationsCount' => $statement->reservations_count,
            'invoicesCount' => $statement->invoices_count,
            'lines' => $statement->lines ?? [],
            'generatedAt' => $statement->generated_at?->toISOString(),
            'emailedAt' => $statement->emailed_at?->toISOString(),
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

    /** @return array{uuid: string, name: string, reference: string} */
    private function accountProps(CompanyAccount $account): array
    {
        return [
            'uuid' => $account->uuid,
            'name' => $account->name,
            'reference' => $account->reference,
        ];
    }

    private function account(Request $request): CompanyAccount
    {
        return $request->attributes->get('companyAccount');
    }
}
