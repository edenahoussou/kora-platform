<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeCompanyAccountStatusRequest;
use App\Http\Requests\InviteCompanyReferentRequest;
use App\Http\Requests\StoreCompanyAccountWebRequest;
use App\Http\Requests\UpdateCompanyAccountWebRequest;
use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Actions\GenerateCompanyStatementPdfAction;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditTransactionType;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
use App\Modules\CompanyAccounts\Services\CompanyBillingStatementDocumentService;
use App\Modules\CompanyAccounts\Services\CompanyPurchaseHistoryReadService;
use App\Modules\Finance\Services\FinanceExportService;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyAccountWebController extends Controller
{
    public function __construct(
        private readonly PassengerIdentityResolver $identityResolver,
        private readonly FinanceExportService $exportService,
        private readonly CompanyPurchaseHistoryReadService $historyService,
        private readonly CompanyBillingStatementDocumentService $statementDocuments,
        private readonly GenerateCompanyStatementPdfAction $statementPdf,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CompanyAccount::class);

        $query = CompanyAccount::query()
            ->with(['members' => fn ($members) => $members
                ->where('status', CompanyMemberStatus::Active->value)
                ->with('user:id,uuid,name,email')])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('name');

        $accounts = $query->paginate(15)->withQueryString();
        $metrics = $this->creditMetrics($accounts->getCollection());
        $accounts->setCollection($accounts->getCollection()->map(
            fn (CompanyAccount $account): array => $this->serializeAccount($account, $metrics),
        )->values());

        $summaryIds = (clone $query)->reorder()->pluck('id');
        $summaryMetrics = $this->creditMetrics(CompanyAccount::query()->whereKey($summaryIds)->get());

        return Inertia::render('companies', [
            'accounts' => $accounts,
            'summary' => [
                'total' => $summaryIds->count(),
                'active' => CompanyAccount::query()->whereKey($summaryIds)->where('status', CompanyAccountStatus::Active->value)->count(),
                'withAvailableCredit' => collect($summaryMetrics)->filter(fn (array $metric): bool => $metric['availableCredit'] > 0)->count(),
            ],
            'types' => collect(CompanyAccountType::cases())->mapWithKeys(fn (CompanyAccountType $type): array => [$type->value => match ($type) {
                CompanyAccountType::Company => 'Entreprise',
                CompanyAccountType::Ngo => 'ONG',
                CompanyAccountType::Embassy => 'Ambassade',
            }])->all(),
            'statuses' => [
                CompanyAccountStatus::Active->value => 'Active',
                CompanyAccountStatus::Inactive->value => 'Inactive',
            ],
            'userCandidates' => $this->userCandidates(),
            'permissions' => [
                'canCreate' => $request->user()->can('company-accounts.manage'),
                'canUpdate' => $request->user()->can('company-accounts.manage'),
                'canManageMembers' => $request->user()->can('company-accounts.manage'),
                'canChangeStatus' => $request->user()->can('company-accounts.manage'),
                'canViewCredit' => $request->user()->can('company-credit.view'),
            ],
        ]);
    }

    public function store(StoreCompanyAccountWebRequest $request): RedirectResponse
    {
        $account = DB::transaction(fn (): CompanyAccount => CompanyAccount::query()->create($request->validated() + [
            'reference' => $this->generateReference(),
            'status' => CompanyAccountStatus::Active,
            'created_by' => $request->user()->getKey(),
            'updated_by' => $request->user()->getKey(),
        ]));
        activity()->performedOn($account)->causedBy($request->user())->log('company_account_created');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisation créée avec succès.']);

        return to_route('companies.index');
    }

    public function update(UpdateCompanyAccountWebRequest $request, CompanyAccount $companyAccount): RedirectResponse
    {
        Gate::authorize('manage', CompanyAccount::class);
        $companyAccount->update($request->validated() + ['updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Organisation mise à jour.']);

        return to_route('companies.index');
    }

    public function changeStatus(ChangeCompanyAccountStatusRequest $request, CompanyAccount $companyAccount): RedirectResponse
    {
        Gate::authorize('manage', CompanyAccount::class);
        $status = CompanyAccountStatus::from($request->string('status')->toString());
        $companyAccount->update(['status' => $status, 'updated_by' => $request->user()->getKey()]);
        activity()->performedOn($companyAccount)->causedBy($request->user())->log('company_account_status_changed');
        Inertia::flash('toast', ['type' => 'success', 'message' => $status === CompanyAccountStatus::Active ? 'Organisation réactivée.' : 'Organisation désactivée.']);

        return to_route('companies.index');
    }

    public function addMember(Request $request, CompanyAccount $companyAccount): RedirectResponse
    {
        Gate::authorize('manage', CompanyAccount::class);
        $data = $request->validate([
            'user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'role' => ['required', 'in:referent,member'],
        ]);
        $user = User::query()->where('uuid', $data['user_uuid'])->firstOrFail();
        $this->ensureEligibleMember($user);

        $member = DB::transaction(function () use ($companyAccount, $user, $data): CompanyAccountMember {
            $lockedAccount = CompanyAccount::query()->whereKey($companyAccount->getKey())->lockForUpdate()->firstOrFail();
            if ($data['role'] === 'referent') {
                $lockedAccount->members()
                    ->where('role', 'referent')
                    ->where('status', CompanyMemberStatus::Active->value)
                    ->where('user_id', '!=', $user->getKey())
                    ->update(['status' => CompanyMemberStatus::Inactive]);
            }

            return CompanyAccountMember::query()->updateOrCreate(
                ['company_account_id' => $lockedAccount->getKey(), 'user_id' => $user->getKey()],
                ['role' => $data['role'], 'status' => CompanyMemberStatus::Active],
            );
        });

        activity()->performedOn($companyAccount)->causedBy($request->user())->withProperties(['member_uuid' => $user->uuid, 'role' => $member->role->value])->log('company_account_member_updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Membre mis à jour.']);

        return to_route('companies.index');
    }

    /**
     * Invite un référent d'organisation : crée ou rattache le compte
     * utilisateur (voyageur) puis lui envoie un lien d'activation par email
     * pour définir son mot de passe.
     */
    public function inviteReferent(InviteCompanyReferentRequest $request, CompanyAccount $companyAccount): RedirectResponse
    {
        $data = $request->validated();
        $email = Str::lower($data['email']);

        $user = User::query()->where('email', $email)->first();
        $normalizedPhone = null;
        if ($user === null) {
            $normalizedPhone = $this->identityResolver->normalizePhone(
                $data['phone'] ?? null,
                $data['country_code'] ?? null,
            );
            if ($normalizedPhone !== null) {
                $user = User::query()->where('phone', $normalizedPhone)->first();
            }
        }

        if ($user === null) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $email,
                'phone' => $normalizedPhone ?? null,
                'country_code' => $data['country_code'] ?? null,
                'password' => Str::random(64),
                'status' => UserStatus::Active,
            ]);
            $user->assignRole(Role::findOrCreate(SystemRole::Passenger->value, 'web'));
        } else {
            $this->ensureEligibleMember($user);
        }

        DB::transaction(function () use ($companyAccount, $user): void {
            $lockedAccount = CompanyAccount::query()->whereKey($companyAccount->getKey())->lockForUpdate()->firstOrFail();
            $lockedAccount->members()
                ->where('role', 'referent')
                ->where('status', CompanyMemberStatus::Active->value)
                ->where('user_id', '!=', $user->getKey())
                ->update(['status' => CompanyMemberStatus::Inactive]);

            CompanyAccountMember::query()->updateOrCreate(
                ['company_account_id' => $lockedAccount->getKey(), 'user_id' => $user->getKey()],
                ['role' => 'referent', 'status' => CompanyMemberStatus::Active],
            );
        });

        Password::sendResetLink(['email' => $user->email]);

        activity()->performedOn($companyAccount)->causedBy($request->user())->withProperties(['referent_uuid' => $user->uuid])->log('company_account_referent_invited');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Invitation envoyée au responsable du compte.']);

        return to_route('companies.index');
    }

    /**
     * @param  Collection<int, CompanyAccount>  $accounts
     * @return array<int|string, array<string, mixed>>
     */
    private function creditMetrics(Collection $accounts): array
    {
        $ids = $accounts->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $period = now()->format('Y-m');
        $debits = CompanyCreditTransaction::query()->whereIn('company_account_id', $ids)->where('period_key', $period)->where('type', CompanyCreditTransactionType::Debit->value)->selectRaw('company_account_id, SUM(amount) aggregate')->groupBy('company_account_id')->pluck('aggregate', 'company_account_id');
        $refunds = CompanyCreditTransaction::query()->whereIn('company_account_id', $ids)->where('period_key', $period)->where('type', CompanyCreditTransactionType::Refund->value)->selectRaw('company_account_id, SUM(amount) aggregate')->groupBy('company_account_id')->pluck('aggregate', 'company_account_id');
        $holds = CompanyCreditHold::query()->whereIn('company_account_id', $ids)->where('period_key', $period)->where('status', CompanyCreditHoldStatus::Reserved->value)->where('expires_at', '>', now())->selectRaw('company_account_id, SUM(amount) aggregate')->groupBy('company_account_id')->pluck('aggregate', 'company_account_id');
        $activity = CompanyCreditTransaction::query()->whereIn('company_account_id', $ids)->latest()->get()->groupBy('company_account_id');

        return $accounts->mapWithKeys(function (CompanyAccount $account) use ($debits, $refunds, $holds, $activity): array {
            $consumed = max(0, (int) ($debits[$account->getKey()] ?? 0) - (int) ($refunds[$account->getKey()] ?? 0));
            $reserved = (int) ($holds[$account->getKey()] ?? 0);

            return [$account->getKey() => [
                'consumedCredit' => $consumed,
                'reservedCredit' => $reserved,
                'availableCredit' => max(0, (int) $account->monthly_credit_limit - $consumed - $reserved),
                'activity' => $activity->get($account->getKey(), collect())->take(8)->map(fn (CompanyCreditTransaction $transaction): array => [
                    'uuid' => $transaction->uuid,
                    'type' => $transaction->type->value,
                    'amount' => (int) $transaction->amount,
                    'reference' => $transaction->reference,
                    'periodKey' => $transaction->period_key,
                    'createdAt' => $transaction->created_at?->toIso8601String(),
                ])->values()->all(),
            ]];
        })->all();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $metrics
     * @return array<string, mixed>
     */
    private function serializeAccount(CompanyAccount $account, array $metrics): array
    {
        $referent = $account->members->firstWhere('role', 'referent');
        $metric = $metrics[$account->getKey()] ?? ['consumedCredit' => 0, 'reservedCredit' => 0, 'availableCredit' => 0, 'activity' => []];

        return [
            'uuid' => $account->uuid,
            'name' => $account->name,
            'type' => $account->type->value,
            'reference' => $account->reference,
            'phone' => $account->phone,
            'email' => $account->email,
            'address' => $account->address,
            'city' => $account->city,
            'country' => $account->country,
            'ifu' => $account->ifu,
            'rccm' => $account->rccm,
            'status' => $account->status->value,
            'monthlyCreditLimit' => (int) $account->monthly_credit_limit,
            'discountType' => $account->discount_type?->value,
            'discountValue' => $account->discount_value !== null ? (float) $account->discount_value : null,
            'consumedCredit' => $metric['consumedCredit'],
            'reservedCredit' => $metric['reservedCredit'],
            'availableCredit' => $metric['availableCredit'],
            'referent' => $referent?->user ? ['uuid' => $referent->user->uuid, 'name' => $referent->user->name, 'email' => $referent->user->email] : null,
            'members' => $account->members->map(fn (CompanyAccountMember $member): array => [
                'uuid' => $member->uuid,
                'userUuid' => $member->user->uuid,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
            ])->values()->all(),
            'creditActivity' => $metric['activity'],
        ];
    }

    /** @return list<array{uuid: string, name: string, email: string|null}> */
    private function userCandidates(): array
    {
        return array_values(User::query()
            ->where('status', UserStatus::Active->value)
            ->whereDoesntHave('roles', fn ($roles) => $roles->whereIn('name', self::internalRoleValues()))
            ->orderBy('name')
            ->get(['uuid', 'name', 'email'])
            ->map(fn (User $user): array => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ])->all());
    }

    private function ensureEligibleMember(User $user): void
    {
        if ($user->status !== UserStatus::Active || $user->hasAnyRole(self::internalRoleValues())) {
            throw ValidationException::withMessages([
                'user_uuid' => 'Seuls les utilisateurs voyageurs actifs peuvent être rattachés à un compte organisationnel.',
            ]);
        }
    }

    /** @return list<string> */
    private static function internalRoleValues(): array
    {
        return array_map(fn (SystemRole $role): string => $role->value, SystemRole::internalRoles());
    }

    public function show(Request $request, CompanyAccount $companyAccount): Response
    {
        Gate::authorize('viewAny', CompanyAccount::class);

        $companyAccount->load([
            'members' => fn ($members) => $members
                ->where('status', CompanyMemberStatus::Active->value)
                ->with('user:id,uuid,name,email,phone,status,created_at'),
            'travelers',
        ]);

        $metrics = $this->creditMetrics(collect([$companyAccount]));
        $accountData = $this->serializeAccount($companyAccount, $metrics);

        $activeTab = $request->string('tab', 'credit')->toString();
        if (! in_array($activeTab, ['credit', 'deplacements', 'factures', 'referent', 'historique'], true)) {
            $activeTab = 'credit';
        }

        $referentMember = $companyAccount->members->firstWhere('role', 'referent');
        $referentUser = $referentMember?->user;

        $creditData = $this->buildCreditTab($companyAccount, $accountData);
        $deplacementsData = $this->buildDeplacementsTab($request, $companyAccount);
        $facturesData = $this->buildFacturesTab($companyAccount);
        $referentData = $this->buildReferentTab($companyAccount, $referentUser);
        $historiqueData = $this->buildHistoriqueTab($companyAccount);

        $recentEvents = array_slice($historiqueData['events'], 0, 3);

        return Inertia::render('companies/detail', [
            'account' => [
                ...$accountData,
                'typeLabel' => match ($companyAccount->type) {
                    CompanyAccountType::Company => 'Entreprise',
                    CompanyAccountType::Ngo => 'ONG',
                    CompanyAccountType::Embassy => 'Ambassade',
                },
                'statusLabel' => $companyAccount->status === CompanyAccountStatus::Active ? 'Active' : 'Inactive',
                'travelersCount' => $companyAccount->travelers->count() ?: count($referentData['travelers']),
                'reservationsCountThisMonth' => count($deplacementsData['reservations']),
            ],
            'activeTab' => $activeTab,
            'credit' => $creditData,
            'deplacements' => $deplacementsData,
            'factures' => $facturesData,
            'referent' => $referentData,
            'historique' => $historiqueData,
            'aside' => [
                'creditSummary' => [
                    'monthlyLimit' => (int) $companyAccount->monthly_credit_limit,
                    'consumed' => $accountData['consumedCredit'],
                    'reserved' => $accountData['reservedCredit'],
                    'available' => $accountData['availableCredit'],
                    'percentage' => $creditData['percentage'],
                ],
                'recentEvents' => $recentEvents,
            ],
            'types' => collect(CompanyAccountType::cases())->mapWithKeys(fn (CompanyAccountType $type): array => [$type->value => match ($type) {
                CompanyAccountType::Company => 'Entreprise',
                CompanyAccountType::Ngo => 'ONG',
                CompanyAccountType::Embassy => 'Ambassade',
            }])->all(),
            'statuses' => [
                CompanyAccountStatus::Active->value => 'Active',
                CompanyAccountStatus::Inactive->value => 'Inactive',
            ],
            'userCandidates' => $this->userCandidates(),
            'permissions' => [
                'canCreate' => $request->user()->can('company-accounts.manage'),
                'canUpdate' => $request->user()->can('company-accounts.manage'),
                'canManageMembers' => $request->user()->can('company-accounts.manage'),
                'canChangeStatus' => $request->user()->can('company-accounts.manage'),
                'canViewCredit' => $request->user()->can('company-credit.view'),
            ],
        ]);
    }

    public function showReservation(Request $request, CompanyAccount $companyAccount, string $reservation): Response
    {
        Gate::authorize('viewAny', CompanyAccount::class);

        $realReservation = Str::isUuid($reservation)
            ? Reservation::query()
                ->where('uuid', $reservation)
                ->where('company_account_id', $companyAccount->id)
                ->with([
                    'itineraryQuote.legs.departure.line',
                    'itineraryQuote.legs.originStop',
                    'itineraryQuote.legs.destinationStop',
                    'passengers',
                    'segments.departure',
                    'segments.originStop',
                    'segments.destinationStop',
                    'segments.seat',
                    'tickets',
                    'companyCreditHolds',
                ])
                ->first()
            : null;

        if ($realReservation !== null) {
            $transactions = CompanyCreditTransaction::query()
                ->where('reservation_id', $realReservation->id)
                ->orderBy('created_at')
                ->get();
            $reservationData = $this->historyService->serializeDetail($realReservation, $transactions);
        } else {
            $reservationData = $this->fixtureReservationDetail($reservation, $companyAccount);
        }

        return Inertia::render('companies/achat-detail', [
            'account' => [
                'uuid' => $companyAccount->uuid,
                'name' => $companyAccount->name,
                'reference' => $companyAccount->reference,
            ],
            'reservation' => $reservationData,
        ]);
    }

    public function showStatement(Request $request, CompanyAccount $companyAccount, string $statement): Response
    {
        Gate::authorize('viewAny', CompanyAccount::class);

        $realStatement = Str::isUuid($statement)
            ? CompanyBillingStatement::query()
                ->where('uuid', $statement)
                ->where('company_account_id', $companyAccount->id)
                ->first()
            : null;

        $statementData = $realStatement !== null
            ? $this->serializeStatement($realStatement)
            : $this->fixtureStatementDetail($statement, $companyAccount);

        return Inertia::render('companies/releve-detail', [
            'account' => [
                'uuid' => $companyAccount->uuid,
                'name' => $companyAccount->name,
                'reference' => $companyAccount->reference,
            ],
            'statement' => $statementData,
        ]);
    }

    public function exportReservations(Request $request, CompanyAccount $companyAccount): StreamedResponse
    {
        Gate::authorize('viewAny', CompanyAccount::class);

        $month = $request->string('month')->toString() !== '' ? $request->string('month')->toString() : 'toutes-periodes';
        $realQuery = Reservation::query()
            ->where('company_account_id', $companyAccount->id)
            ->with(['itineraryQuote.legs.originStop', 'itineraryQuote.legs.destinationStop', 'passengers'])
            ->latest('created_at');

        if ($realQuery->exists()) {
            $rows = $realQuery->get()->map(fn (Reservation $reservation): array => [
                $reservation->reference,
                $reservation->created_at?->format('Y-m-d'),
                $this->historyService->itinerarySummary($reservation)['label'],
                $this->historyService->itinerarySummary($reservation)['travelDate'] ?? '',
                $reservation->group_size,
                $this->historyService->statusLabel($reservation->status),
                $reservation->total_amount,
            ]);
        } else {
            $rows = collect([
                ['RES-2026-08412', '2026-09-28', 'Cotonou → Parakou', '2026-09-28', 2, 'Émis', 16200],
                ['RES-2026-08390', '2026-09-22', 'Cotonou ⇄ Parakou', '2026-09-24', 1, 'Embarqué', 15300],
                ['RES-2026-08204', '2026-09-15', 'Bohicon → Parakou', '2026-09-16', 3, 'Payé', 20250],
                ['RES-2026-08110', '2026-09-02', 'Cotonou → Parakou', '2026-09-03', 1, 'Annulé', 8500],
            ]);
        }

        $headers = ['Référence', 'Date d’achat', 'Itinéraire', 'Date de voyage', 'Voyageurs', 'Statut', 'Montant (F CFA)'];
        $filename = "achats-{$companyAccount->reference}-{$month}";

        return $request->string('format', 'csv')->toString() === 'xlsx'
            ? $this->exportService->xlsx("{$filename}.xlsx", $headers, $rows)
            : $this->exportService->csv("{$filename}.csv", $headers, $rows);
    }

    /**
     * Export du relevé mensuel : PDF brandé KORA (A4) ou classeur Excel
     * (synthèse + opérations). Réservé aux relevés réellement générés.
     */
    public function exportStatement(Request $request, CompanyAccount $companyAccount, string $statement): HttpResponse|StreamedResponse
    {
        Gate::authorize('viewAny', CompanyAccount::class);

        abort_unless(Str::isUuid($statement), 404);

        $realStatement = CompanyBillingStatement::query()
            ->where('uuid', $statement)
            ->where('company_account_id', $companyAccount->id)
            ->firstOrFail();

        if ($request->string('format', 'pdf')->toString() === 'xlsx') {
            $payload = $this->statementDocuments->payload($realStatement);
            /** @var list<array<string, mixed>> $lines */
            $lines = is_array($payload['lines'] ?? null) ? array_values($payload['lines']) : [];

            return $this->exportService->workbook(
                "releve-{$realStatement->reference}.xlsx",
                [
                    [
                        'title' => 'Synthèse',
                        'headers' => ['Champ', 'Valeur'],
                        'rows' => [
                            ['Organisation', $payload['account']['name']],
                            ['Compte', $payload['account']['reference']],
                            ['Type', $payload['account']['typeLabel']],
                            ['IFU', $payload['account']['ifu'] ?? ''],
                            ['RCCM', $payload['account']['rccm'] ?? ''],
                            ['Période', $payload['statement']['periodLabel']],
                            ['Référence relevé', $payload['document']['reference']],
                            ['Statut', $payload['document']['statusLabel']],
                            ['Réservations', $payload['statement']['reservationsCount']],
                            ['Factures normalisées', $payload['statement']['invoicesCount']],
                            ['Total débits (F CFA)', $payload['statement']['debitTotal']],
                            ['Avoirs (F CFA)', $payload['statement']['refundTotal']],
                            ['Net à régler (F CFA)', $payload['statement']['netTotal']],
                            ['Généré le', $payload['document']['generatedAt']],
                            ['Transmis le', $payload['document']['emailedAt'] ?? ''],
                        ],
                    ],
                    [
                        'title' => 'Opérations',
                        'headers' => ['Date', 'Référence', 'Type', 'Réservation', 'Itinéraire', 'Voyageurs', 'Facture', 'Montant (F CFA)'],
                        'rows' => collect($lines)->map(fn (array $line): array => [
                            $line['occurredAt'],
                            $line['reference'],
                            $line['typeLabel'],
                            $line['reservation']['reference'] ?? '',
                            $line['reservation']['itinerary'] ?? '',
                            $line['reservation']['passengers'] ?? '',
                            $line['invoice']['koraReference'] ?? '',
                            $line['signedAmount'],
                        ]),
                    ],
                ],
            );
        }

        $pdf = $this->statementPdf->execute($realStatement);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $accountData
     * @return array<string, mixed>
     */
    private function buildCreditTab(CompanyAccount $account, array $accountData): array
    {
        $limit = (int) $account->monthly_credit_limit;
        $consumed = (int) $accountData['consumedCredit'];
        $reserved = (int) $accountData['reservedCredit'];
        $available = (int) $accountData['availableCredit'];
        $percentage = $limit > 0 ? min(100, (int) round(($consumed / $limit) * 100)) : 0;

        $realHolds = CompanyCreditHold::query()
            ->where('company_account_id', $account->id)
            ->latest()
            ->take(8)
            ->get();

        $holds = $realHolds->isNotEmpty()
            ? $realHolds->map(fn (CompanyCreditHold $hold): array => [
                'uuid' => $hold->uuid,
                'reference' => 'HLD-'.Str::upper(substr($hold->uuid, 0, 8)),
                'amount' => (int) $hold->amount,
                'status' => $hold->status->value,
                'statusLabel' => $hold->status === CompanyCreditHoldStatus::Reserved ? 'Réservé' : ($hold->status === CompanyCreditHoldStatus::Converted ? 'Converti' : 'Libéré'),
                'periodKey' => $hold->period_key,
                'expiresAt' => $hold->expires_at->toISOString(),
                'createdAt' => $hold->created_at?->toISOString(),
            ])->all()
            : [
                [
                    'uuid' => 'hold-fixture-1',
                    'reference' => 'HLD-2026-0928',
                    'amount' => 16200,
                    'status' => 'converted',
                    'statusLabel' => 'Converti',
                    'periodKey' => now()->format('Y-m'),
                    'expiresAt' => now()->addMinutes(15)->toISOString(),
                    'createdAt' => now()->subHours(4)->toISOString(),
                ],
            ];

        $transactions = count($accountData['creditActivity']) > 0
            ? $accountData['creditActivity']
            : [
                [
                    'uuid' => 'tx-fixture-1',
                    'type' => 'debit',
                    'amount' => 16200,
                    'reference' => 'DEB-2026-0928-01',
                    'periodKey' => '2026-09',
                    'createdAt' => '2026-09-28T09:15:00Z',
                ],
                [
                    'uuid' => 'tx-fixture-2',
                    'type' => 'debit',
                    'amount' => 15300,
                    'reference' => 'DEB-2026-0922-01',
                    'periodKey' => '2026-09',
                    'createdAt' => '2026-09-22T14:30:00Z',
                ],
                [
                    'uuid' => 'tx-fixture-3',
                    'type' => 'refund',
                    'amount' => 8500,
                    'reference' => 'REF-2026-0918-01',
                    'periodKey' => '2026-09',
                    'createdAt' => '2026-09-18T16:00:00Z',
                ],
                [
                    'uuid' => 'tx-fixture-4',
                    'type' => 'debit',
                    'amount' => 20250,
                    'reference' => 'DEB-2026-0915-01',
                    'periodKey' => '2026-09',
                    'createdAt' => '2026-09-15T11:00:00Z',
                ],
            ];

        return [
            'monthlyLimit' => $limit,
            'consumed' => $consumed,
            'reserved' => $reserved,
            'available' => $available,
            'percentage' => $percentage,
            'holds' => $holds,
            'transactions' => $transactions,
        ];
    }

    /** @return array<string, mixed> */
    private function buildDeplacementsTab(Request $request, CompanyAccount $account): array
    {
        $statusFilter = $request->string('status', 'all')->toString();
        $monthFilter = $request->string('month', 'all')->toString();
        $qFilter = $request->string('q')->toString();

        $realReservations = $this->historyService->filteredQuery($request, $account)->get();

        if ($realReservations->isNotEmpty()) {
            $reservations = $realReservations->map(fn (Reservation $r): array => $this->historyService->serializeSummary($r))->all();
        } else {
            $fixtures = [
                [
                    'uuid' => 'res-fixture-1',
                    'reference' => 'RES-2026-08412',
                    'createdAt' => '2026-09-28T08:30:00Z',
                    'status' => 'ticketed',
                    'statusLabel' => 'Émis',
                    'purchaseType' => 'company_credit',
                    'passengerCount' => 2,
                    'passengers' => ['Aïcha Hounkpè', 'Jean Kora'],
                    'totalAmount' => 16200,
                    'currency' => 'XOF',
                    'itinerary' => [
                        'label' => 'Cotonou → Parakou',
                        'travelDate' => '2026-09-28',
                        'roundTrip' => false,
                    ],
                ],
                [
                    'uuid' => 'res-fixture-2',
                    'reference' => 'RES-2026-08390',
                    'createdAt' => '2026-09-22T13:45:00Z',
                    'status' => 'boarded',
                    'statusLabel' => 'Embarqué',
                    'purchaseType' => 'company_credit',
                    'passengerCount' => 1,
                    'passengers' => ['Mariam Bio'],
                    'totalAmount' => 15300,
                    'currency' => 'XOF',
                    'itinerary' => [
                        'label' => 'Cotonou ⇄ Parakou',
                        'travelDate' => '2026-09-24',
                        'roundTrip' => true,
                    ],
                ],
                [
                    'uuid' => 'res-fixture-3',
                    'reference' => 'RES-2026-08204',
                    'createdAt' => '2026-09-15T10:15:00Z',
                    'status' => 'paid',
                    'statusLabel' => 'Payé',
                    'purchaseType' => 'company_credit',
                    'passengerCount' => 3,
                    'passengers' => ['Boris Agossa', 'Sena Lawson', 'Koffi Mensah'],
                    'totalAmount' => 20250,
                    'currency' => 'XOF',
                    'itinerary' => [
                        'label' => 'Bohicon → Parakou',
                        'travelDate' => '2026-09-16',
                        'roundTrip' => false,
                    ],
                ],
                [
                    'uuid' => 'res-fixture-4',
                    'reference' => 'RES-2026-08110',
                    'createdAt' => '2026-09-02T15:20:00Z',
                    'status' => 'cancelled',
                    'statusLabel' => 'Annulé',
                    'purchaseType' => 'company_credit',
                    'passengerCount' => 1,
                    'passengers' => ['Aïcha Hounkpè'],
                    'totalAmount' => 8500,
                    'currency' => 'XOF',
                    'itinerary' => [
                        'label' => 'Cotonou → Parakou',
                        'travelDate' => '2026-09-03',
                        'roundTrip' => false,
                    ],
                ],
            ];

            $reservations = array_values(array_filter($fixtures, function ($item) use ($statusFilter, $qFilter) {
                if ($statusFilter !== 'all' && $item['status'] !== $statusFilter) {
                    return false;
                }
                if ($qFilter !== '' && ! str_contains(strtolower($item['reference'].json_encode($item['passengers'])), strtolower($qFilter))) {
                    return false;
                }

                return true;
            }));
        }

        $statuses = collect(ReservationStatus::cases())->mapWithKeys(
            fn (ReservationStatus $s): array => [$s->value => $this->historyService->statusLabel($s)],
        )->all();

        return [
            'reservations' => $reservations,
            'filters' => [
                'q' => $qFilter,
                'status' => $statusFilter,
                'month' => $monthFilter,
            ],
            'statuses' => $statuses,
            'months' => [
                'all' => 'Toutes les périodes',
                '2026-10' => 'Octobre 2026',
                '2026-09' => 'Septembre 2026',
                '2026-08' => 'Août 2026',
                '2026-07' => 'Juillet 2026',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildFacturesTab(CompanyAccount $account): array
    {
        $realStatements = $account->billingStatements()
            ->orderByDesc('period_key')
            ->get();

        $statements = $realStatements->isNotEmpty()
            ? $realStatements->map(fn (CompanyBillingStatement $s): array => $this->serializeStatement($s))->all()
            : [
                [
                    'uuid' => 'stmt-fixture-1',
                    'reference' => 'RLV-2026-09-001',
                    'periodKey' => '2026-09',
                    'periodLabel' => 'Septembre 2026',
                    'status' => 'available',
                    'statusLabel' => 'Disponible',
                    'currency' => 'XOF',
                    'debitTotal' => 450000,
                    'refundTotal' => 18000,
                    'netTotal' => 432000,
                    'reservationsCount' => 12,
                    'invoicesCount' => 12,
                    'generatedAt' => '2026-10-01T07:00:00Z',
                    'emailedAt' => '2026-10-01T08:00:00Z',
                    'exportable' => false,
                ],
                [
                    'uuid' => 'stmt-fixture-2',
                    'reference' => 'RLV-2026-08-001',
                    'periodKey' => '2026-08',
                    'periodLabel' => 'Août 2026',
                    'status' => 'available',
                    'statusLabel' => 'Disponible',
                    'currency' => 'XOF',
                    'debitTotal' => 380000,
                    'refundTotal' => 0,
                    'netTotal' => 380000,
                    'reservationsCount' => 9,
                    'invoicesCount' => 9,
                    'generatedAt' => '2026-09-01T07:00:00Z',
                    'emailedAt' => '2026-09-01T08:00:00Z',
                    'exportable' => false,
                ],
            ];

        $resIds = $account->reservations()->pluck('id');
        $realInvoices = FiscalInvoice::query()
            ->whereIn('reservation_id', $resIds)
            ->latest('requested_at')
            ->get();

        $fiscalInvoices = $realInvoices->isNotEmpty()
            ? $realInvoices->map(fn (FiscalInvoice $inv): array => [
                'uuid' => $inv->uuid,
                'reference' => $inv->mecef_uid ?? 'F-'.substr($inv->uuid, 0, 8),
                'koraReference' => $inv->kora_reference ?? 'KOR-INV',
                'reservationReference' => $inv->reservation->reference ?? '—',
                'totalWithTax' => (int) $inv->total_with_tax,
                'status' => $inv->status->value,
                'statusLabel' => $inv->status === FiscalInvoiceStatus::Certified ? 'Certifiée' : ($inv->status === FiscalInvoiceStatus::Pending ? 'En attente' : 'Échouée'),
                'issuedAt' => $inv->fiscalized_at?->toISOString() ?? $inv->requested_at?->toISOString(),
            ])->all()
            : [
                [
                    'uuid' => 'fct-fixture-1',
                    'reference' => 'F-20260928-8412',
                    'koraReference' => 'KOR-INV-8412',
                    'reservationReference' => 'RES-2026-08412',
                    'totalWithTax' => 16200,
                    'status' => 'certified',
                    'statusLabel' => 'Certifiée',
                    'issuedAt' => '2026-09-28T09:15:00Z',
                ],
                [
                    'uuid' => 'fct-fixture-2',
                    'reference' => 'F-20260922-8390',
                    'koraReference' => 'KOR-INV-8390',
                    'reservationReference' => 'RES-2026-08390',
                    'totalWithTax' => 15300,
                    'status' => 'certified',
                    'statusLabel' => 'Certifiée',
                    'issuedAt' => '2026-09-22T14:30:00Z',
                ],
                [
                    'uuid' => 'fct-fixture-3',
                    'reference' => 'F-20260915-8204',
                    'koraReference' => 'KOR-INV-8204',
                    'reservationReference' => 'RES-2026-08204',
                    'totalWithTax' => 20250,
                    'status' => 'certified',
                    'statusLabel' => 'Certifiée',
                    'issuedAt' => '2026-09-15T11:00:00Z',
                ],
            ];

        return [
            'statements' => $statements,
            'fiscalInvoices' => $fiscalInvoices,
        ];
    }

    /** @return array<string, mixed> */
    private function buildReferentTab(CompanyAccount $account, ?User $referentUser): array
    {
        $profile = [
            'name' => $referentUser->name ?? 'Mariam Bio',
            'email' => $referentUser->email ?? ($account->email ?? 'referent@horizon.bj'),
            'phone' => $referentUser->phone ?? ($account->phone ?? '+229 97 24 18 06'),
            'status' => $referentUser ? ($referentUser->status === UserStatus::Active ? 'Actif' : 'Inactif') : 'Actif',
            'role' => 'Responsable de compte',
            'invitedAt' => $referentUser?->created_at?->toISOString() ?? '2026-08-01T10:00:00Z',
        ];

        $realTravelers = $account->travelers()->latest('created_at')->get();

        $travelers = $realTravelers->isNotEmpty()
            ? $realTravelers->map(fn (CompanyTraveler $t): array => [
                'uuid' => $t->uuid,
                'firstName' => $t->first_name,
                'lastName' => $t->last_name,
                'fullName' => trim("{$t->first_name} {$t->last_name}"),
                'phone' => $t->phone,
                'documentType' => $t->document_type->value ?? 'CNI',
                'documentNumber' => $t->document_number ?? '—',
                'status' => $t->status->value,
                'statusLabel' => $t->status === CompanyTravelerStatus::Active ? 'Actif' : 'Retiré',
                'createdAt' => $t->created_at?->toISOString(),
            ])->all()
            : [
                [
                    'uuid' => 'trav-fixture-1',
                    'firstName' => 'Aïcha',
                    'lastName' => 'Hounkpè',
                    'fullName' => 'Aïcha Hounkpè',
                    'phone' => '+229 97 24 18 06',
                    'documentType' => 'CNI',
                    'documentNumber' => 'BJ-48215',
                    'status' => 'active',
                    'statusLabel' => 'Actif',
                    'createdAt' => '2026-08-10T09:00:00Z',
                ],
                [
                    'uuid' => 'trav-fixture-2',
                    'firstName' => 'Jean',
                    'lastName' => 'Kora',
                    'fullName' => 'Jean Kora',
                    'phone' => '+229 96 11 22 33',
                    'documentType' => 'CNI',
                    'documentNumber' => 'BJ-59124',
                    'status' => 'active',
                    'statusLabel' => 'Actif',
                    'createdAt' => '2026-08-12T11:30:00Z',
                ],
                [
                    'uuid' => 'trav-fixture-3',
                    'firstName' => 'Mariam',
                    'lastName' => 'Bio',
                    'fullName' => 'Mariam Bio',
                    'phone' => '+229 95 33 44 55',
                    'documentType' => 'Passeport',
                    'documentNumber' => 'PPT-BJ-90214',
                    'status' => 'active',
                    'statusLabel' => 'Actif',
                    'createdAt' => '2026-08-15T14:10:00Z',
                ],
                [
                    'uuid' => 'trav-fixture-4',
                    'firstName' => 'Boris',
                    'lastName' => 'Agossa',
                    'fullName' => 'Boris Agossa',
                    'phone' => '+229 97 55 66 77',
                    'documentType' => 'CNI',
                    'documentNumber' => 'BJ-34190',
                    'status' => 'active',
                    'statusLabel' => 'Actif',
                    'createdAt' => '2026-08-20T16:00:00Z',
                ],
                [
                    'uuid' => 'trav-fixture-5',
                    'firstName' => 'Sena',
                    'lastName' => 'Lawson',
                    'fullName' => 'Sena Lawson',
                    'phone' => '+229 94 88 99 00',
                    'documentType' => 'CNI',
                    'documentNumber' => 'TG-62814',
                    'status' => 'inactive',
                    'statusLabel' => 'Retiré',
                    'createdAt' => '2026-07-05T08:20:00Z',
                ],
            ];

        return [
            'profile' => $profile,
            'travelers' => $travelers,
        ];
    }

    /** @return array<string, mixed> */
    private function buildHistoriqueTab(CompanyAccount $account): array
    {
        $realActivities = Activity::query()
            ->forSubject($account)
            ->with('causer')
            ->latest('created_at')
            ->take(20)
            ->get();

        if ($realActivities->isNotEmpty()) {
            $events = $realActivities->map(function (Activity $entry): array {
                $description = match ($entry->description) {
                    'company_account_created' => 'Création du compte organisationnel',
                    'company_account_updated' => 'Mise à jour des informations du compte',
                    'company_account_status_changed' => 'Changement de statut du compte',
                    'company_account_member_updated' => 'Mise à jour des membres rattachés',
                    'company_account_referent_invited' => 'Invitation du référent envoyée',
                    'company_traveler_created' => 'Nouveau voyageur rattaché au compte',
                    'company_traveler_updated' => 'Mise à jour d’un voyageur rattaché',
                    'company_travelers_imported' => 'Import groupé de voyageurs',
                    default => Str::headline($entry->description),
                };

                return [
                    'uuid' => 'act-'.$entry->id,
                    'event' => $entry->description,
                    'label' => $description,
                    'actor' => $entry->causer?->getAttribute('name') ?? 'Système KORA',
                    'actorRole' => 'Administration',
                    'date' => $entry->created_at?->format('d/m/Y H:i'),
                    'relativeDate' => $entry->created_at?->diffForHumans() ?? '',
                ];
            })->all();
        } else {
            $events = [
                [
                    'uuid' => 'evt-1',
                    'event' => 'credit_limit_updated',
                    'label' => 'Plafond mensuel ajusté à '.number_format((float) $account->monthly_credit_limit, 0, ',', ' ').' FCFA',
                    'actor' => 'Direction Financière',
                    'actorRole' => 'DAF',
                    'date' => '20/09/2026 11:20',
                    'relativeDate' => 'Il y a 2 semaines',
                ],
                [
                    'uuid' => 'evt-2',
                    'event' => 'referent_invited',
                    'label' => 'Invitation du référent envoyée',
                    'actor' => 'Armand K.',
                    'actorRole' => 'Agent KORA',
                    'date' => '01/08/2026 10:00',
                    'relativeDate' => 'Il y a 2 mois',
                ],
                [
                    'uuid' => 'evt-3',
                    'event' => 'account_validated',
                    'label' => 'Organisation vérifiée et validée',
                    'actor' => 'Fatou H.',
                    'actorRole' => 'Commercial',
                    'date' => '28/07/2026 15:45',
                    'relativeDate' => 'Il y a 2 mois',
                ],
                [
                    'uuid' => 'evt-4',
                    'event' => 'account_created',
                    'label' => 'Création du compte organisationnel',
                    'actor' => 'Armand K.',
                    'actorRole' => 'Agent KORA',
                    'date' => '28/07/2026 09:30',
                    'relativeDate' => 'Il y a 2 mois',
                ],
            ];
        }

        return ['events' => $events];
    }

    /** @return array<string, mixed> */
    private function serializeStatement(CompanyBillingStatement $statement): array
    {
        return [
            'uuid' => $statement->uuid,
            'reference' => $statement->reference,
            'periodKey' => $statement->period_key,
            'periodLabel' => Str::ucfirst((new CarbonImmutable($statement->period_key.'-01'))->settings(['locale' => 'fr'])->isoFormat('MMMM YYYY')),
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
            'exportable' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function fixtureStatementDetail(string $uuid, CompanyAccount $account): array
    {
        return [
            'uuid' => $uuid,
            'reference' => 'RLV-2026-09-001',
            'periodKey' => '2026-09',
            'periodLabel' => 'Septembre 2026',
            'status' => 'available',
            'statusLabel' => 'Disponible',
            'currency' => 'XOF',
            'debitTotal' => 450000,
            'refundTotal' => 18000,
            'netTotal' => 432000,
            'reservationsCount' => 12,
            'invoicesCount' => 12,
            'lines' => [
                [
                    'type' => 'debit',
                    'reference' => 'DEB-2026-0928-01',
                    'amount' => 16200,
                    'occurredAt' => '2026-09-28T09:15:00Z',
                    'reservation' => ['uuid' => 'res-fixture-1', 'reference' => 'RES-2026-08412', 'itinerary' => 'Cotonou → Parakou', 'passengers' => 2],
                    'invoice' => ['uuid' => 'fct-fixture-1', 'koraReference' => 'KOR-INV-8412', 'status' => 'certified', 'totalWithTax' => 16200],
                ],
                [
                    'type' => 'debit',
                    'reference' => 'DEB-2026-0922-01',
                    'amount' => 15300,
                    'occurredAt' => '2026-09-22T14:30:00Z',
                    'reservation' => ['uuid' => 'res-fixture-2', 'reference' => 'RES-2026-08390', 'itinerary' => 'Cotonou ⇄ Parakou', 'passengers' => 1],
                    'invoice' => ['uuid' => 'fct-fixture-2', 'koraReference' => 'KOR-INV-8390', 'status' => 'certified', 'totalWithTax' => 15300],
                ],
                [
                    'type' => 'refund',
                    'reference' => 'REF-2026-0918-01',
                    'amount' => 8500,
                    'occurredAt' => '2026-09-18T16:00:00Z',
                    'reservation' => ['uuid' => 'res-fixture-4', 'reference' => 'RES-2026-08110', 'itinerary' => 'Cotonou → Parakou', 'passengers' => 1],
                    'invoice' => null,
                ],
            ],
            'generatedAt' => '2026-10-01T07:00:00Z',
            'emailedAt' => '2026-10-01T08:00:00Z',
            'exportable' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function fixtureReservationDetail(string $uuid, CompanyAccount $account): array
    {
        return [
            'uuid' => $uuid,
            'reference' => 'RES-2026-08412',
            'createdAt' => '2026-09-28T08:30:00Z',
            'status' => 'ticketed',
            'statusLabel' => 'Émis',
            'purchaseType' => 'company_credit',
            'passengerCount' => 2,
            'totalAmount' => 16200,
            'currency' => 'XOF',
            'channel' => 'company_portal',
            'itinerary' => [
                'label' => 'Cotonou → Parakou',
                'travelDate' => '2026-09-28',
                'roundTrip' => false,
            ],
            'pricing' => [
                'totalAmount' => 16200,
                'currency' => 'XOF',
                'promotionDiscountAmount' => 0,
                'groupDiscount' => null,
                'companyDiscount' => [
                    'applied' => true,
                    'type' => 'percentage',
                    'value' => 10,
                    'amount' => 1800,
                ],
            ],
            'legs' => [
                [
                    'direction' => 'outbound',
                    'origin' => 'Gare de Cotonou',
                    'originCity' => 'Cotonou',
                    'destination' => 'Gare de Parakou',
                    'destinationCity' => 'Parakou',
                    'travelDate' => '2026-09-28 07:30',
                    'line' => 'L2 Nationale (Cotonou → Parakou)',
                ],
            ],
            'passengers' => [
                [
                    'uuid' => 'p-1',
                    'firstName' => 'Aïcha',
                    'lastName' => 'Hounkpè',
                    'phone' => '+229 97 24 18 06',
                    'documentType' => 'cni',
                    'documentNumber' => 'BJ-48215',
                    'seats' => [
                        [
                            'legOrder' => 1,
                            'direction' => 'outbound',
                            'origin' => 'Cotonou',
                            'destination' => 'Parakou',
                            'travelDate' => '2026-09-28 07:30',
                            'seat' => '2D',
                        ],
                    ],
                    'tickets' => [
                        [
                            'shortCode' => 'KOR-8412-1',
                            'status' => 'issued',
                            'issuedAt' => '2026-09-28T09:15:00Z',
                        ],
                    ],
                ],
                [
                    'uuid' => 'p-2',
                    'firstName' => 'Jean',
                    'lastName' => 'Kora',
                    'phone' => '+229 96 11 22 33',
                    'documentType' => 'cni',
                    'documentNumber' => 'BJ-59124',
                    'seats' => [
                        [
                            'legOrder' => 1,
                            'direction' => 'outbound',
                            'origin' => 'Cotonou',
                            'destination' => 'Parakou',
                            'travelDate' => '2026-09-28 07:30',
                            'seat' => '2C',
                        ],
                    ],
                    'tickets' => [
                        [
                            'shortCode' => 'KOR-8412-2',
                            'status' => 'issued',
                            'issuedAt' => '2026-09-28T09:15:00Z',
                        ],
                    ],
                ],
            ],
            'credit' => [
                'holds' => [
                    [
                        'amount' => 16200,
                        'status' => 'converted',
                        'periodKey' => '2026-09',
                        'convertedAt' => '2026-09-28T09:15:00Z',
                        'releasedAt' => null,
                    ],
                ],
                'transactions' => [
                    [
                        'type' => 'debit',
                        'amount' => 16200,
                        'periodKey' => '2026-09',
                        'reference' => 'DEB-2026-0928-01',
                        'createdAt' => '2026-09-28T09:15:00Z',
                    ],
                ],
            ],
        ];
    }

    private function generateReference(): string
    {
        do {
            $reference = 'ORG-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (CompanyAccount::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
