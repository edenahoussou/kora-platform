<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Actions\CreateWalletLoyaltyRuleDraftAction;
use App\Modules\Wallet\Actions\ScheduleWalletLoyaltyRuleAction;
use App\Modules\Wallet\Actions\SimulateWalletLoyaltyRulesAction;
use App\Modules\Wallet\Actions\UpdateWalletLoyaltyRuleDraftAction;
use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use App\Modules\Wallet\Data\WalletLoyaltyRuleVersionStatus;
use App\Modules\Wallet\Http\Requests\SimulateWalletLoyaltyRuleRequest;
use App\Modules\Wallet\Http\Requests\WalletLoyaltyRuleVersionRequest;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OpsWalletLoyaltyRuleController extends Controller
{
    public function index(WalletSettings $settings): Response
    {
        Gate::authorize('viewAny', WalletLoyaltyRuleVersion::class);
        $versions = WalletLoyaltyRuleVersion::query()->with(['createdBy', 'scheduledBy'])->latest('version')->limit(30)->get();
        $active = $versions->firstWhere('status', WalletLoyaltyRuleVersionStatus::Active);
        $scheduled = $versions->contains(fn (WalletLoyaltyRuleVersion $version): bool => $version->status === WalletLoyaltyRuleVersionStatus::Scheduled);

        return Inertia::render('wallet/ops', [
            'view' => 'rules',
            'activeRules' => WalletLoyaltyRuleSet::fromSettings($settings),
            'versions' => $versions->map(fn (WalletLoyaltyRuleVersion $version): array => [
                'uuid' => (string) $version->uuid,
                'version' => (int) $version->version,
                'status' => $version->status->value,
                'rules' => WalletLoyaltyRuleSet::fromArray($version->rules),
                'effectiveAt' => $version->effective_at?->toIso8601String(),
                'publishedAt' => $version->published_at?->toIso8601String(),
                'activatedAt' => $version->activated_at?->toIso8601String(),
                'createdBy' => $version->createdBy?->name,
                'scheduledBy' => $version->scheduledBy?->name,
            ])->values()->all(),
            'draft' => $versions->firstWhere('status', WalletLoyaltyRuleVersionStatus::Draft)?->only([
                'uuid',
                'version',
                'rules',
            ]),
            'capabilities' => [
                'manageRules' => Gate::allows('manage', WalletLoyaltyRuleVersion::class),
                'hasScheduledVersion' => $scheduled,
            ],
            'urls' => [
                'dashboard' => route('wallet.dashboard'),
                'accounts' => route('wallet.accounts'),
                'transactions' => route('wallet.transactions'),
                'rules' => route('wallet.rules'),
                'settings' => route('administration.settings.wallet'),
                'restitutions' => route('administration.wallet-restitutions.index'),
            ],
        ]);
    }

    public function store(Request $request, CreateWalletLoyaltyRuleDraftAction $createDraft): RedirectResponse
    {
        Gate::authorize('create', WalletLoyaltyRuleVersion::class);
        $createDraft->execute($request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le brouillon de règles est prêt.']);

        return to_route('wallet.rules');
    }

    public function update(
        WalletLoyaltyRuleVersionRequest $request,
        WalletLoyaltyRuleVersion $version,
        UpdateWalletLoyaltyRuleDraftAction $updateDraft,
    ): RedirectResponse {
        Gate::authorize('update', $version);
        $updateDraft->execute($version, $request->values());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Le brouillon a été enregistré.']);

        return to_route('wallet.rules');
    }

    public function simulate(
        SimulateWalletLoyaltyRuleRequest $request,
        WalletLoyaltyRuleVersion $version,
        SimulateWalletLoyaltyRulesAction $simulate,
    ): JsonResponse {
        Gate::authorize('simulate', $version);
        $input = $request->validated();

        return response()->json([
            'data' => $simulate->execute(
                rules: WalletLoyaltyRuleSet::fromArray($version->rules),
                ticketAmount: (int) $input['ticket_amount'],
                pointsUsed: (int) ($input['points_used'] ?? 0),
                qualifyingTrips: (int) ($input['qualifying_trips'] ?? 0),
                isFirstUsage: (bool) ($input['first_usage'] ?? false),
            ),
        ]);
    }

    public function schedule(
        Request $request,
        WalletLoyaltyRuleVersion $version,
        ScheduleWalletLoyaltyRuleAction $schedule,
    ): RedirectResponse {
        Gate::authorize('schedule', $version);
        $values = $request->validate([
            'effective_at' => ['required', 'date', 'after:now'],
        ]);
        $schedule->execute($version, $request->user(), Carbon::parse($values['effective_at']));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La nouvelle version sera activée à la date programmée.']);

        return to_route('wallet.rules');
    }
}
