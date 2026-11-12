<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
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
    public function dashboard(OpsWalletReader $reader, WalletSettings $settings): Response
    {
        Gate::authorize('viewAny', Wallet::class);

        return Inertia::render('wallet/ops', [
            'view' => 'dashboard',
            'dashboard' => $reader->dashboard()->toArray(),
            'activeRules' => WalletLoyaltyRuleSet::fromSettings($settings),
            'activeRuleVersion' => WalletLoyaltyRuleVersion::query()
                ->where('status', WalletLoyaltyRuleVersionStatus::Active->value)
                ->latest('version')
                ->value('version'),
            'capabilities' => [
                'manageRules' => Gate::allows('manage', WalletLoyaltyRuleVersion::class),
                'manageSettings' => Gate::allows('update', WalletSettings::class),
            ],
            'urls' => $this->urls(),
        ]);
    }

    public function accounts(
        Request $request,
        OpsWalletReader $reader,
        WalletSettings $settings,
    ): Response {
        Gate::authorize('viewAny', Wallet::class);
        $filters = $request->validate($this->accountFilterRules());
        $filters['per_page'] ??= 10;

        return Inertia::render('wallet/ops', [
            'view' => 'accounts',
            'accounts' => $reader->accounts($filters),
            'filters' => $filters,
            'agencyOptions' => $this->agencyOptions(),
            'activeRules' => WalletLoyaltyRuleSet::fromSettings($settings),
            'urls' => $this->urls(),
        ]);
    }

    public function account(
        Request $request,
        Wallet $wallet,
        OpsWalletReader $reader,
        WalletSettings $settings,
    ): Response {
        Gate::authorize('view', $wallet);
        $filters = $request->validate($this->accountFilterRules());
        $returnFilters = array_filter(
            $filters,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return Inertia::render('wallet/ops', [
            'view' => 'account',
            'account' => $reader->account($wallet)->toArray(),
            'returnUrl' => route('wallet.accounts', $returnFilters),
            'activeRules' => WalletLoyaltyRuleSet::fromSettings($settings),
            'urls' => $this->urls(),
        ]);
    }

    public function movements(Request $request, OpsWalletReader $reader): Response
    {
        Gate::authorize('viewAny', WalletTransaction::class);
        $filters = $request->validate($this->movementFilterRules());

        return Inertia::render('wallet/ops', [
            'view' => 'transactions',
            'movements' => $reader->movements($filters),
            'filters' => $filters,
            'agencyOptions' => $this->agencyOptions(),
            'urls' => $this->urls(),
        ]);
    }

    public function movement(
        Request $request,
        WalletTransaction $transaction,
        OpsWalletReader $reader,
    ): Response {
        Gate::authorize('view', $transaction);
        $filters = $request->validate($this->movementFilterRules());
        $returnFilters = array_filter(
            $filters,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return Inertia::render('wallet/ops', [
            'view' => 'transaction',
            'movement' => $reader->movement($transaction)->toArray(),
            'returnUrl' => route('wallet.transactions', $returnFilters),
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

    /** @return array<string, list<string>> */
    private function accountFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,suspended,locked'],
            'balance_type' => ['nullable', 'in:cash,points'],
            'expiration' => ['nullable', 'in:near'],
            'agency_uuid' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ];
    }

    /** @return array<string, list<string>> */
    private function movementFilterRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'balance_type' => ['nullable', 'in:cash,points'],
            'direction' => ['nullable', 'in:credit,debit'],
            'reason' => ['nullable', 'in:'.implode(',', WalletTransactionReason::values())],
            'agency_uuid' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
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
