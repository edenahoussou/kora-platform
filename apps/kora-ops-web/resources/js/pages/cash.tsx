import { Head, router, useForm, usePoll } from '@inertiajs/react';
import {
    ArrowUpRight,
    Banknote,
    ChevronRight,
    CircleAlert,
    Download,
    FileCheck2,
    Search,
    Plus,
    Smartphone,
    SlidersHorizontal,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CameraCapture } from '@/components/cash/camera-capture';
import { CashOpeningDialog } from '@/components/cash/cash-opening-dialog';
import {
    FilterSelect,
    OperationsTable,
    Pagination,
    StatusPill,
    formatDate,
    formatTime,
    networkBrands,
    networkLabels,
    statusLabels,
} from '@/components/cash/cash-shared';
import type {
    CashOperationRow,
    CashSessionLedger,
} from '@/components/cash/cash-shared';
import { OperationDetailDrawer } from '@/components/cash/operation-detail-drawer';
import type { CashSessionDetail } from '@/components/cash/operation-detail-drawer';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type {
    SaleCashSession,
    SaleMobileMoneyAccount,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import { cash } from '@/routes';
import { exportMethod as exportCash } from '@/routes/cash';
import { approveVariance, close as closeCash } from '@/routes/cash';
import { store } from '@/routes/cash/movements';

type CashSessionPage = Omit<SaleCashSession, 'ledgers'> & {
    status: string;
    ledgers: CashSessionLedger[];
    openedAt?: string | null;
    submittedAt?: string | null;
    closedAt?: string | null;
    approvedAt?: string | null;
    approvedByName?: string | null;
    forceClosedAt?: string | null;
    forceClosedByName?: string | null;
    closingComment?: string | null;
    agentName?: string | null;
    agencyName?: string | null;
    cashRegister?: { uuid: string; code: string; label: string } | null;
    isStale?: boolean;
    isLateClosing?: boolean;
    isForced?: boolean;
};
type CashFilters = {
    q: string;
    channel: string;
    status: string;
    direction: string;
    nature: string;
    from: string;
    to: string;
    agent: string;
    session_uuid: string;
};
type AgencyBalances = {
    cash: number;
    mobileMoney: number;
    global: number;
    registers: {
        uuid: string;
        code: string;
        label: string;
        kind: string;
        published: number;
        reserved: number;
        available: number;
    }[];
    accounts: {
        uuid: string;
        network: string;
        label: string;
        status: string;
        accountIdentifier: string;
        balance: number;
    }[];
};
type Props = {
    operations: {
        data: CashOperationRow[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    filters: CashFilters;
    filterOptions?: {
        agents: { uuid: string; name: string }[];
    };
    cashSession: CashSessionPage | null;
    sessionDetail?: CashSessionDetail | null;
    mobileMoneyAccounts: SaleMobileMoneyAccount[];
    cashRegisters: { uuid: string; code: string; label: string }[];
    agencyBalances: AgencyBalances | null;
    permissions: {
        canOpen: boolean;
        canClose: boolean;
        canApproveVariance: boolean;
        canCreateMovement: boolean;
        canApproveMovement: boolean;
        canViewAgency: boolean;
    };
};

export default function CashPage({
    cashSession,
    sessionDetail,
    operations,
    filters,
    filterOptions,
    mobileMoneyAccounts,
    cashRegisters,
    agencyBalances,
    permissions,
}: Props) {
    const [openingOpen, setOpeningOpen] = useState(false);
    const [closingOpen, setClosingOpen] = useState(false);
    const [movementOpen, setMovementOpen] = useState(false);
    const [selectedOperation, setSelectedOperation] =
        useState<CashOperationRow | null>(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [isLoadingSession, setIsLoadingSession] = useState(false);
    const [query, setQuery] = useState(filters.q);
    const channel = filters.channel;
    const status = filters.status;
    const direction = filters.direction;
    const { start, stop } = usePoll(
        30000,
        {
            only: [
                'cashSession',
                'operations',
                'sessionDetail',
                'permissions',
                'mobileMoneyAccounts',
                'agencyBalances',
            ],
        },
        { autoStart: false },
    );

    useEffect(() => {
        const updatePolling = () => (document.hidden ? stop() : start());
        updatePolling();
        document.addEventListener('visibilitychange', updatePolling);

        return () => {
            stop();
            document.removeEventListener('visibilitychange', updatePolling);
        };
    }, [start, stop]);

    const visit = (next: Partial<CashFilters>, page = 1) => {
        router.get(
            cash.url(),
            { ...filters, q: query, ...next, page },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };
    const resetFilters = () => {
        setQuery('');
        visit({
            q: '',
            channel: 'all',
            status: 'all',
            direction: 'all',
            nature: 'all',
            from: '',
            to: '',
            agent: '',
            session_uuid: '',
        });
    };

    const handleSelectOperation = (op: CashOperationRow) => {
        setSelectedOperation(op);
        setDrawerOpen(true);

        if (!sessionDetail || sessionDetail.uuid !== op.sessionUuid) {
            router.get(
                cash.url(),
                {
                    ...filters,
                    q: query,
                    session_detail: op.sessionUuid,
                },
                {
                    only: ['sessionDetail'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onStart: () => setIsLoadingSession(true),
                    onFinish: () => setIsLoadingSession(false),
                },
            );
        }
    };

    // Rafraîchit la ligne ouverte dans le drawer quand le journal se recharge.
    const liveOperation =
        operations.data.find((op) => op.key === selectedOperation?.key) ??
        selectedOperation;

    const session = cashSession;
    const ledgerCards = [...(session?.ledgers ?? [])].sort((first, second) =>
        first.kind === 'cash' ? -1 : second.kind === 'cash' ? 1 : 0,
    );
    const totalExpected = ledgerCards.reduce(
        (total, ledger) => total + ledger.expectedBalance,
        0,
    );

    const unifiedCards = buildUnifiedCards(session, agencyBalances);

    return (
        <>
            <Head title="Caisse journalière" />
            <div className="cash-page">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Finance / Caisse
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Caisse journalière
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Suivez les encaissements, consultez le journal
                            d’opérations et préparez la clôture.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        {!session && permissions.canOpen && (
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() => setOpeningOpen(true)}
                            >
                                <Plus className="size-4" /> Ouvrir la caisse
                            </button>
                        )}
                        {session?.status === 'open' && permissions.canClose && (
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() => setClosingOpen(true)}
                            >
                                <FileCheck2 className="size-4" /> Clôturer la
                                caisse
                            </button>
                        )}
                    </div>
                </div>

                {session?.isStale && (
                    <div className="cash-reveal mb-5 flex items-start gap-3 rounded-[var(--kora-radius)] border border-amber-500/30 bg-amber-500/10 p-4">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                        <div className="text-xs leading-5 text-foreground">
                            <strong>
                                Session du {formatDate(session.businessDate)}{' '}
                                non clôturée.
                            </strong>
                            <br />
                            Clôturez cette session pour débloquer la caisse
                            avant d'ouvrir la journée en cours.
                        </div>
                    </div>
                )}

                <section className="cash-reveal cash-reveal-delay-1 cash-hero">
                    <div>
                        <span className="section-kicker text-gold">
                            {session
                                ? 'Ma caisse journalière'
                                : 'Trésorerie de l’agence'}
                        </span>
                        <h2>
                            {!session
                                ? agencyBalances
                                    ? 'Situation financière permanente'
                                    : 'Aucune caisse ouverte'
                                : session.isStale
                                  ? `Session du ${formatDate(session.businessDate)} encore ouverte`
                                  : session.status === 'open'
                                    ? `Caisse ouverte depuis ${formatTime(session.openedAt)}`
                                    : `Caisse ${statusLabels[session.status]?.toLowerCase() ?? session.status}`}
                        </h2>
                        <p>
                            {!session
                                ? agencyBalances
                                    ? `Total permanent consolidé : ${agencyBalances.registers.length} caisse(s) et ${agencyBalances.accounts.length} compte(s) Mobile Money.`
                                    : 'Ouvrez votre caisse pour enregistrer les opérations de la journée.'
                                : `${session.agentName ?? ''} · ${session.agencyName ?? ''}. Total attendu calculé à partir des sous-journaux du shift.`}
                        </p>
                    </div>
                    <div className="cash-hero-current">
                        <span>
                            {session
                                ? 'Solde session attendu'
                                : 'Trésorerie globale agence'}
                        </span>
                        <strong>
                            {session
                                ? formatMoney(totalExpected, 'XOF')
                                : agencyBalances
                                  ? formatMoney(agencyBalances.global, 'XOF')
                                  : '—'}
                        </strong>
                    </div>
                    {session ? (
                        <StatusPill status={session.status} />
                    ) : (
                        <KoraStatusBadge
                            label="Trésorerie agence"
                            tone="navy"
                        />
                    )}
                </section>

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-2">
                    {unifiedCards.map((card) => (
                        <UnifiedBalanceCard key={card.key} card={card} />
                    ))}
                </section>

                <div className="cash-reveal cash-reveal-delay-3 cash-section-intro">
                    <div>
                        <span className="section-kicker">
                            Journal des opérations
                        </span>
                        <h2>Historique & mouvements</h2>
                    </div>
                    <div className="actions">
                        {permissions.canCreateMovement &&
                            session &&
                            session.status === 'open' && (
                                <button
                                    type="button"
                                    className="btn btn-ghost"
                                    onClick={() => setMovementOpen(true)}
                                >
                                    <ArrowUpRight className="size-4" />{' '}
                                    Reversement
                                </button>
                            )}
                        <a
                            className="btn btn-ghost"
                            href={exportCash.url({ query: filters })}
                        >
                            <Download className="size-4" /> Exporter
                        </a>
                    </div>
                </div>

                <div className="cash-filterbar cash-reveal cash-reveal-delay-3">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                        }}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                visit({});
                            }
                        }}
                        aria-label="Rechercher une opération"
                        placeholder="Rechercher une opération, une référence…"
                        className="cash-filter-search"
                    />
                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Du</span>
                        <input
                            type="date"
                            value={filters.from}
                            onChange={(event) =>
                                visit({ from: event.target.value })
                            }
                            aria-label="Date de début"
                            className="cash-filter-select h-11 w-full rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                        />
                    </label>
                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Au</span>
                        <input
                            type="date"
                            value={filters.to}
                            onChange={(event) =>
                                visit({ to: event.target.value })
                            }
                            aria-label="Date de fin"
                            className="cash-filter-select h-11 w-full rounded-xl bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                        />
                    </label>
                    {permissions.canViewAgency &&
                        Boolean(filterOptions?.agents?.length) && (
                            <FilterSelect
                                label="Agent"
                                value={filters.agent}
                                onChange={(value) => visit({ agent: value })}
                                options={[
                                    ['', 'Tous les agents'],
                                    ...(filterOptions?.agents ?? []).map(
                                        (agent) => [agent.uuid, agent.name],
                                    ),
                                ]}
                            />
                        )}
                    <FilterSelect
                        label="Canal"
                        value={channel}
                        onChange={(value) => {
                            visit({ channel: value });
                        }}
                        options={[
                            ['all', 'Tous les canaux'],
                            ['cash', 'Espèces'],
                            ['mtn_momo', 'MTN MoMo'],
                            ['moov_flooz', 'Moov Money / Flooz'],
                            ['celtiis_cash', 'Celtiis Cash'],
                        ]}
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={(value) => {
                            visit({ status: value });
                        }}
                        options={[
                            ['all', 'Tous les statuts'],
                            ['posted', 'Publié'],
                            ['approved', 'Approuvé'],
                            ['pending', 'En attente'],
                            ['rejected', 'Rejeté'],
                            ['reversed', 'Contrepassé'],
                        ]}
                    />
                    <FilterSelect
                        label="Sens"
                        value={direction}
                        onChange={(value) => {
                            visit({ direction: value });
                        }}
                        options={[
                            ['all', 'Tous les sens'],
                            ['in', 'Encaissements'],
                            ['out', 'Sorties'],
                        ]}
                    />
                    <FilterSelect
                        label="Nature"
                        value={filters.nature}
                        onChange={(value) => {
                            visit({ nature: value });
                        }}
                        options={[
                            ['all', 'Toutes les lignes'],
                            ['movements', 'Mouvements'],
                            ['lifecycle', 'Ouvertures & clôtures'],
                        ]}
                    />
                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={() => visit({})}
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={resetFilters}
                    >
                        Réinitialiser
                    </button>
                </div>

                <section className="cash-table-card cash-reveal cash-reveal-delay-4">
                    <OperationsTable
                        operations={operations.data}
                        showAgent={permissions.canViewAgency}
                        onSelect={handleSelectOperation}
                    />
                    <Pagination
                        currentPage={operations.current_page}
                        totalPages={operations.last_page}
                        total={operations.total}
                        pageSize={operations.per_page}
                        onPageChange={(page) => visit(filters, page)}
                    />
                </section>

                {session?.status === 'pending_approval' && (
                    <div className="cash-reveal cash-reveal-delay-4 mt-5">
                        <PendingActions
                            session={session}
                            canApproveVariance={permissions.canApproveVariance}
                        />
                    </div>
                )}
            </div>
            {openingOpen && (
                <CashOpeningDialog
                    accounts={mobileMoneyAccounts}
                    cashRegisters={cashRegisters}
                    onClose={() => setOpeningOpen(false)}
                />
            )}
            {closingOpen && cashSession && (
                <CloseDialog
                    session={cashSession}
                    onClose={() => setClosingOpen(false)}
                />
            )}
            {movementOpen && cashSession && (
                <MovementDialog
                    session={cashSession}
                    onClose={() => setMovementOpen(false)}
                />
            )}
            <OperationDetailDrawer
                open={drawerOpen}
                onOpenChange={setDrawerOpen}
                operation={liveOperation}
                sessionDetail={sessionDetail ?? null}
                isLoadingSession={isLoadingSession}
                canApproveMovement={permissions.canApproveMovement}
                filters={filters}
            />
        </>
    );
}

type UnifiedCardData = {
    key: string;
    kind: 'cash' | 'mobile_money';
    title: string;
    subtitle: string;
    brand: { key: string; mark: string };
    balance: number;
    balanceLabel: string;
    sessionLedger?: CashSessionLedger | null;
    registerData?: {
        kind: string;
        available: number;
        published: number;
        reserved: number;
    } | null;
};

function buildUnifiedCards(
    session: CashSessionPage | null,
    balances: AgencyBalances | null,
): UnifiedCardData[] {
    const cards: UnifiedCardData[] = [];

    if (balances) {
        // 1. Physical registers
        for (const register of balances.registers) {
            const isSessionRegister =
                session?.cashRegister?.uuid === register.uuid ||
                (!session?.cashRegister && register.kind === 'counter');
            const matchingLedger = isSessionRegister
                ? (session?.ledgers.find((ledger) => ledger.kind === 'cash') ??
                  null)
                : null;

            cards.push({
                key: `reg-${register.uuid}`,
                kind: 'cash',
                title:
                    register.kind === 'vault'
                        ? 'Coffre-fort'
                        : 'Caisse Espèces',
                subtitle: `${register.code} · ${register.label}`,
                brand: { key: 'cash', mark: '₣' },
                balance: matchingLedger
                    ? matchingLedger.expectedBalance
                    : register.available,
                balanceLabel: matchingLedger
                    ? 'Solde session attendu'
                    : 'Disponible en caisse',
                sessionLedger: matchingLedger,
                registerData: register,
            });
        }

        // 2. Mobile Money accounts
        for (const account of balances.accounts) {
            const matchingLedger =
                session?.ledgers.find(
                    (ledger) => ledger.account?.uuid === account.uuid,
                ) ?? null;

            cards.push({
                key: `acc-${account.uuid}`,
                kind: 'mobile_money',
                title: networkLabels[account.network] ?? 'Mobile Money',
                subtitle: `${account.label} · ${account.accountIdentifier}`,
                brand: networkBrands[account.network] ?? {
                    key: 'default',
                    mark: 'MM',
                },
                balance: matchingLedger
                    ? matchingLedger.expectedBalance
                    : account.balance,
                balanceLabel: matchingLedger
                    ? 'Solde session attendu'
                    : 'Solde disponible',
                sessionLedger: matchingLedger,
                registerData: null,
            });
        }
    } else if (session) {
        for (const ledger of session.ledgers) {
            const isCash = ledger.kind === 'cash';
            cards.push({
                key: `led-${ledger.uuid}`,
                kind: isCash ? 'cash' : 'mobile_money',
                title: isCash
                    ? 'Espèces'
                    : (networkLabels[ledger.account?.network ?? ''] ??
                      'Mobile Money'),
                subtitle: isCash
                    ? 'Caisse physique'
                    : (ledger.account?.accountIdentifier ??
                      ledger.account?.label ??
                      ''),
                brand: isCash
                    ? { key: 'cash', mark: '₣' }
                    : (networkBrands[ledger.account?.network ?? ''] ?? {
                          key: 'default',
                          mark: 'MM',
                      }),
                balance: ledger.expectedBalance,
                balanceLabel: 'Solde session attendu',
                sessionLedger: ledger,
                registerData: null,
            });
        }
    }

    return cards;
}

function UnifiedBalanceCard({ card }: { card: UnifiedCardData }) {
    const {
        kind,
        title,
        subtitle,
        brand,
        balance,
        balanceLabel,
        sessionLedger,
        registerData,
    } = card;
    const isCash = kind === 'cash';

    return (
        <article
            className={cn('cash-ledger-card', `cash-ledger-card-${brand.key}`)}
        >
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className="cash-ledger-brand">{brand.mark}</span>
                    <div>
                        <span className="cash-ledger-title">{title}</span>
                        <small>{subtitle}</small>
                    </div>
                </div>
                {sessionLedger ? (
                    <StatusPill status={sessionLedger.status} />
                ) : (
                    <KoraStatusBadge label="Trésorerie" tone="navy" />
                )}
            </div>

            <div className="cash-ledger-balance">
                <span>{balanceLabel}</span>
                <strong>{formatMoney(balance, 'XOF')}</strong>
            </div>

            {sessionLedger ? (
                <div className="cash-ledger-breakdown">
                    <div>
                        <span>Ouverture</span>
                        <strong>
                            {formatMoney(sessionLedger.openingBalance, 'XOF')}
                        </strong>
                    </div>
                    <div>
                        <span>Entrées</span>
                        <strong className="is-positive">
                            +{formatMoney(sessionLedger.inflows, 'XOF')}
                        </strong>
                    </div>
                    <div>
                        <span>Sorties</span>
                        <strong className="is-negative">
                            −{formatMoney(sessionLedger.outflows, 'XOF')}
                        </strong>
                    </div>
                </div>
            ) : registerData ? (
                <div className="cash-ledger-breakdown">
                    <div>
                        <span>Théorique</span>
                        <strong>
                            {formatMoney(registerData.published, 'XOF')}
                        </strong>
                    </div>
                    <div>
                        <span>Réservé</span>
                        <strong>
                            {formatMoney(registerData.reserved, 'XOF')}
                        </strong>
                    </div>
                    <div>
                        <span>Disponible</span>
                        <strong>
                            {formatMoney(registerData.available, 'XOF')}
                        </strong>
                    </div>
                </div>
            ) : (
                <div className="cash-ledger-breakdown">
                    <div>
                        <span>Type</span>
                        <strong>Compte MoMo</strong>
                    </div>
                    <div>
                        <span>Solde réel</span>
                        <strong>{formatMoney(balance, 'XOF')}</strong>
                    </div>
                    <div>
                        <span>Statut</span>
                        <strong className="is-positive">Actif</strong>
                    </div>
                </div>
            )}

            <div className="cash-ledger-foot">
                {isCash ? (
                    <Banknote className="size-3.5" strokeWidth={1.7} />
                ) : (
                    <Smartphone className="size-3.5" strokeWidth={1.7} />
                )}
                <span>
                    {sessionLedger
                        ? sessionLedger.movementCount === 0
                            ? 'Aucun mouvement sur ce shift'
                            : `${sessionLedger.movementCount} mouvement${sessionLedger.movementCount > 1 ? 's' : ''} enregistré${sessionLedger.movementCount > 1 ? 's' : ''}`
                        : registerData
                          ? registerData.kind === 'vault'
                              ? 'Coffre-fort agence'
                              : 'Guichet opérationnel'
                          : 'Compte actif permanent'}
                </span>
                {sessionLedger?.actualBalance !== null &&
                    sessionLedger?.actualBalance !== undefined && (
                        <b>
                            Constaté{' '}
                            {formatMoney(sessionLedger.actualBalance, 'XOF')}
                        </b>
                    )}
            </div>
        </article>
    );
}

function PendingActions({
    session,
    canApproveVariance,
}: {
    session: CashSessionPage;
    canApproveVariance: boolean;
}) {
    const needsApproval = session.status === 'pending_approval';

    return (
        <section className="kora-panel rounded-[var(--kora-radius)] border border-border bg-card p-5 shadow-[0_8px_30px_rgba(0,29,78,0.045)]">
            <div className="flex items-center gap-2">
                <CircleAlert
                    className={cn(
                        'size-4',
                        needsApproval ? 'text-gold' : 'text-emerald-600',
                    )}
                />
                <h2 className="text-sm font-extrabold text-foreground">
                    À surveiller
                </h2>
            </div>
            <div className="mt-4 space-y-3">
                {needsApproval ? (
                    <div className="rounded-xl bg-gold/10 p-3 text-xs leading-5 text-foreground">
                        <strong>Écart à approuver.</strong>
                        <br />
                        La clôture restera en attente jusqu’à la validation d’un
                        responsable.
                    </div>
                ) : (
                    <div className="rounded-xl bg-emerald-500/10 p-3 text-xs leading-5 text-foreground">
                        <strong>Caisse équilibrée.</strong>
                        <br />
                        Aucune action prioritaire pour cette session.
                    </div>
                )}
                {needsApproval && canApproveVariance && (
                    <button
                        type="button"
                        className="btn btn-primary w-full"
                        onClick={() =>
                            router.post(approveVariance.url(session.uuid!))
                        }
                    >
                        Approuver l’écart <ChevronRight className="size-4" />
                    </button>
                )}
            </div>
        </section>
    );
}

function CloseDialog({
    session,
    onClose,
}: {
    session: CashSessionPage;
    onClose: () => void;
}) {
    const form = useForm<{
        ledgers: Array<{
            uuid: string;
            actual_balance: string;
            closing_comment: string;
            evidence: File | null;
            evidence_captured_at: string | null;
            evidence_preview_url: string | null;
        }>;
    }>({
        ledgers: session.ledgers.map((ledger) => ({
            uuid: ledger.uuid,
            actual_balance: String(ledger.expectedBalance),
            closing_comment: '',
            evidence: null,
            evidence_captured_at: null,
            evidence_preview_url: null,
        })),
    });
    // Business-rule errors like `cash_session` aren't tied to any form
    // field, so they fall outside the strict FormDataErrors<T> typing;
    // read them via a loosely-typed view.
    const errors = form.errors as Record<string, string | undefined>;
    const valid = form.data.ledgers.every(
        (ledger) =>
            Number.isInteger(Number(ledger.actual_balance)) &&
            Number(ledger.actual_balance) >= 0 &&
            ledger.evidence !== null &&
            ledger.evidence_captured_at !== null,
    );
    const update = (
        index: number,
        changes: Partial<(typeof form.data.ledgers)[number]>,
    ) =>
        form.setData(
            'ledgers',
            form.data.ledgers.map((ledger, ledgerIndex) =>
                ledgerIndex === index ? { ...ledger, ...changes } : ledger,
            ),
        );
    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!valid || form.processing) {
            return;
        }

        form.transform((data) => ({
            ledgers: data.ledgers.map((ledger) => ({
                uuid: ledger.uuid,
                actual_balance: ledger.actual_balance,
                closing_comment: ledger.closing_comment,
                evidence: ledger.evidence,
                evidence_captured_at: ledger.evidence_captured_at,
            })),
        }));

        form.post(closeCash.url(session.uuid!), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal cash-opening-modal"
                role="dialog"
                aria-modal="true"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Fin de journée</span>
                        <h3>Clôturer la caisse</h3>
                        <p>
                            Déclarez le solde constaté pour chaque sous-journal.
                            Une différence sera soumise à approbation.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="cash-opening-list">
                    {form.data.ledgers.map((ledger, index) => {
                        const source = session.ledgers.find(
                            (item) => item.uuid === ledger.uuid,
                        );

                        return (
                            <div className="cash-opening-row" key={ledger.uuid}>
                                <div className="cash-opening-row-head">
                                    <span className="cash-opening-icon">
                                        {source?.kind === 'cash' ? (
                                            <Banknote className="size-4" />
                                        ) : (
                                            <Smartphone className="size-4" />
                                        )}
                                    </span>
                                    <div>
                                        <strong>
                                            {source?.kind === 'cash'
                                                ? 'Espèces'
                                                : source?.account?.label}
                                        </strong>
                                        <small>
                                            Attendu ·{' '}
                                            {formatMoney(
                                                source?.expectedBalance ?? 0,
                                                'XOF',
                                            )}
                                        </small>
                                    </div>
                                </div>
                                <div className="cash-opening-fields">
                                    <label className="field">
                                        <span>
                                            Solde réel <b>*</b>
                                        </span>
                                        <input
                                            className="control"
                                            type="number"
                                            min="0"
                                            value={ledger.actual_balance}
                                            onChange={(event) =>
                                                update(index, {
                                                    actual_balance:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </label>
                                    <CameraCapture
                                        label="Preuve de clôture"
                                        error={
                                            form.errors[
                                                `ledgers.${index}.evidence`
                                            ] ??
                                            form.errors[
                                                `ledgers.${index}.evidence_captured_at`
                                            ]
                                        }
                                        value={
                                            ledger.evidence &&
                                            ledger.evidence_preview_url
                                                ? {
                                                      file: ledger.evidence,
                                                      capturedAt:
                                                          ledger.evidence_captured_at ??
                                                          '',
                                                      previewUrl:
                                                          ledger.evidence_preview_url,
                                                  }
                                                : null
                                        }
                                        onChange={(captured) =>
                                            update(index, {
                                                evidence:
                                                    captured?.file ?? null,
                                                evidence_captured_at:
                                                    captured?.capturedAt ??
                                                    null,
                                                evidence_preview_url:
                                                    captured?.previewUrl ??
                                                    null,
                                            })
                                        }
                                    />
                                </div>
                                <label className="field cash-opening-comment">
                                    <span>
                                        Commentaire{' '}
                                        <em>obligatoire si écart</em>
                                    </span>
                                    <input
                                        className="control"
                                        value={ledger.closing_comment}
                                        onChange={(event) =>
                                            update(index, {
                                                closing_comment:
                                                    event.target.value,
                                            })
                                        }
                                        placeholder="Expliquez une différence éventuelle"
                                    />
                                </label>
                            </div>
                        );
                    })}
                </div>
                {(errors.cash_session || form.errors.ledgers) && (
                    <div className="cash-form-error" role="alert">
                        {errors.cash_session ?? form.errors.ledgers}
                    </div>
                )}
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onClose}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={!valid || form.processing}
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Soumettre la clôture'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function MovementDialog({
    session,
    onClose,
}: {
    session: CashSessionPage;
    onClose: () => void;
}) {
    const form = useForm({
        ledger_uuid: session.ledgers[0]?.uuid ?? '',
        amount: '',
        type: 'reversement',
        destination_label: '',
        destination_reference: '',
        description: '',
        evidence: null as File | null,
    });
    const valid =
        form.data.ledger_uuid !== '' &&
        Number(form.data.amount) > 0 &&
        form.data.destination_label.trim() !== '' &&
        form.data.destination_reference.trim() !== '' &&
        form.data.evidence !== null;
    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!valid || form.processing) {
            return;
        }

        form.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <div className="cash-modal-backdrop" role="presentation">
            <form
                className="cash-modal"
                role="dialog"
                aria-modal="true"
                onSubmit={submit}
            >
                <div className="cash-modal-head">
                    <div>
                        <span className="section-kicker">Sortie de fonds</span>
                        <h3>Enregistrer un reversement</h3>
                        <p>
                            Le mouvement sera soumis à validation et ne
                            modifiera le solde qu’après approbation.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="cash-modal-close"
                        onClick={onClose}
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="field">
                        <span>
                            Sous-journal <b>*</b>
                        </span>
                        <select
                            className="control"
                            value={form.data.ledger_uuid}
                            onChange={(event) =>
                                form.setData('ledger_uuid', event.target.value)
                            }
                        >
                            {session.ledgers.map((ledger) => (
                                <option key={ledger.uuid} value={ledger.uuid}>
                                    {ledger.kind === 'cash'
                                        ? 'Espèces'
                                        : ledger.account?.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="field">
                        <span>
                            Montant <b>*</b>
                        </span>
                        <input
                            className="control"
                            type="number"
                            min="1"
                            value={form.data.amount}
                            onChange={(event) =>
                                form.setData('amount', event.target.value)
                            }
                            placeholder="0"
                        />
                    </label>
                    <label className="field">
                        <span>
                            Destinataire <b>*</b>
                        </span>
                        <input
                            className="control"
                            value={form.data.destination_label}
                            onChange={(event) =>
                                form.setData(
                                    'destination_label',
                                    event.target.value,
                                )
                            }
                            placeholder="Ex. Trésorerie KORA"
                        />
                    </label>
                    <label className="field">
                        <span>
                            Référence <b>*</b>
                        </span>
                        <input
                            className="control"
                            value={form.data.destination_reference}
                            onChange={(event) =>
                                form.setData(
                                    'destination_reference',
                                    event.target.value,
                                )
                            }
                            placeholder="Référence du reversement"
                        />
                    </label>
                </div>
                <label className="field mt-4">
                    <span>
                        Justificatif <b>*</b>
                    </span>
                    <input
                        className="control"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(event) =>
                            form.setData(
                                'evidence',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <small>JPEG, PNG ou WebP · 5 Mo maximum</small>
                </label>
                <label className="field mt-4">
                    <span>
                        Commentaire <em>facultatif</em>
                    </span>
                    <textarea
                        className="control min-h-20"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </label>
                <div className="cash-modal-actions">
                    <button
                        type="button"
                        className="btn btn-ghost"
                        onClick={onClose}
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        className="btn btn-primary"
                        disabled={!valid || form.processing}
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Soumettre le reversement'}
                    </button>
                </div>
            </form>
        </div>
    );
}
