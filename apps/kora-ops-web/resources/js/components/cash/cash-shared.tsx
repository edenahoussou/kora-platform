import {
    ArrowDownLeft,
    ArrowUpRight,
    CheckCheck,
    ChevronRight,
    DoorOpen,
    Hourglass,
    Lock,
    ShieldAlert,
} from 'lucide-react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type {
    SaleCashMedia,
    SaleCashLedger,
} from '@/components/sale/sale-types';
import { formatMoney } from '@/components/sale/sale-utils';
import { cn } from '@/lib/utils';

export type CashMovementRow = {
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

/** Ligne du journal unifié : mouvement financier ou événement de session. */
export type CashOperationRow = {
    key: string;
    uuid: string;
    kind: 'movement' | 'lifecycle';
    event: string;
    direction: 'in' | 'out' | null;
    status: string | null;
    amount: number | null;
    reference: string | null;
    occurredAt: string | null;
    sessionUuid: string;
    businessDate: string;
    agentName: string | null;
    channel: string | null;
};

export type CashSessionLedger = SaleCashLedger & {
    inflows: number;
    outflows: number;
    movementCount: number;
    openingMedia?: SaleCashMedia[];
    closingMedia?: SaleCashMedia[];
};

export const networkLabels: Record<string, string> = {
    mtn_momo: 'MTN MoMo',
    moov_flooz: 'Moov Money / Flooz',
    celtiis_cash: 'Celtiis Cash',
};

export const networkBrands: Record<string, { key: string; mark: string }> = {
    mtn_momo: { key: 'mtn', mark: 'MTN' },
    moov_flooz: { key: 'moov', mark: 'moov' },
    celtiis_cash: { key: 'celtiis', mark: 'celtiis' },
};

export const statusLabels: Record<string, string> = {
    open: 'Ouverte',
    pending_approval: 'En attente d’approbation',
    closed: 'Clôturée',
    posted: 'Publié',
    pending: 'En attente',
    approved: 'Approuvé',
    rejected: 'Rejeté',
    reversed: 'Contrepassé',
};

export const movementTypeLabels: Record<string, string> = {
    counter_sale: 'Vente guichet',
    counter_sale_cash: 'Vente guichet espèces',
    counter_sale_mobile_money: 'Vente guichet Mobile Money',
    parcel_sale_cash: 'Envoi colis espèces',
    parcel_sale_mobile_money: 'Envoi colis Mobile Money',
    mobile_money_payment: 'Paiement Mobile Money',
    cash_payment: 'Paiement espèces',
    reversement: 'Reversement',
    expense: 'Dépense',
    adjustment: 'Ajustement',
};

export const lifecycleEventLabels: Record<string, string> = {
    session_opened: 'Ouverture de caisse',
    session_submitted: 'Clôture soumise',
    session_closed: 'Clôture de caisse',
    session_force_closed: 'Clôture forcée',
    variance_approved: 'Écart approuvé',
};

export const channelLabels: Record<string, string> = {
    cash: 'Espèces',
    ...networkLabels,
};

export function formatMovementType(type: string): string {
    return movementTypeLabels[type] ?? type.replaceAll('_', ' ');
}

export function formatOperationEvent(operation: CashOperationRow): string {
    return (
        movementTypeLabels[operation.event] ??
        lifecycleEventLabels[operation.event] ??
        operation.event.replaceAll('_', ' ')
    );
}

export function operationChannelLabel(operation: CashOperationRow): string {
    if (operation.kind !== 'movement') {
        return '—';
    }

    return channelLabels[operation.channel ?? ''] ?? 'Mobile Money';
}

export function formatDateTime(value: string | null): string {
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

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
}

export function formatTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export function StatusPill({ status }: { status: string }) {
    const tone =
        status === 'pending' || status === 'pending_approval'
            ? 'gold'
            : status === 'rejected'
              ? 'destructive'
              : status === 'closed' || status === 'reversed'
                ? 'muted'
                : 'navy';

    return (
        <KoraStatusBadge label={statusLabels[status] ?? status} tone={tone} />
    );
}

export function FilterSelect({
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

export function Pagination({
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
                {from}–{to} sur {total} éléments
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

const lifecycleIcons: Record<string, typeof DoorOpen> = {
    session_opened: DoorOpen,
    session_submitted: Hourglass,
    session_closed: Lock,
    session_force_closed: ShieldAlert,
    variance_approved: CheckCheck,
};

export function OperationsTable({
    operations,
    showAgent = false,
    onSelect,
    emptyLabel = 'Aucune opération ne correspond à ces filtres.',
}: {
    operations: CashOperationRow[];
    showAgent?: boolean;
    onSelect?: (operation: CashOperationRow) => void;
    emptyLabel?: string;
}) {
    if (operations.length === 0) {
        return (
            <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                {emptyLabel}
            </p>
        );
    }

    return (
        <div className="cash-table-scroll overflow-x-auto">
            <table className="w-full min-w-[950px] text-left">
                <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3">Date & heure</th>
                        <th className="px-4 py-3">Opération</th>
                        {showAgent && <th className="px-4 py-3">Agent</th>}
                        <th className="px-4 py-3">Canal</th>
                        <th className="px-4 py-3">Référence</th>
                        <th className="px-4 py-3">Statut</th>
                        <th className="px-4 py-3 text-right">Montant</th>
                        <th className="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {operations.map((operation) => (
                        <OperationTableRow
                            key={operation.key}
                            operation={operation}
                            showAgent={showAgent}
                            onSelect={onSelect}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function OperationTableRow({
    operation,
    showAgent,
    onSelect,
}: {
    operation: CashOperationRow;
    showAgent: boolean;
    onSelect?: (operation: CashOperationRow) => void;
}) {
    const isMovement = operation.kind === 'movement';
    const LifecycleIcon = lifecycleIcons[operation.event] ?? DoorOpen;
    const isForced = operation.event === 'session_force_closed';

    return (
        <tr
            onClick={() => onSelect?.(operation)}
            className={cn(
                'group border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30',
                onSelect && 'cursor-pointer',
            )}
        >
            <td className="px-4 py-3.5">
                <p className="text-xs font-bold text-foreground">
                    {formatDateTime(operation.occurredAt)}
                </p>
                <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                    {formatDate(operation.businessDate)}
                </p>
            </td>
            <td className="px-4 py-3.5">
                <div className="flex items-center gap-3">
                    <span
                        className={cn(
                            'grid size-9 shrink-0 place-items-center rounded-xl',
                            isMovement
                                ? operation.direction === 'in'
                                    ? 'bg-emerald-500/10 text-emerald-600'
                                    : 'bg-orange-500/10 text-orange-600'
                                : isForced
                                  ? 'bg-destructive/10 text-destructive'
                                  : 'bg-[var(--kora)]/10 text-[var(--kora)]',
                        )}
                    >
                        {isMovement ? (
                            operation.direction === 'in' ? (
                                <ArrowDownLeft
                                    className="size-4"
                                    strokeWidth={1.6}
                                />
                            ) : (
                                <ArrowUpRight
                                    className="size-4"
                                    strokeWidth={1.6}
                                />
                            )
                        ) : (
                            <LifecycleIcon
                                className="size-4"
                                strokeWidth={1.6}
                            />
                        )}
                    </span>
                    <div>
                        <p className="text-xs font-black text-foreground">
                            {formatOperationEvent(operation)}
                        </p>
                        <p className="mt-0.5 text-[0.625rem] text-muted-foreground">
                            {isMovement
                                ? operation.direction === 'in'
                                    ? 'Encaissement'
                                    : 'Sortie de caisse'
                                : 'Cycle de vie'}
                        </p>
                    </div>
                </div>
            </td>
            {showAgent && (
                <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                    {operation.agentName ?? '—'}
                </td>
            )}
            <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                {operationChannelLabel(operation)}
            </td>
            <td className="px-4 py-3.5 font-mono text-[0.6875rem] text-muted-foreground">
                {operation.reference ?? '—'}
            </td>
            <td className="px-4 py-3.5">
                {operation.status ? (
                    <StatusPill status={operation.status} />
                ) : isForced ? (
                    <KoraStatusBadge label="Forcée" tone="destructive" />
                ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                )}
            </td>
            <td
                className={cn(
                    'px-4 py-3.5 text-right text-xs font-black',
                    isMovement
                        ? operation.direction === 'in'
                            ? 'text-emerald-600'
                            : 'text-orange-600'
                        : 'text-muted-foreground',
                )}
            >
                {isMovement && operation.amount !== null
                    ? `${operation.direction === 'in' ? '+' : '-'}${formatMoney(operation.amount, 'XOF')}`
                    : '—'}
            </td>
            <td className="px-4 py-3.5">
                <div
                    className="flex justify-end gap-1.5"
                    onClick={(e) => e.stopPropagation()}
                >
                    {onSelect ? (
                        <button
                            type="button"
                            onClick={() => onSelect(operation)}
                            className="relative grid size-8 place-items-center rounded-full text-muted-foreground transition-[background-color,color] duration-500 hover:bg-muted hover:text-foreground"
                            aria-label="Voir le détail"
                        >
                            {operation.status === 'pending' && (
                                <span className="absolute top-0.5 right-0.5 size-2 rounded-full bg-amber-500 ring-2 ring-card" />
                            )}
                            <ChevronRight
                                className="size-4"
                                strokeWidth={1.5}
                            />
                        </button>
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
