import {
    CheckCircle2,
    ClipboardList,
    Coins,
    RefreshCw,
    RotateCcw,
    ShieldAlert,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import { ParcelButton } from '@/components/parcels/parcel-button';
import { ParcelKpi } from '@/components/parcels/parcel-kpi';
import type {
    ParcelSupervision,
    ParcelSupervisionFinanceItem,
    ParcelSupervisionResponse,
} from '@/components/parcels/types';

function xsrfToken(): string {
    const value = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';

    return decodeURIComponent(value);
}

function formatDateTime(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatMoney(value: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} XOF`;
}

function responseMessage(payload: unknown, fallback: string): string {
    if (
        payload !== null &&
        typeof payload === 'object' &&
        'message' in payload &&
        typeof payload.message === 'string'
    ) {
        return payload.message;
    }

    return fallback;
}

type Props = {
    refreshKey?: number;
};

export function ParcelFinancePanel({ refreshKey = 0 }: Props) {
    const [data, setData] = useState<ParcelSupervision | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [retrying, setRetrying] = useState<string | null>(null);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch('/parcels/data/supervision', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const payload = (await response
                .json()
                .catch(() => null)) as ParcelSupervisionResponse | null;

            if (!response.ok || payload === null) {
                setError(
                    responseMessage(
                        payload,
                        'Les files financières Colis sont indisponibles.',
                    ),
                );
                setData(null);

                return;
            }

            setData(payload.data);
        } catch {
            setError(
                'Le réseau est instable. Les files financières Colis sont indisponibles.',
            );
            setData(null);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        void load();
    }, [load, refreshKey]);

    const retryFiscalization = async (item: ParcelSupervisionFinanceItem) => {
        if (item.parcel_uuid === null) {
            return;
        }

        setRetrying(item.uuid);

        try {
            const response = await fetch(
                `/parcels/data/${item.parcel_uuid}/fiscalization/retry`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                },
            );
            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                toast.error(
                    responseMessage(
                        payload,
                        'La relance de fiscalisation a été refusée.',
                    ),
                );

                return;
            }

            toast.success('Relance de fiscalisation demandée.');
            await load();
        } catch {
            toast.error(
                'Le réseau est instable. La relance n’a pas été envoyée.',
            );
        } finally {
            setRetrying(null);
        }
    };

    if (loading) {
        return (
            <section className="flex min-h-60 items-center justify-center rounded-[18px] border border-border bg-card">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <RefreshCw className="size-4 animate-spin" />
                    Chargement des files financières Colis…
                </div>
            </section>
        );
    }

    if (error !== null || data === null) {
        return (
            <section className="rounded-[18px] border border-red-200 bg-red-50 px-5 py-5 text-sm text-red-800">
                <strong className="block">
                    Files financières indisponibles
                </strong>
                <p className="mt-1">{error ?? 'Aucune donnée reçue.'}</p>
                <ParcelButton
                    type="button"
                    variant="ghost"
                    className="mt-4"
                    onClick={() => void load()}
                >
                    <RefreshCw className="size-4" />
                    Réessayer
                </ParcelButton>
            </section>
        );
    }

    if (!data.permissions.can_finance || data.finance === null) {
        return (
            <section className="rounded-[18px] border border-border bg-muted/20 px-5 py-5 text-sm text-muted-foreground">
                <div className="flex items-center gap-2 text-[0.6875rem] font-extrabold tracking-[0.14em] uppercase">
                    <Coins className="size-3.5 text-gold" />
                    Files financières Colis
                </div>
                <strong className="mt-3 block text-foreground">
                    Consultation réservée à parcels.finance.
                </strong>
                <p className="mt-1 max-w-2xl text-xs leading-5">
                    Les encaissements, remboursements et rapprochements Colis
                    restent masqués pour votre profil. Aucun montant n’est
                    recalculé dans cet écran : les valeurs proviennent des
                    paiements et factures Colis.
                </p>
            </section>
        );
    }

    const reconciliationColumns: TableColumn<ParcelSupervisionFinanceItem>[] = [
        {
            key: 'reference',
            header: 'Colis',
            render: (row) => (
                <strong className="text-xs">
                    {row.parcel_reference ?? '—'}
                </strong>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (row) => formatMoney(row.amount ?? 0),
        },
        {
            key: 'detected_at',
            header: 'Détecté le',
            render: (row) => formatDateTime(row.detected_at ?? null),
        },
    ];
    const refundColumns: TableColumn<ParcelSupervisionFinanceItem>[] = [
        {
            key: 'reference',
            header: 'Colis',
            render: (row) => (
                <strong className="text-xs">
                    {row.parcel_reference ?? '—'}
                </strong>
            ),
        },
        {
            key: 'amount',
            header: 'Montant',
            align: 'right',
            render: (row) => formatMoney(row.amount ?? 0),
        },
        {
            key: 'requested_at',
            header: 'Demandé le',
            render: (row) => formatDateTime(row.requested_at ?? null),
        },
    ];
    const invoiceColumns: TableColumn<ParcelSupervisionFinanceItem>[] = [
        {
            key: 'reference',
            header: 'Colis / facture',
            render: (row) => (
                <div>
                    <strong className="block text-xs">
                        {row.parcel_reference ?? '—'}
                    </strong>
                    <span className="text-[0.6875rem] text-muted-foreground">
                        {row.kora_reference ?? '—'}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (row) => (
                <span className="badge badge-warning">
                    {row.status_label ?? row.status ?? '—'}
                </span>
            ),
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            render: (row) => formatMoney(row.total ?? 0),
        },
        {
            key: 'action',
            header: 'Action',
            align: 'right',
            render: (row) => (
                <ParcelButton
                    type="button"
                    variant="ghost"
                    className="btn-sm"
                    disabled={retrying === row.uuid || row.parcel_uuid === null}
                    onClick={() => void retryFiscalization(row)}
                >
                    <RefreshCw
                        className={`size-3.5 ${retrying === row.uuid ? 'animate-spin' : ''}`}
                    />
                    Relancer
                </ParcelButton>
            ),
        },
    ];

    return (
        <section className="grid gap-4">
            <div className="rounded-[18px] border border-border bg-card px-5 py-5">
                <div className="flex items-center gap-2 text-[0.6875rem] font-extrabold tracking-[0.14em] text-muted-foreground uppercase">
                    <Coins className="size-3.5 text-gold" />
                    Files financières Colis
                </div>
                <p className="mt-1 max-w-2xl text-xs text-muted-foreground">
                    Consultation réservée aux porteurs de parcels.finance. Les
                    montants proviennent des paiements et factures Colis, sans
                    second calcul de revenu.
                </p>
            </div>

            <div className="grid gap-3.5 sm:grid-cols-3">
                <ParcelKpi
                    icon={ShieldAlert}
                    label="Rapprochements"
                    hint="Paiements à vérifier"
                    value={data.finance.reconciliations.count}
                    tone="navy"
                    tooltip={`Montant cumulé : ${formatMoney(data.finance.reconciliations.total_amount)}. Paiements Colis marqués parcel_reconciliation_required.`}
                />
                <ParcelKpi
                    icon={RotateCcw}
                    label="Remboursements"
                    hint="Autorisés, non complétés"
                    value={data.finance.pending_refunds.count}
                    tone="gold"
                    tooltip={`Montant cumulé : ${formatMoney(data.finance.pending_refunds.total_amount)}. Restitutions en statut pending.`}
                />
                <ParcelKpi
                    icon={ClipboardList}
                    label="Factures en attente"
                    hint="Fiscalisation à finaliser"
                    value={data.finance.pending_invoices.count}
                    tone="blue"
                    tooltip={`Montant cumulé : ${formatMoney(data.finance.pending_invoices.total_amount)}. Factures pending ou failed dans le périmètre Colis.`}
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <KoraTable<ParcelSupervisionFinanceItem>
                    title="Rapprochements à traiter"
                    subtitle="Paiements reçus sans règlement automatique."
                    icon={ShieldAlert}
                    variant="finance"
                    columns={reconciliationColumns}
                    data={data.finance.reconciliations.items}
                    rowKey={(row) => row.uuid}
                    emptyIcon={CheckCircle2}
                    emptyTitle="Aucun rapprochement"
                    emptyMessage="Tous les paiements Colis sont réglés."
                />
                <KoraTable<ParcelSupervisionFinanceItem>
                    title="Remboursements en attente"
                    subtitle="Autorisation posée, exécution à compléter."
                    icon={RotateCcw}
                    variant="finance"
                    columns={refundColumns}
                    data={data.finance.pending_refunds.items}
                    rowKey={(row) => row.uuid}
                    emptyIcon={CheckCircle2}
                    emptyTitle="Aucun remboursement en attente"
                    emptyMessage="Les restitutions autorisées sont complétées."
                />
            </div>

            <KoraTable<ParcelSupervisionFinanceItem>
                title="Factures en attente de fiscalisation"
                subtitle="Relance possible sans exposer de payload fournisseur."
                icon={ClipboardList}
                variant="finance"
                columns={invoiceColumns}
                data={data.finance.pending_invoices.items}
                rowKey={(row) => row.uuid}
                emptyIcon={CheckCircle2}
                emptyTitle="Aucune facture en attente"
                emptyMessage="Toutes les factures Colis sont certifiées."
            />
        </section>
    );
}
