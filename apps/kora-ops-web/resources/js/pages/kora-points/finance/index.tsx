import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    CheckCircle2,
    Landmark,
    MoreHorizontal,
    Receipt,
    RotateCcw,
    ShieldOff,
    Ticket,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type {
    FinanceRefund,
    FinanceSettlement,
} from '@/components/kora-points/admin/kora-admin-ui';
import {
    formatAmount,
    KpiCard,
    RefundStatusBadge,
    SettlementStatusBadge,
} from '@/components/kora-points/admin/kora-admin-ui';
import { KoraRemittanceReceiveDialog } from '@/components/kora-points/admin/kora-remittance-receive-dialog';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import refundRoutes from '@/routes/kora-points/refunds';
import type { BreadcrumbItem } from '@/types';

type Stats = {
    totalToReconcile: number;
    refundsInArbitration: number;
    monthlyValidatedRemittances: number;
    totalRemittancesCount: number;
    totalRefundsCount: number;
};

type Props = {
    settlements: FinanceSettlement[];
    refunds: FinanceRefund[];
    groupSettlements?: unknown[];
    stats: Stats;
};

export default function KoraPointFinanceIndexPage({
    settlements,
    refunds,
    stats,
}: Props) {
    const [tab, setTab] = useState<'settlements' | 'refunds'>('settlements');
    const [receiveTarget, setReceiveTarget] =
        useState<FinanceSettlement | null>(null);
    const [receiveMode, setReceiveMode] = useState<'reconcile' | 'reject'>(
        'reconcile',
    );

    const pendingSettlements = useMemo(
        () =>
            settlements.filter((s) =>
                ['DECLARED', 'RECONCILIATION'].includes(s.status),
            ),
        [settlements],
    );

    const openReceive = (
        settlement: FinanceSettlement,
        mode: 'reconcile' | 'reject',
    ) => {
        setReceiveTarget(settlement);
        setReceiveMode(mode);
    };

    const authorizeRefund = (refund: FinanceRefund) => {
        if (
            !window.confirm(
                `Autoriser le remboursement ${refund.reference} de ${formatAmount(refund.refund_amount)} pour ${refund.passenger_name} ?`,
            )
        ) {
            return;
        }

        router.post(
            refundRoutes.authorize.url({ refund: refund.uuid }),
            {},
            { preserveScroll: true },
        );
    };

    const rejectRefund = (refund: FinanceRefund) => {
        if (
            !window.confirm(
                `Refuser la demande ${refund.reference} de ${refund.passenger_name} ?`,
            )
        ) {
            return;
        }

        router.post(
            refundRoutes.reject.url({ refund: refund.uuid }),
            {},
            { preserveScroll: true },
        );
    };

    const settlementColumns: TableColumn<FinanceSettlement>[] = [
        {
            key: 'reference',
            header: 'Bordereau',
            render: (s) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-kora text-[0.6875rem] font-black text-white">
                        {s.reference.slice(0, 3)}
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {s.reference}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {s.sales_count} vente(s) ·{' '}
                            {s.declared_at ?? s.period_start}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'point',
            header: 'Émetteur',
            render: (s) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {s.point_name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {s.created_by_name}
                    </span>
                </div>
            ),
        },
        {
            key: 'method',
            header: 'Canal',
            render: (s) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {s.payment_method === 'MOBILE_MONEY'
                            ? 'Mobile Money'
                            : s.payment_method === 'BANK_DEPOSIT'
                              ? 'Dépôt bancaire'
                              : 'Virement'}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {s.payment_reference}
                    </span>
                </div>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (s) => (
                <div>
                    <span className="block font-bold text-foreground">
                        {formatAmount(s.amount_declared)}
                    </span>
                    {s.amount_validated !== null &&
                        s.amount_validated !== s.amount_declared && (
                            <span className="text-xs font-bold text-amber-600">
                                Reçu {formatAmount(s.amount_validated)}
                            </span>
                        )}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (s) => <SettlementStatusBadge status={s.status} />,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (s) =>
                ['DECLARED', 'RECONCILIATION'].includes(s.status) && (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions pour ${s.reference}`}
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onClick={() => openReceive(s, 'reconcile')}
                                >
                                    <CheckCircle2 className="size-4" />
                                    Rapprocher
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => openReceive(s, 'reject')}
                                >
                                    <ShieldOff className="size-4" />
                                    Rejeter
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ),
        },
    ];

    const refundColumns: TableColumn<FinanceRefund>[] = [
        {
            key: 'reference',
            header: 'Demande',
            render: (r) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/15 text-[0.6875rem] font-black text-[var(--kora)]">
                        {r.reference.slice(0, 3)}
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {r.reference}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            Billet {r.ticket_number} · {r.requested_at}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'passenger',
            header: 'Voyageur',
            render: (r) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {r.passenger_name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {r.passenger_phone}
                    </span>
                </div>
            ),
        },
        {
            key: 'reason',
            header: 'Motif',
            render: (r) => (
                <span className="font-medium text-foreground">
                    {r.reason_label}
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (r) => (
                <div>
                    <span className="block font-bold text-foreground">
                        {formatAmount(r.refund_amount)}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Vente {formatAmount(r.original_amount)}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (r) => <RefundStatusBadge status={r.status} />,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (r) =>
                r.status === 'REQUESTED' && (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions pour ${r.reference}`}
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onClick={() => authorizeRefund(r)}
                                >
                                    <CheckCircle2 className="size-4" />
                                    Autoriser
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => rejectRefund(r)}
                                >
                                    <ShieldOff className="size-4" />
                                    Refuser
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ),
        },
    ];

    return (
        <>
            <Head title="Rapprochement & Finance KORA POINT" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Réseau commercial / Rapprochement & Finance
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Rapprochement & Finance
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Rapprochez les reversements partenaires et arbitrez les
                        demandes de remboursement voyageurs.
                    </p>
                </div>
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={Landmark}
                    label="À rapprocher"
                    hint="Reversements déclarés"
                    value={formatAmount(stats.totalToReconcile)}
                    tone="gold"
                />
                <KpiCard
                    icon={RotateCcw}
                    label="En arbitrage"
                    hint="Remboursements à trancher"
                    value={stats.refundsInArbitration}
                    tone="rose"
                />
                <KpiCard
                    icon={Banknote}
                    label="Validé ce mois"
                    hint="Fonds rapprochés"
                    value={formatAmount(stats.monthlyValidatedRemittances)}
                    tone="green"
                />
                <KpiCard
                    icon={Receipt}
                    label="Dossiers"
                    hint="Reversements + remboursements"
                    value={
                        stats.totalRemittancesCount + stats.totalRefundsCount
                    }
                    tone="navy"
                />
            </section>

            <div className="mb-4 flex gap-1 rounded-xl bg-muted/50 p-1 sm:w-fit">
                <button
                    type="button"
                    onClick={() => setTab('settlements')}
                    className={cn(
                        'flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition',
                        tab === 'settlements'
                            ? 'bg-card text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Landmark className="size-3.5" />
                    Reversements ({pendingSettlements.length})
                </button>
                <button
                    type="button"
                    onClick={() => setTab('refunds')}
                    className={cn(
                        'flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold transition',
                        tab === 'refunds'
                            ? 'bg-card text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Ticket className="size-3.5" />
                    Remboursements ({stats.refundsInArbitration})
                </button>
            </div>

            {tab === 'settlements' ? (
                <KoraTable
                    title="File des reversements"
                    subtitle={`${settlements.length} bordereau(x) soumis par le réseau`}
                    icon={Landmark}
                    columns={settlementColumns}
                    data={settlements}
                    rowKey={(s) => s.uuid}
                    emptyTitle="Aucun reversement"
                    emptyMessage="Les bordereaux déclarés par les points apparaîtront ici."
                    emptyIcon={Landmark}
                />
            ) : (
                <KoraTable
                    title="File des remboursements"
                    subtitle={`${refunds.length} demande(s) voyageur à arbitrer`}
                    icon={Ticket}
                    columns={refundColumns}
                    data={refunds}
                    rowKey={(r) => r.uuid}
                    emptyTitle="Aucun remboursement"
                    emptyMessage="Les demandes de remboursement apparaîtront ici."
                    emptyIcon={Ticket}
                />
            )}

            <KoraRemittanceReceiveDialog
                open={receiveTarget !== null}
                settlement={receiveTarget}
                mode={receiveMode}
                onOpenChange={(open) => {
                    if (!open) {
                        setReceiveTarget(null);
                    }
                }}
            />
        </>
    );
}

KoraPointFinanceIndexPage.layout = {
    breadcrumbs: [
        {
            title: 'Rapprochement & Finance',
            href: '/kora-points/finance',
        } satisfies BreadcrumbItem,
    ],
};
