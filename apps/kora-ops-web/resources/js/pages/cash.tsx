import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    Check,
    ChevronRight,
    CircleAlert,
    Download,
    FileCheck2,
    Search,
    Plus,
    Smartphone,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CameraCapture } from '@/components/cash/camera-capture';
import { CashOpeningDialog } from '@/components/cash/cash-opening-dialog';
import type {
    SaleCashLedger,
    SaleCashMedia,
    SaleCashSession,
    SaleMobileMoneyAccount,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';
import { approveVariance, close as closeCash } from '@/routes/cash';
import { approve, reject, store } from '@/routes/cash/movements';

type CashMovement = {
    uuid: string;
    ledgerUuid: string | null;
    type: string;
    direction: 'in' | 'out';
    status: 'posted' | 'pending' | 'approved' | 'rejected' | 'reversed';
    amount: number;
    reference: string | null;
    destinationLabel: string | null;
    occurredAt: string | null;
    media: SaleCashMedia[];
};
type CashSessionPage = SaleCashSession & {
    ledgers: SaleCashLedger[];
    movements: CashMovement[];
    openedAt?: string | null;
    agentName?: string | null;
    agencyName?: string | null;
};
type Props = {
    cashSession: CashSessionPage | null;
    mobileMoneyAccounts: SaleMobileMoneyAccount[];
    permissions: {
        canOpen: boolean;
        canClose: boolean;
        canApproveVariance: boolean;
        canCreateMovement: boolean;
        canApproveMovement: boolean;
    };
};

const networkLabels: Record<string, string> = {
    mtn_momo: 'MTN MoMo',
    moov_flooz: 'Moov Money / Flooz',
    celtiis_cash: 'Celtiis Cash',
};
const networkBrands: Record<string, { key: string; mark: string }> = {
    mtn_momo: { key: 'mtn', mark: 'MTN' },
    moov_flooz: { key: 'moov', mark: 'moov' },
    celtiis_cash: { key: 'celtiis', mark: 'celtiis' },
};
const statusLabels: Record<string, string> = {
    open: 'Ouverte',
    pending_approval: 'En attente d’approbation',
    closed: 'Clôturée',
    posted: 'Publié',
    pending: 'En attente',
    approved: 'Approuvé',
    rejected: 'Rejeté',
    reversed: 'Contrepassé',
};

const movementTypeLabels: Record<string, string> = {
    counter_sale: 'Vente guichet',
    mobile_money_payment: 'Paiement Mobile Money',
    cash_payment: 'Paiement espèces',
    reversement: 'Reversement',
    adjustment: 'Ajustement',
};

const postedStatuses = new Set(['posted', 'approved']);

function buildDemoMovements(): CashMovement[] {
    const seeds = [
        {
            type: 'counter_sale',
            direction: 'in' as const,
            amount: 12500,
            ledgerUuid: 'demo-ledger-cash',
            reference: 'KORA-240828-001',
            destinationLabel: null,
            status: 'posted' as const,
        },
        {
            type: 'mobile_money_payment',
            direction: 'in' as const,
            amount: 18500,
            ledgerUuid: 'demo-ledger-mtn',
            reference: 'MTN-884201',
            destinationLabel: 'MTN MoMo',
            status: 'posted' as const,
        },
        {
            type: 'counter_sale',
            direction: 'in' as const,
            amount: 22000,
            ledgerUuid: 'demo-ledger-cash',
            reference: 'KORA-240828-003',
            destinationLabel: null,
            status: 'posted' as const,
        },
        {
            type: 'mobile_money_payment',
            direction: 'in' as const,
            amount: 9500,
            ledgerUuid: 'demo-ledger-moov',
            reference: 'FLOOZ-441902',
            destinationLabel: 'Moov Money / Flooz',
            status: 'approved' as const,
        },
        {
            type: 'reversement',
            direction: 'out' as const,
            amount: 50000,
            ledgerUuid: 'demo-ledger-cash',
            reference: 'REV-240828-01',
            destinationLabel: 'Trésorerie agence',
            status: 'pending' as const,
        },
        {
            type: 'counter_sale',
            direction: 'in' as const,
            amount: 15000,
            ledgerUuid: 'demo-ledger-cash',
            reference: 'KORA-240828-006',
            destinationLabel: null,
            status: 'posted' as const,
        },
        {
            type: 'mobile_money_payment',
            direction: 'in' as const,
            amount: 27500,
            ledgerUuid: 'demo-ledger-moov',
            reference: 'FLOOZ-441917',
            destinationLabel: 'Moov Money / Flooz',
            status: 'posted' as const,
        },
        {
            type: 'adjustment',
            direction: 'out' as const,
            amount: 3000,
            ledgerUuid: 'demo-ledger-cash',
            reference: 'AJU-240828-01',
            destinationLabel: 'Fonds de caisse',
            status: 'approved' as const,
        },
    ];

    return Array.from({ length: 120 }, (_, index) => {
        const seed = seeds[index % seeds.length];
        const hour = 7 + ((index * 3) % 10);
        const minute = (index * 11) % 60;
        const date = new Date('2026-08-28T00:00:00+01:00');
        date.setHours(hour, minute, 0, 0);

        return {
            uuid: `demo-movement-${index + 1}`,
            ...seed,
            amount: seed.amount + (index % 5) * 500,
            reference: `${seed.reference}-${String(index + 1).padStart(3, '0')}`,
            occurredAt: date.toISOString(),
            media: [],
        };
    });
}

const demoSession: CashSessionPage = {
    isOpen: true,
    canOpen: false,
    uuid: 'demo-cash-session',
    businessDate: '2026-08-28',
    totalCash: 877500,
    totalMobileMoney: 1157500,
    status: 'open',
    openedAt: '2026-08-28T07:32:00+01:00',
    agentName: 'Aïcha Koudjo',
    agencyName: 'Agence Cotonou — Étoile Rouge',
    ledgers: [
        {
            uuid: 'demo-ledger-cash',
            kind: 'cash',
            status: 'open',
            openingBalance: 150000,
            expectedBalance: 877500,
            actualBalance: null,
            variance: null,
            account: null,
        },
        {
            uuid: 'demo-ledger-mtn',
            kind: 'mobile_money',
            status: 'open',
            openingBalance: 200000,
            expectedBalance: 492500,
            actualBalance: null,
            variance: null,
            account: {
                uuid: 'demo-account-mtn',
                network: 'mtn_momo',
                label: 'Compte principal',
                accountIdentifier: '•••• 4821',
            },
        },
        {
            uuid: 'demo-ledger-moov',
            kind: 'mobile_money',
            status: 'open',
            openingBalance: 80000,
            expectedBalance: 665000,
            actualBalance: null,
            variance: null,
            account: {
                uuid: 'demo-account-moov',
                network: 'moov_flooz',
                label: 'Compte agence',
                accountIdentifier: '•••• 9056',
            },
        },
        {
            uuid: 'demo-ledger-celtiis',
            kind: 'mobile_money',
            status: 'open',
            openingBalance: 50000,
            expectedBalance: 210000,
            actualBalance: null,
            variance: null,
            account: {
                uuid: 'demo-account-celtiis',
                network: 'celtiis_cash',
                label: 'Compte agence',
                accountIdentifier: '•••• 1374',
            },
        },
    ],
    movements: buildDemoMovements(),
};

function formatMovementType(type: string): string {
    return movementTypeLabels[type] ?? type.replaceAll('_', ' ');
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function getMovementChannel(
    movement: CashMovement,
    session: CashSessionPage,
): string {
    const ledger = session.ledgers.find(
        (item) => item.uuid === movement.ledgerUuid,
    );

    if (ledger?.kind === 'cash') {
        return 'Espèces';
    }

    return networkLabels[ledger?.account?.network ?? ''] ?? 'Mobile Money';
}

export default function CashPage({
    cashSession,
    mobileMoneyAccounts,
    permissions,
}: Props) {
    const [openingOpen, setOpeningOpen] = useState(false);
    const [closingOpen, setClosingOpen] = useState(false);
    const [movementOpen, setMovementOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [channel, setChannel] = useState('all');
    const [status, setStatus] = useState('all');
    const [direction, setDirection] = useState('all');
    const [page, setPage] = useState(1);

    const isDemoSession = cashSession === null;
    const session = cashSession ?? demoSession;
    const filteredMovements = useMemo(() => {
        const normalizedQuery = query.trim().toLowerCase();

        return session.movements.filter((movement) => {
            const movementChannel = getMovementChannel(movement, session);
            const matchesQuery =
                normalizedQuery === '' ||
                [
                    formatMovementType(movement.type),
                    movement.reference ?? '',
                    movement.destinationLabel ?? '',
                    movementChannel,
                ].some((value) =>
                    value.toLowerCase().includes(normalizedQuery),
                );

            return (
                matchesQuery &&
                (channel === 'all' || movementChannel === channel) &&
                (status === 'all' || movement.status === status) &&
                (direction === 'all' || movement.direction === direction)
            );
        });
    }, [channel, direction, query, session, status]);

    const pageSize = 8;
    const totalPages = Math.max(
        1,
        Math.ceil(filteredMovements.length / pageSize),
    );
    const visibleMovements = filteredMovements.slice(
        (page - 1) * pageSize,
        page * pageSize,
    );
    const ledgerCards = [...session.ledgers].sort((first, second) => {
        if (first.kind === 'cash') {
            return -1;
        }

        if (second.kind === 'cash') {
            return 1;
        }

        return 0;
    });
    const totalExpected = session.ledgers.reduce(
        (total, ledger) => total + ledger.expectedBalance,
        0,
    );
    const resetFilters = () => {
        setQuery('');
        setChannel('all');
        setStatus('all');
        setDirection('all');
        setPage(1);
    };

    const exportMovements = () => {
        const rows = [
            [
                'Date',
                'Nature',
                'Canal',
                'Référence',
                'Sens',
                'Statut',
                'Montant',
            ],
            ...filteredMovements.map((movement) => [
                formatDateTime(movement.occurredAt),
                formatMovementType(movement.type),
                getMovementChannel(movement, session),
                movement.reference ?? '',
                movement.direction === 'in' ? 'Encaissement' : 'Sortie',
                statusLabels[movement.status] ?? movement.status,
                String(movement.amount),
            ]),
        ];
        const csv = `\ufeff${rows
            .map((row) =>
                row.map((cell) => `"${cell.replaceAll('"', '""')}"`).join(';'),
            )
            .join('\n')}`;
        const url = URL.createObjectURL(
            new Blob([csv], { type: 'text/csv;charset=utf-8' }),
        );
        const link = document.createElement('a');
        link.href = url;
        link.download = `encaissements-${session.businessDate ?? 'jour'}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

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
                            Suivez les encaissements et préparez la clôture de
                            la journée.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        {isDemoSession && permissions.canOpen && (
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={() => setOpeningOpen(true)}
                            >
                                <Plus className="size-4" /> Ouvrir la caisse
                            </button>
                        )}
                        {session.status === 'open' &&
                            !isDemoSession &&
                            permissions.canClose && (
                                <button
                                    type="button"
                                    className="btn btn-primary"
                                    onClick={() => setClosingOpen(true)}
                                >
                                    <FileCheck2 className="size-4" /> Clôturer
                                    la caisse
                                </button>
                            )}
                    </div>
                </div>

                <section className="cash-reveal cash-reveal-delay-1 cash-hero">
                    <div>
                        <span className="section-kicker text-gold">
                            {isDemoSession
                                ? 'Aperçu de la session'
                                : 'Ma caisse journalière'}
                        </span>
                        <h2>
                            {isDemoSession
                                ? 'Caisse prête à être ouverte'
                                : `Caisse ouverte depuis ${formatTime(session.openedAt)}`}
                        </h2>
                        <p>
                            {isDemoSession
                                ? 'Les chiffres illustrent la structure attendue avant le raccordement complet aux écritures du jour.'
                                : 'Le total additionne les soldes théoriques de chaque compte suivi.'}
                        </p>
                    </div>
                    <div className="cash-hero-current">
                        <span>Solde théorique actuel</span>
                        <strong>{formatMoney(totalExpected, 'XOF')}</strong>
                    </div>
                    <span className="cash-status open">
                        <i />
                        {isDemoSession ? 'Aperçu statique' : 'Ouverte'}
                    </span>
                </section>

                {isDemoSession && (
                    <div className="cash-demo-note cash-reveal cash-reveal-delay-1">
                        <CircleAlert className="size-4" /> Les montants affichés
                        sont des données de démonstration pour valider l’écran.
                    </div>
                )}

                <section className="cash-ledger-grid cash-reveal cash-reveal-delay-2">
                    {ledgerCards.map((ledger) => (
                        <LedgerBalanceCard
                            key={ledger.uuid}
                            ledger={ledger}
                            movements={session.movements}
                        />
                    ))}
                </section>

                <div className="cash-reveal cash-reveal-delay-3 cash-section-intro">
                    <div>
                        <span className="section-kicker">
                            Journal automatique
                        </span>
                        <h2>Opérations du jour</h2>
                    </div>
                    <div className="actions">
                        {permissions.canCreateMovement &&
                            !isDemoSession &&
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
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={exportMovements}
                        >
                            <Download className="size-4" /> Exporter
                        </button>
                    </div>
                </div>

                <div className="cash-filterbar cash-reveal cash-reveal-delay-3">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setPage(1);
                        }}
                        placeholder="Rechercher une opération, une référence…"
                        className="cash-filter-search"
                    />
                    <FilterSelect
                        label="Canal"
                        value={channel}
                        onChange={(value) => {
                            setChannel(value);
                            setPage(1);
                        }}
                        options={[
                            ['all', 'Tous les canaux'],
                            ['Espèces', 'Espèces'],
                            ['MTN MoMo', 'MTN MoMo'],
                            ['Moov Money / Flooz', 'Moov Money / Flooz'],
                            ['Celtiis Cash', 'Celtiis Cash'],
                        ]}
                    />
                    <FilterSelect
                        label="Statut"
                        value={status}
                        onChange={(value) => {
                            setStatus(value);
                            setPage(1);
                        }}
                        options={[
                            ['all', 'Tous les statuts'],
                            ['posted', 'Publié'],
                            ['approved', 'Approuvé'],
                            ['pending', 'En attente'],
                            ['rejected', 'Rejeté'],
                        ]}
                    />
                    <FilterSelect
                        label="Sens"
                        value={direction}
                        onChange={(value) => {
                            setDirection(value);
                            setPage(1);
                        }}
                        options={[
                            ['all', 'Tous les sens'],
                            ['in', 'Encaissements'],
                            ['out', 'Sorties'],
                        ]}
                    />
                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={() => setPage(1)}
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
                    <MovementTable
                        movements={visibleMovements}
                        session={session}
                        canApprove={
                            permissions.canApproveMovement && !isDemoSession
                        }
                    />
                    <Pagination
                        currentPage={page}
                        totalPages={totalPages}
                        total={filteredMovements.length}
                        pageSize={pageSize}
                        onPageChange={setPage}
                    />
                </section>

                {session.status === 'pending_approval' && (
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
        </>
    );
}

function formatTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function LedgerBalanceCard({
    ledger,
    movements,
}: {
    ledger: SaleCashLedger;
    movements: CashMovement[];
}) {
    const ledgerMovements = movements.filter(
        (movement) => movement.ledgerUuid === ledger.uuid,
    );
    const inflows = ledgerMovements
        .filter(
            (movement) =>
                movement.direction === 'in' &&
                postedStatuses.has(movement.status),
        )
        .reduce((total, movement) => total + movement.amount, 0);
    const outflows = ledgerMovements
        .filter(
            (movement) =>
                movement.direction === 'out' &&
                postedStatuses.has(movement.status),
        )
        .reduce((total, movement) => total + movement.amount, 0);
    const isCash = ledger.kind === 'cash';
    const network = isCash
        ? 'Espèces'
        : (networkLabels[ledger.account?.network ?? ''] ?? 'Mobile Money');
    const brand = isCash
        ? { key: 'cash', mark: '₣' }
        : (networkBrands[ledger.account?.network ?? ''] ?? {
              key: 'default',
              mark: 'MM',
          });
    const accountLabel = isCash
        ? 'Caisse physique'
        : (ledger.account?.label ?? 'Compte Mobile Money');
    const balance = ledger.expectedBalance;

    return (
        <article
            className={cn('cash-ledger-card', `cash-ledger-card-${brand.key}`)}
        >
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className="cash-ledger-brand">{brand.mark}</span>
                    <div>
                        <span className="cash-ledger-title">{network}</span>
                        <small>
                            {ledger.account?.accountIdentifier ?? accountLabel}
                        </small>
                    </div>
                </div>
                <StatusPill status={ledger.status} />
            </div>

            <div className="cash-ledger-balance">
                <span>Solde théorique</span>
                <strong>{formatMoney(balance, 'XOF')}</strong>
            </div>

            <div className="cash-ledger-breakdown">
                <div>
                    <span>Ouverture</span>
                    <strong>{formatMoney(ledger.openingBalance, 'XOF')}</strong>
                </div>
                <div>
                    <span>Entrées</span>
                    <strong className="is-positive">
                        +{formatMoney(inflows, 'XOF')}
                    </strong>
                </div>
                <div>
                    <span>Sorties</span>
                    <strong className="is-negative">
                        −{formatMoney(outflows, 'XOF')}
                    </strong>
                </div>
            </div>

            <div className="cash-ledger-foot">
                {isCash ? (
                    <Banknote className="size-3.5" strokeWidth={1.7} />
                ) : (
                    <Smartphone className="size-3.5" strokeWidth={1.7} />
                )}
                <span>
                    {ledgerMovements.length === 0
                        ? 'Aucun mouvement enregistré'
                        : `${ledgerMovements.length} mouvement${ledgerMovements.length > 1 ? 's' : ''} enregistré${ledgerMovements.length > 1 ? 's' : ''}`}
                </span>
                {ledger.actualBalance !== null && (
                    <b>Constaté {formatMoney(ledger.actualBalance, 'XOF')}</b>
                )}
            </div>
        </article>
    );
}

function MovementTable({
    movements,
    session,
    canApprove,
}: {
    movements: CashMovement[];
    session: CashSessionPage;
    canApprove: boolean;
}) {
    if (movements.length === 0) {
        return (
            <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                Aucun mouvement ne correspond à ces filtres.
            </p>
        );
    }

    return (
        <div className="cash-table-scroll overflow-x-auto">
            <table className="w-full min-w-[850px] text-left">
                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3">Date & heure</th>
                        <th className="px-4 py-3">Opération</th>
                        <th className="px-4 py-3">Canal</th>
                        <th className="px-4 py-3">Référence</th>
                        <th className="px-4 py-3">Statut</th>
                        <th className="px-4 py-3 text-right">Montant</th>
                        <th className="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {movements.map((movement) => (
                        <MovementRow
                            key={movement.uuid}
                            movement={movement}
                            session={session}
                            canApprove={canApprove}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MovementRow({
    movement,
    session,
    canApprove,
}: {
    movement: CashMovement;
    session: CashSessionPage;
    canApprove: boolean;
}) {
    return (
        <tr className="group border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30">
            <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                {formatDateTime(movement.occurredAt)}
            </td>
            <td className="px-4 py-3.5">
                <div className="flex items-center gap-3">
                    <span
                        className={cn(
                            'grid size-9 shrink-0 place-items-center rounded-xl',
                            movement.direction === 'in'
                                ? 'bg-emerald-500/10 text-emerald-600'
                                : 'bg-orange-500/10 text-orange-600',
                        )}
                    >
                        {movement.direction === 'in' ? (
                            <ArrowDownLeft
                                className="size-4"
                                strokeWidth={1.6}
                            />
                        ) : (
                            <ArrowUpRight
                                className="size-4"
                                strokeWidth={1.6}
                            />
                        )}
                    </span>
                    <div>
                        <p className="text-xs font-black text-foreground">
                            {formatMovementType(movement.type)}
                        </p>
                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                            {movement.direction === 'in'
                                ? 'Encaissement'
                                : 'Sortie de caisse'}
                        </p>
                    </div>
                </div>
            </td>
            <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                {getMovementChannel(movement, session)}
            </td>
            <td className="px-4 py-3.5 font-mono text-[0.6875rem] text-muted-foreground">
                {movement.reference ?? 'Sans référence'}
            </td>
            <td className="px-4 py-3.5">
                <StatusPill status={movement.status} />
            </td>
            <td
                className={cn(
                    'px-4 py-3.5 text-right text-xs font-black',
                    movement.direction === 'in'
                        ? 'text-emerald-600'
                        : 'text-orange-600',
                )}
            >
                {movement.direction === 'in' ? '+' : '-'}
                {formatMoney(movement.amount, 'XOF')}
            </td>
            <td className="px-4 py-3.5">
                <div className="flex justify-end gap-1.5">
                    {canApprove && movement.status === 'pending' ? (
                        <>
                            <button
                                className="grid size-8 place-items-center rounded-full bg-emerald-500/10 text-emerald-600 transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-emerald-500/20"
                                onClick={() =>
                                    router.post(approve.url(movement.uuid))
                                }
                                aria-label="Approuver"
                            >
                                <Check className="size-3.5" strokeWidth={1.7} />
                            </button>
                            <button
                                className="grid size-8 place-items-center rounded-full bg-destructive/10 text-destructive transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:scale-105 hover:bg-destructive/20"
                                onClick={() =>
                                    router.post(reject.url(movement.uuid), {
                                        reason: 'Rejeté depuis la caisse',
                                    })
                                }
                                aria-label="Rejeter"
                            >
                                <X className="size-3.5" strokeWidth={1.7} />
                            </button>
                        </>
                    ) : (
                        <span className="grid size-8 place-items-center rounded-full text-muted-foreground/45">
                            <ChevronRight
                                className="size-4"
                                strokeWidth={1.5}
                            />
                        </span>
                    )}
                </div>
            </td>
        </tr>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[][];
}) {
    return (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <ChevronRight
                className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                strokeWidth={1.7}
            />
        </label>
    );
}

function Pagination({
    currentPage,
    totalPages,
    total,
    pageSize,
    onPageChange,
}: {
    currentPage: number;
    totalPages: number;
    total: number;
    pageSize: number;
    onPageChange: (page: number) => void;
}) {
    const pages: (number | 'ellipsis')[] = [];

    for (let index = 1; index <= totalPages; index += 1) {
        if (
            index === 1 ||
            index === totalPages ||
            Math.abs(index - currentPage) <= 1
        ) {
            pages.push(index);
        } else if (pages[pages.length - 1] !== 'ellipsis') {
            pages.push('ellipsis');
        }
    }

    const from = total === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const to = Math.min(currentPage * pageSize, total);

    return (
        <div className="flex flex-col gap-4 px-4 py-3 text-[0.6875rem] text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <span>
                {from}–{to} sur {total} opérations
            </span>
            <div className="flex items-center gap-[5px]">
                <button
                    type="button"
                    onClick={() => onPageChange(Math.max(1, currentPage - 1))}
                    disabled={currentPage === 1}
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                >
                    <ChevronRight
                        className="size-3.5 rotate-180"
                        strokeWidth={1.7}
                    />
                </button>
                {pages.map((page, index) =>
                    page === 'ellipsis' ? (
                        <span
                            key={`ellipsis-${index}`}
                            className="grid size-[31px] place-items-center"
                        >
                            …
                        </span>
                    ) : (
                        <button
                            key={page}
                            type="button"
                            onClick={() => onPageChange(page)}
                            className={cn(
                                'grid size-[31px] place-items-center rounded-[8px] text-xs font-black transition-[transform,background-color,color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-0.5',
                                page === currentPage
                                    ? 'bg-[var(--kora)] text-white'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            {page}
                        </button>
                    ),
                )}
                <button
                    type="button"
                    onClick={() =>
                        onPageChange(Math.min(totalPages, currentPage + 1))
                    }
                    disabled={currentPage === totalPages}
                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                >
                    <ChevronRight className="size-3.5" strokeWidth={1.7} />
                </button>
            </div>
        </div>
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

function StatusPill({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'rounded-full px-2 py-1 text-[0.6rem] font-extrabold',
                ['open', 'posted', 'approved', 'closed'].includes(status)
                    ? 'bg-emerald-500/10 text-emerald-600'
                    : status === 'rejected'
                      ? 'bg-destructive/10 text-destructive'
                      : 'bg-gold/15 text-gold',
            )}
        >
            {statusLabels[status] ?? status}
        </span>
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
