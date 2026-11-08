<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\OpsWalletReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OpsWalletPageController extends Controller
{
    public function dashboard(OpsWalletReader $reader): Response
    {
        Gate::authorize('viewAny', Wallet::class);

        return Inertia::render('wallet/ops', [
            'view' => 'dashboard',
            'dashboard' => $reader->dashboard()->toArray(),
            'capabilities' => [
                'manageRules' => Gate::allows('manage', WalletLoyaltyRuleVersion::class),
                'manageSettings' => Gate::allows('update', WalletSettings::class),
            ],
            'urls' => $this->urls(),
        ]);
    }

    public function accounts(Request $request, OpsWalletReader $reader): Response
    {
        Gate::authorize('viewAny', Wallet::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,suspended,locked'],
            'balance_type' => ['nullable', 'in:cash,points'],
            'expiration' => ['nullable', 'in:near'],
            'agency_uuid' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        return Inertia::render('wallet/ops', [
            'view' => 'accounts',
            'accounts' => $reader->accounts($filters),
            'filters' => $filters,
            'agencyOptions' => $this->agencyOptions(),
            'urls' => $this->urls(),
        ]);
    }

    public function account(Wallet $wallet, OpsWalletReader $reader): Response
    {
        Gate::authorize('view', $wallet);

        return Inertia::render('wallet/ops', [
            'view' => 'account',
            'account' => $reader->account($wallet)->toArray(),
            'urls' => $this->urls(),
        ]);
    }

    public function movements(Request $request, OpsWalletReader $reader): Response
    {
        Gate::authorize('viewAny', WalletTransaction::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'balance_type' => ['nullable', 'in:cash,points'],
            'direction' => ['nullable', 'in:credit,debit'],
            'reason' => ['nullable', 'in:'.implode(',', WalletTransactionReason::values())],
            'agency_uuid' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ]);

        return Inertia::render('wallet/ops', [
            'view' => 'transactions',
            'movements' => $reader->movements($filters),
            'filters' => $filters,
            'agencyOptions' => $this->agencyOptions(),
            'urls' => $this->urls(),
        ]);
    }

    public function movement(WalletTransaction $transaction, OpsWalletReader $reader): Response
    {
        Gate::authorize('view', $transaction);

        return Inertia::render('wallet/ops', [
            'view' => 'transaction',
            'movement' => $reader->movement($transaction)->toArray(),
            'urls' => $this->urls(),
        ]);
    }

    /** @return array<string, string> */
    private function urls(): array
    {
        return [
            'dashboard' => route('wallet.dashboard'),
            'accounts' => route('wallet.accounts'),
            'transactions' => route('wallet.transactions'),
            'rules' => route('wallet.rules'),
            'settings' => route('administration.settings.wallet'),
            'restitutions' => route('administration.wallet-restitutions.index'),
        ];
    }

    /** @return list<array{uuid: string, name: string}> */
    private function agencyOptions(): array
    {
        $agencies = Agency::query()
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (Agency $agency): array => [
                'uuid' => (string) $agency->uuid,
                'name' => (string) $agency->name,
            ])
            ->all();

        return array_values($agencies);
    }
}
