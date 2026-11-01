import { KoraStatusBadge } from '@/components/kora-status-badge';
import { cn } from '@/lib/utils';
import type { AdminOrganization as MockAdminOrganization } from '../mock/kora-point-mock-data';

export type AdminPoint = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    status: 'ACTIVE' | 'WARNING_THRESHOLD' | 'LIMIT_REACHED' | 'SUSPENDED';
    raw_status: string;
    type: 'AUTONOMOUS' | 'ORGANIZATION_ATTACHED';
    organization_name: string | null;
    organization_uuid: string | null;
    manager_name: string;
    responsible_name: string;
    phone: string;
    address: string | null;
    latitude: number | null;
    longitude: number | null;
    terms: {
        commission_basis_points?: number | null;
        ceiling_amount?: number | null;
        settlement_calendar?: {
            frequency?: string;
            time?: string;
            timezone?: string;
            weekday?: number | null;
            day?: number | null;
        } | null;
    };
    current_exposure: number;
    credit_limit: number;
    usage_percentage: number;
    commission_rate_bps: number;
    settlement_frequency_days: number;
    monthly_sales_amount: number;
    sales_count: number;
    overdue: boolean;
    created_at: string;
};

export type AdminOrganization = MockAdminOrganization & {
    status: string;
    phone: string | null;
    address: string | null;
    commission_basis_points: number | null;
    ceiling_amount: number | null;
    settlement_calendar: {
        frequency?: string;
        time?: string;
        timezone?: string;
        weekday?: number | null;
        day?: number | null;
    } | null;
};

export type OrgSubPoint = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    manager_name: string;
    responsible_name: string;
    phone: string;
    address: string | null;
    country: string;
    status: AdminPoint['status'];
    monthly_sales_amount: number;
    credit_limit: number;
    current_exposure: number;
    usage_percentage: number;
    commission_rate_bps: number;
    organization_uuid: string | null;
    organization_name: string | null;
    settlement_calendar: AdminPoint['terms']['settlement_calendar'];
};

export type UnattachedPoint = {
    uuid: string;
    code: string;
    name: string;
    city: string;
    country: string;
    responsible_name: string;
    phone: string;
    address: string | null;
    latitude: number | null;
    longitude: number | null;
};

export type FinanceSettlement = {
    uuid: string;
    reference: string;
    point_name: string;
    created_by_name: string;
    period_start: string;
    period_end: string;
    declared_at: string | null;
    validated_at: string | null;
    amount_expected: number;
    amount_declared: number;
    amount_validated: number | null;
    payment_method: string;
    payment_reference: string;
    proof_file_name: string;
    status: 'DRAFT' | 'DECLARED' | 'RECONCILIATION' | 'VALIDATED' | 'REJECTED';
    sales_count: number;
    notes: string | null;
    rejection_reason: string | null;
};

export type FinanceRefund = {
    uuid: string;
    reference: string;
    ticket_number: string;
    passenger_name: string;
    passenger_phone: string;
    route_label: string;
    original_amount: number;
    refund_amount: number;
    reason_label: string;
    status:
        'REQUESTED' | 'APPROVED_BY_FINANCE' | 'FUNDS_RESTITUTED' | 'REJECTED';
    requested_at: string;
    requested_by: string;
    approved_at: string | null;
    approved_by: string | null;
    rejection_reason: string | null;
};

export type AdminPermissions = {
    canManage: boolean;
    canManageTerms: boolean;
};

export function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone = 'navy',
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green' | 'rose';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        rose: 'bg-destructive/10 text-destructive',
    };

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', toneStyles[tone])}>
                        <Icon className="size-4" strokeWidth={1.8} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Total</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

export function PointStatusBadge({ status }: { status: AdminPoint['status'] }) {
    switch (status) {
        case 'ACTIVE':
            return (
                <KoraStatusBadge
                    variant="solid"
                    colorScheme="emerald"
                    label="Opérationnel"
                />
            );
        case 'WARNING_THRESHOLD':
            return (
                <KoraStatusBadge
                    variant="solid"
                    colorScheme="amber"
                    label="Seuil 80%"
                />
            );
        case 'LIMIT_REACHED':
            return (
                <KoraStatusBadge
                    variant="solid"
                    colorScheme="destructive"
                    label="Plafond atteint"
                />
            );
        case 'SUSPENDED':
            return (
                <KoraStatusBadge
                    variant="solid"
                    colorScheme="muted"
                    label="Suspendu"
                />
            );
    }
}

export function SettlementStatusBadge({
    status,
}: {
    status: FinanceSettlement['status'];
}) {
    const map = {
        DRAFT: { label: 'Brouillon', colorScheme: 'muted' },
        DECLARED: { label: 'Déclaré', colorScheme: 'blue' },
        RECONCILIATION: { label: 'En rapprochement', colorScheme: 'amber' },
        VALIDATED: { label: 'Validé', colorScheme: 'emerald' },
        REJECTED: { label: 'Rejeté', colorScheme: 'destructive' },
    } as const;
    const entry = map[status] ?? map.DRAFT;

    return (
        <KoraStatusBadge
            variant="solid"
            colorScheme={entry.colorScheme}
            label={entry.label}
        />
    );
}

export function RefundStatusBadge({
    status,
}: {
    status: FinanceRefund['status'];
}) {
    const map = {
        REQUESTED: { label: 'En arbitrage', colorScheme: 'amber' },
        APPROVED_BY_FINANCE: {
            label: 'Autorisé',
            colorScheme: 'blue',
        },
        FUNDS_RESTITUTED: { label: 'Restitué', colorScheme: 'emerald' },
        REJECTED: { label: 'Refusé', colorScheme: 'destructive' },
    } as const;
    const entry = map[status] ?? map.REQUESTED;

    return (
        <KoraStatusBadge
            variant="solid"
            colorScheme={entry.colorScheme}
            label={entry.label}
        />
    );
}

export function formatAmount(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return 'Non renseigné';
    }

    return `${value.toLocaleString('fr-FR')} FCFA`;
}
