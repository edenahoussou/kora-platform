import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    BadgeCheck,
    Building2,
    CalendarClock,
    Check,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    Coins,
    ClipboardList,
    Download,
    FileText,
    Gauge,
    Landmark,
    Mail,
    Megaphone,
    MapPin,
    Pencil,
    Phone,
    RotateCcw,
    ShieldOff,
    Store,
    UserRound,
    UserPlus,
    UsersRound,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { KoraPointFieldOperationsPanels } from '@/components/kora-points/admin/field-operations-panels';
import type {
    Paginated as FieldPaginated,
    PlvOrder,
    PointVisit,
    VisitAssignee,
} from '@/components/kora-points/admin/field-operations-panels';
import {
    formatAmount,
    PointStatusBadge,
} from '@/components/kora-points/admin/kora-admin-ui';
import type { AdminPoint } from '@/components/kora-points/admin/kora-admin-ui';
import { KoraPointFormDialog } from '@/components/kora-points/admin/kora-point-form-dialog';
import type { KoraPointFormOrganization } from '@/components/kora-points/admin/kora-point-form-dialog';
import { KoraPointMemberDialog } from '@/components/kora-points/admin/kora-point-member-dialog';
import { KoraTermsDialog } from '@/components/kora-points/admin/kora-terms-dialog';
import koraPoints from '@/routes/kora-points';
import organizationRoutes from '@/routes/kora-points/organizations';
import type { BreadcrumbItem } from '@/types';

type DetailPoint = AdminPoint & {
    country: 'BJ' | 'TG' | 'CI';
    gross_sales_amount: number;
    earned_commission_amount: number;
    outstanding_amount: number;
    refunded_amount: number;
    grace_period_days: number;
    terms: AdminPoint['terms'] & {
        sources?: Record<string, 'point' | 'organization' | 'operations'>;
    };
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Sale = {
    uuid: string;
    status: string;
    gross_amount: number;
    commission_amount: number;
    commission_reversed: number;
    net_amount: number;
    outstanding_amount: number;
    refunded_amount: number;
    paid_at: string;
    due_at: string;
};

type Entry = {
    uuid: string;
    type: string;
    event: string;
    amount: number;
    commission_delta: number;
    recorded_at: string | null;
};

type Refund = {
    uuid: string;
    sale_uuid: string;
    status: string;
    amount: number | null;
    commission_reversed: number;
    executor: string | null;
    reason: string | null;
    executed_at: string | null;
};

type Remittance = {
    uuid: string;
    reference: string;
    status: string;
    point_amount: number;
    group_amount: number | null;
    received_amount: number | null;
    sales_count: number;
    submitted_at: string | null;
    validated_at: string | null;
    url: string | null;
};

type Member = {
    uuid: string;
    name: string;
    email: string | null;
    phone: string | null;
    status: 'active' | 'suspended' | 'locked';
};

type OrganizationSummary = {
    uuid: string;
    code: string;
    name: string;
    country: string;
    status: string;
    global_ceiling_amount: number | null;
    allocated_ceiling_amount: number | null;
    point_allocation_amount: number;
};

type Document = {
    uuid: string;
    kind: string;
    title: string;
    reference: string | null;
    signed_at: string | null;
    expires_at: string | null;
    download_url: string;
    source: 'point' | 'organization';
};

type Props = {
    point: DetailPoint;
    organization: OrganizationSummary | null;
    members: Paginated<Member>;
    sales: Paginated<Sale>;
    entries: Paginated<Entry>;
    refunds: Paginated<Refund>;
    remittances: Paginated<Remittance>;
    visits: FieldPaginated<PointVisit>;
    plv_orders: FieldPaginated<PlvOrder>;
    visit_assignees: VisitAssignee[];
    documents: Document[];
    organization_options: KoraPointFormOrganization[];
    permissions: {
        canManage: boolean;
        canManageTerms: boolean;
        canViewNetworkContext: boolean;
        canManageMembers: boolean;
        canManageFieldOperations: boolean;
    };
};

const tabs = [
    { key: 'summary', label: 'Synthèse', icon: Gauge },
    { key: 'sales', label: 'Ventes & commissions', icon: Coins },
    { key: 'remittances', label: 'Reversements', icon: Landmark },
    { key: 'visits', label: 'Visites', icon: ClipboardList },
    { key: 'plv', label: 'PLV', icon: Megaphone },
    { key: 'members', label: 'Membres', icon: UsersRound },
    { key: 'dossier', label: 'Dossier', icon: FileText },
] as const;

type TabKey = (typeof tabs)[number]['key'];

function dateLabel(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        ...(value.includes('T') ? { timeStyle: 'short' as const } : {}),
    }).format(date);
}

function termSourceLabel(source: string | undefined): string {
    if (source === 'organization') {
        return 'Hérité de l’organisation';
    }

    if (source === 'operations') {
        return 'Paramètre KORA OPS';
    }

    return 'Défini sur le Point';
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        draft: 'Brouillon',
        submitted: 'Soumis',
        reconciliation: 'À rapprocher',
        validated: 'Validé',
        rejected: 'Rejeté',
        requested: 'Demandé',
        approved: 'Autorisé',
        confirmed: 'Confirmé',
        executed: 'Exécuté',
        active: 'Actif',
        suspended: 'Suspendu',
        locked: 'Verrouillé',
    };

    return labels[status] ?? status;
}

function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination de l’historique"
            className="flex flex-wrap justify-end gap-1 border-t border-border px-4 py-3"
        >
            {page.links.map((link, index) => {
                const isPrevious = index === 0;
                const isNext = index === page.links.length - 1;
                const label = link.label
                    .replace(/&laquo;|&raquo;|<[^>]*>/g, '')
                    .replace(/Previous/i, 'Précédent')
                    .replace(/Next/i, 'Suivant')
                    .trim();
                const controlClass = `grid size-[31px] place-items-center rounded-[8px] text-[0.6875rem] font-black transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ${link.active ? 'bg-[var(--kora)] text-white' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`;

                return link.url ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        preserveScroll
                        className={controlClass}
                        aria-current={link.active ? 'page' : undefined}
                        aria-label={
                            isPrevious
                                ? 'Page précédente'
                                : isNext
                                  ? 'Page suivante'
                                  : `Page ${label}`
                        }
                    >
                        {isPrevious ? (
                            <ChevronLeft className="size-4" />
                        ) : isNext ? (
                            <ChevronRight className="size-4" />
                        ) : (
                            label
                        )}
                    </Link>
                ) : (
                    <span
                        key={`${link.label}-${index}`}
                        aria-disabled="true"
                        className={`${controlClass} opacity-40`}
                        aria-label={
                            isPrevious
                                ? 'Page précédente'
                                : isNext
                                  ? 'Page suivante'
                                  : undefined
                        }
                    >
                        {isPrevious ? (
                            <ChevronLeft className="size-4" />
                        ) : isNext ? (
                            <ChevronRight className="size-4" />
                        ) : (
                            label
                        )}
                    </span>
                );
            })}
        </nav>
    );
}

function Panel({
    title,
    subtitle,
    actions,
    table = false,
    children,
}: {
    title: string;
    subtitle?: string;
    actions?: React.ReactNode;
    table?: boolean;
    children: React.ReactNode;
}) {
    return (
        <section
            className={
                table
                    ? 'cash-table-card'
                    : 'overflow-hidden rounded-[1.125rem] border border-border/65 bg-card shadow-[0_12px_35px_rgba(0,29,78,0.045)]'
            }
        >
            <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                <div className="min-w-0">
                    <h2 className="text-[0.9375rem] font-bold text-foreground">
                        {title}
                    </h2>
                    {subtitle ? (
                        <p className="mt-1 text-[0.6875rem] leading-5 text-muted-foreground">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {actions ? (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                ) : null}
            </div>
            {children}
        </section>
    );
}

function InfoTile({
    icon: Icon,
    label,
    children,
    detail,
    tone = 'gold',
}: {
    icon: typeof Coins;
    label: string;
    children: React.ReactNode;
    detail?: React.ReactNode;
    tone?: 'gold' | 'digital' | 'navy' | 'emerald' | 'violet';
}) {
    const iconTone = {
        gold: 'bg-gold/15 text-[var(--kora)]',
        digital: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
        navy: 'bg-kora/10 text-kora',
        emerald: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        violet: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    }[tone];

    return (
        <div className="flex min-h-[88px] min-w-0 items-start gap-3 rounded-2xl border border-border/75 bg-card p-4 shadow-[0_7px_22px_rgba(0,29,78,0.035)]">
            <span
                className={`grid size-9 shrink-0 place-items-center rounded-xl ${iconTone}`}
            >
                <Icon className="size-[17px]" strokeWidth={1.9} />
            </span>
            <div className="min-w-0 flex-1">
                <dt className="text-[0.68rem] font-bold tracking-[0.035em] text-muted-foreground">
                    {label}
                </dt>
                <dd className="mt-1 text-sm leading-5 font-bold text-foreground">
                    {children}
                </dd>
                {detail ? (
                    <div className="mt-1 text-[0.68rem] leading-4 text-muted-foreground">
                        {detail}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

function EmptyState({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex flex-col items-center px-6 py-12 text-center">
            <span className="mb-3 grid size-11 place-items-center rounded-full bg-muted text-muted-foreground">
                <CircleHelp className="size-5" />
            </span>
            <p className="max-w-md text-sm leading-6 text-muted-foreground">
                {children}
            </p>
        </div>
    );
}

function TableHead({ children }: { children: React.ReactNode }) {
    return (
        <th className="px-4 py-3 text-left text-[0.65rem] font-bold tracking-[0.08em] whitespace-nowrap text-muted-foreground uppercase">
            {children}
        </th>
    );
}

function TableCell({
    children,
    className,
}: {
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <td
            className={`px-4 py-3.5 text-sm text-foreground ${className ?? ''}`}
        >
            {children}
        </td>
    );
}

function Kpi({
    label,
    value,
    caption,
    icon: Icon,
    tone,
}: {
    label: string;
    value: string | number;
    caption: string;
    icon: typeof Coins;
    tone: 'gold' | 'digital' | 'navy' | 'emerald';
}) {
    return (
        <article
            className={`cash-ledger-card cash-ledger-card-kpi-${tone} group transition-[transform,box-shadow] duration-300 hover:-translate-y-1.5 hover:shadow-[0_25px_55px_rgba(0,29,78,0.14)]`}
        >
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className="cash-ledger-brand">
                        <Icon className="size-4" strokeWidth={1.8} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{caption}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance mb-0">
                <span className="tabular-nums">KORA POINT</span>
                <strong className="tabular-nums">{value}</strong>
            </div>
        </article>
    );
}

export default function KoraPointShowPage({
    point,
    organization,
    members,
    sales,
    entries,
    refunds,
    remittances,
    visits,
    plv_orders,
    visit_assignees,
    documents,
    organization_options,
    permissions,
}: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('summary');
    const [editingPoint, setEditingPoint] = useState(false);
    const [editingTerms, setEditingTerms] = useState(false);
    const [addingMember, setAddingMember] = useState(false);
    const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);
    const attached = organization !== null;

    const selectTab = (index: number) => {
        const nextIndex = (index + tabs.length) % tabs.length;
        setActiveTab(tabs[nextIndex].key);
        tabRefs.current[nextIndex]?.focus();
    };

    const changeStatus = (status: 'active' | 'suspended' | 'closed') => {
        const action =
            status === 'closed'
                ? 'clôturer'
                : status === 'suspended'
                  ? 'suspendre'
                  : 'réactiver';

        if (
            !window.confirm(
                `Voulez-vous vraiment ${action} « ${point.name} » ?`,
            )
        ) {
            return;
        }

        router.patch(
            koraPoints.status.url({ point: point.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const sourceFor = (key: string) => point.terms.sources?.[key];

    return (
        <>
            <Head title={`${point.name} — KORA POINT`} />

            <main className="cash-page flex flex-col gap-5 pb-8">
                <header className="kora-page-head cash-reveal flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Réseau commercial / Points de vente /{' '}
                            {point.code}
                        </div>
                        <div className="mb-1.5 flex flex-wrap items-center gap-2">
                            <span className="text-[0.625rem] font-black tracking-[0.14em] text-[var(--digital)] uppercase">
                                KORA POINT
                            </span>
                            <PointStatusBadge status={point.status} />
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] leading-tight font-extrabold tracking-[-0.055em] text-foreground">
                            {point.name}
                        </h1>
                        <p className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.8125rem] leading-6 text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5">
                                <MapPin className="size-3.5 shrink-0" />
                                {[point.city, point.department, point.country]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span className="inline-flex items-center gap-1.5">
                                <Building2 className="size-3.5 shrink-0" />
                                {attached
                                    ? `Rattaché à ${organization.name}`
                                    : 'Point autonome'}
                            </span>
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Link href="/kora-points" className="btn btn-navy">
                            <ArrowLeft className="size-4" />
                            Retour
                        </Link>
                        {permissions.canManage ? (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setEditingPoint(true)}
                                    className="btn btn-primary"
                                >
                                    <Pencil className="size-4" />
                                    Modifier
                                </button>
                                {point.raw_status === 'active' ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            changeStatus('suspended')
                                        }
                                        className="btn btn-navy"
                                    >
                                        <ShieldOff className="size-4" />
                                        Suspendre
                                    </button>
                                ) : point.raw_status !== 'closed' ? (
                                    <button
                                        type="button"
                                        onClick={() => changeStatus('active')}
                                        className="btn btn-success"
                                    >
                                        <RotateCcw className="size-4" />
                                        Réactiver
                                    </button>
                                ) : null}
                                {point.raw_status !== 'closed' ? (
                                    <button
                                        type="button"
                                        disabled={point.current_exposure > 0}
                                        title={
                                            point.current_exposure > 0
                                                ? 'Le Point doit être soldé avant clôture.'
                                                : undefined
                                        }
                                        onClick={() => changeStatus('closed')}
                                        className="btn btn-danger"
                                    >
                                        <ShieldOff className="size-4" />
                                        Clôturer
                                    </button>
                                ) : null}
                            </>
                        ) : null}
                    </div>
                </header>

                <section className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
                    <Kpi
                        icon={Coins}
                        tone="gold"
                        label="Ventes cumulées"
                        value={formatAmount(point.gross_sales_amount)}
                        caption={`${point.sales_count} vente${point.sales_count === 1 ? '' : 's'} enregistrée${point.sales_count === 1 ? '' : 's'}`}
                    />
                    <Kpi
                        icon={Landmark}
                        tone="navy"
                        label="Encours actuel"
                        value={formatAmount(point.outstanding_amount)}
                        caption={
                            point.overdue
                                ? 'Des montants sont échus'
                                : 'Solde restant à reverser'
                        }
                    />
                    <Kpi
                        icon={BadgeCheck}
                        tone="digital"
                        label="Commissions nettes"
                        value={formatAmount(point.earned_commission_amount)}
                        caption="Après corrections enregistrées"
                    />
                    <Kpi
                        icon={Gauge}
                        tone="emerald"
                        label="Utilisation du plafond"
                        value={`${point.usage_percentage}%`}
                        caption={`${formatAmount(point.current_exposure)} sur ${formatAmount(point.credit_limit)}`}
                    />
                </section>

                <nav
                    role="tablist"
                    aria-label={`Espaces de la fiche de ${point.name}`}
                    className="-mx-1 flex overflow-x-auto border-b border-border px-1"
                >
                    {tabs.map((tab, index) => {
                        const TabIcon = tab.icon;

                        return (
                            <button
                                key={tab.key}
                                ref={(element) => {
                                    tabRefs.current[index] = element;
                                }}
                                id={`point-tab-${tab.key}`}
                                type="button"
                                role="tab"
                                aria-selected={activeTab === tab.key}
                                aria-controls={`point-panel-${tab.key}`}
                                tabIndex={activeTab === tab.key ? 0 : -1}
                                onClick={() => setActiveTab(tab.key)}
                                onKeyDown={(event) => {
                                    if (event.key === 'ArrowRight') {
                                        event.preventDefault();
                                        selectTab(index + 1);
                                    } else if (event.key === 'ArrowLeft') {
                                        event.preventDefault();
                                        selectTab(index - 1);
                                    } else if (event.key === 'Home') {
                                        event.preventDefault();
                                        selectTab(0);
                                    } else if (event.key === 'End') {
                                        event.preventDefault();
                                        selectTab(tabs.length - 1);
                                    }
                                }}
                                className={`shrink-0 border-b-2 px-3 py-3 text-sm font-semibold transition sm:px-4 ${activeTab === tab.key ? 'border-[var(--kora)] text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'} focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-ring`}
                            >
                                <span className="inline-flex items-center gap-2">
                                    <TabIcon
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {tab.label}
                                </span>
                                {tab.key === 'members' &&
                                permissions.canViewNetworkContext ? (
                                    <span className="ml-2 rounded-full bg-muted px-1.5 py-0.5 text-[0.65rem] tabular-nums">
                                        {members.total}
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}
                </nav>

                {activeTab === 'summary' ? (
                    <section
                        id="point-panel-summary"
                        role="tabpanel"
                        aria-labelledby="point-tab-summary"
                        tabIndex={0}
                        className="space-y-5 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <div className="grid gap-8 xl:grid-cols-2 xl:gap-10">
                            <section aria-labelledby="point-profile-heading">
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <div>
                                        <h2
                                            id="point-profile-heading"
                                            className="text-base font-extrabold tracking-tight text-foreground"
                                        >
                                            Profil & contact
                                        </h2>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Informations opérationnelles du
                                            point de vente.
                                        </p>
                                    </div>
                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-gold/15 text-[var(--kora)]">
                                        <Store className="size-[17px]" />
                                    </span>
                                </div>
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    <InfoTile
                                        icon={UserRound}
                                        label="Responsable"
                                        tone="digital"
                                    >
                                        {point.responsible_name ||
                                            'Non renseigné'}
                                    </InfoTile>
                                    <InfoTile
                                        icon={Phone}
                                        label="Téléphone"
                                        tone="emerald"
                                    >
                                        {point.phone || 'Non renseigné'}
                                    </InfoTile>
                                    <InfoTile
                                        icon={MapPin}
                                        label="Adresse"
                                        tone="navy"
                                    >
                                        {point.address ||
                                            point.city ||
                                            'Non renseignée'}
                                    </InfoTile>
                                    <InfoTile
                                        icon={CalendarClock}
                                        label="Création"
                                        tone="violet"
                                    >
                                        {point.created_at || '—'}
                                    </InfoTile>
                                    <InfoTile
                                        icon={BadgeCheck}
                                        label="Statut opérationnel"
                                        tone="gold"
                                    >
                                        {statusLabel(point.raw_status)}
                                    </InfoTile>
                                </dl>
                            </section>

                            <section aria-labelledby="point-terms-heading">
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <div>
                                        <h2
                                            id="point-terms-heading"
                                            className="text-base font-extrabold tracking-tight text-foreground"
                                        >
                                            {attached
                                                ? 'Rattachement & allocation'
                                                : 'Conditions du Point'}
                                        </h2>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {attached
                                                ? 'Le Point utilise une part du plafond de son organisation.'
                                                : 'Conditions commerciales actuellement appliquées.'}
                                        </p>
                                    </div>
                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-cyan-500/10 text-cyan-700 dark:text-cyan-300">
                                        {attached ? (
                                            <Building2 className="size-[17px]" />
                                        ) : (
                                            <Coins className="size-[17px]" />
                                        )}
                                    </span>
                                </div>
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    {organization ? (
                                        <>
                                            <InfoTile
                                                icon={Building2}
                                                label="Organisation"
                                                tone="navy"
                                                detail={`${organization.code} · ${organization.country}`}
                                            >
                                                {organization.name}
                                                {permissions.canManage ? (
                                                    <Link
                                                        href={organizationRoutes.show.url(
                                                            {
                                                                organization:
                                                                    organization.uuid,
                                                            },
                                                        )}
                                                        className="mt-2 inline-flex items-center gap-1.5 text-xs font-bold text-[var(--kora)] hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-ring"
                                                    >
                                                        Voir l’organisation{' '}
                                                        <ArrowUpRight className="size-3.5" />
                                                    </Link>
                                                ) : null}
                                            </InfoTile>
                                            <InfoTile
                                                icon={Landmark}
                                                label="Allocation du Point"
                                                tone="gold"
                                            >
                                                {formatAmount(
                                                    organization.point_allocation_amount,
                                                )}
                                            </InfoTile>
                                            {permissions.canViewNetworkContext ? (
                                                <>
                                                    <InfoTile
                                                        icon={Gauge}
                                                        label="Plafond global"
                                                        tone="digital"
                                                    >
                                                        {formatAmount(
                                                            organization.global_ceiling_amount,
                                                        )}
                                                    </InfoTile>
                                                    <InfoTile
                                                        icon={Coins}
                                                        label="Allocations du réseau"
                                                        tone="violet"
                                                    >
                                                        {formatAmount(
                                                            organization.allocated_ceiling_amount,
                                                        )}
                                                    </InfoTile>
                                                    <InfoTile
                                                        icon={BadgeCheck}
                                                        label="Disponible global"
                                                        tone="emerald"
                                                    >
                                                        {formatAmount(
                                                            Math.max(
                                                                0,
                                                                (organization.global_ceiling_amount ??
                                                                    0) -
                                                                    (organization.allocated_ceiling_amount ??
                                                                        0),
                                                            ),
                                                        )}
                                                    </InfoTile>
                                                </>
                                            ) : null}
                                        </>
                                    ) : (
                                        <>
                                            <InfoTile
                                                icon={Coins}
                                                label="Commission"
                                                tone="gold"
                                                detail={termSourceLabel(
                                                    sourceFor(
                                                        'commission_basis_points',
                                                    ),
                                                )}
                                            >
                                                {(
                                                    point.commission_rate_bps /
                                                    100
                                                ).toLocaleString('fr-FR')}{' '}
                                                %
                                            </InfoTile>
                                            <InfoTile
                                                icon={Landmark}
                                                label="Plafond d’encours"
                                                tone="navy"
                                                detail={termSourceLabel(
                                                    sourceFor('ceiling_amount'),
                                                )}
                                            >
                                                {formatAmount(
                                                    point.credit_limit,
                                                )}
                                            </InfoTile>
                                            <InfoTile
                                                icon={CalendarClock}
                                                label="Calendrier de reversement"
                                                tone="digital"
                                                detail={termSourceLabel(
                                                    sourceFor(
                                                        'settlement_calendar',
                                                    ),
                                                )}
                                            >
                                                <span className="capitalize">
                                                    {point.terms
                                                        .settlement_calendar
                                                        ?.frequency ??
                                                        'Non défini'}
                                                </span>
                                            </InfoTile>
                                            {permissions.canManageTerms ? (
                                                <div className="flex items-center">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setEditingTerms(
                                                                true,
                                                            )
                                                        }
                                                        className="btn btn-primary"
                                                    >
                                                        <Pencil className="size-3.5" />
                                                        Modifier les conditions
                                                    </button>
                                                </div>
                                            ) : null}
                                        </>
                                    )}
                                </dl>
                            </section>
                        </div>
                    </section>
                ) : null}

                {activeTab === 'sales' ? (
                    <section
                        id="point-panel-sales"
                        role="tabpanel"
                        aria-labelledby="point-tab-sales"
                        tabIndex={0}
                        className="space-y-5 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <Panel
                            table
                            title="Historique des ventes"
                            subtitle="Montants cumulés à la date de chaque vente, sans regroupement journalier ou mensuel."
                        >
                            {sales.data.length === 0 ? (
                                <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                                    Aucune vente n’est encore enregistrée pour
                                    ce Point.
                                </p>
                            ) : (
                                <>
                                    <div className="cash-table-scroll overflow-x-auto">
                                        <table className="w-full min-w-[780px] border-collapse">
                                            <thead className="bg-muted/40">
                                                <tr>
                                                    <TableHead>Vente</TableHead>
                                                    <TableHead>Date</TableHead>
                                                    <TableHead>
                                                        Vente brute
                                                    </TableHead>
                                                    <TableHead>
                                                        Commission
                                                    </TableHead>
                                                    <TableHead>
                                                        Correction
                                                    </TableHead>
                                                    <TableHead>
                                                        Encours
                                                    </TableHead>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {sales.data.map((sale) => (
                                                    <tr key={sale.uuid}>
                                                        <TableCell>
                                                            <span className="font-mono text-xs">
                                                                {sale.uuid
                                                                    .slice(0, 8)
                                                                    .toUpperCase()}
                                                            </span>
                                                            <span className="mt-1 block text-[0.68rem] text-muted-foreground">
                                                                {statusLabel(
                                                                    sale.status,
                                                                )}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            {dateLabel(
                                                                sale.paid_at,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="font-semibold">
                                                            {formatAmount(
                                                                sale.gross_amount,
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {formatAmount(
                                                                sale.commission_amount,
                                                            )}
                                                        </TableCell>
                                                        <TableCell
                                                            className={
                                                                sale.commission_reversed >
                                                                0
                                                                    ? 'font-semibold text-destructive'
                                                                    : 'text-muted-foreground'
                                                            }
                                                        >
                                                            {sale.commission_reversed >
                                                            0
                                                                ? `− ${formatAmount(sale.commission_reversed)}`
                                                                : '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {formatAmount(
                                                                sale.outstanding_amount,
                                                            )}
                                                        </TableCell>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Pagination page={sales} />
                                </>
                            )}
                        </Panel>

                        <div className="grid gap-5 xl:grid-cols-2">
                            <Panel
                                table
                                title="Journal des commissions"
                                subtitle="Écritures de vente, reversement et correction associées au Point."
                            >
                                {entries.data.length === 0 ? (
                                    <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                                        Aucune écriture de commission n’est
                                        disponible.
                                    </p>
                                ) : (
                                    <>
                                        <div className="cash-table-scroll overflow-x-auto">
                                            <table className="w-full min-w-[520px] border-collapse">
                                                <thead className="bg-muted/40">
                                                    <tr>
                                                        <TableHead>
                                                            Écriture
                                                        </TableHead>
                                                        <TableHead>
                                                            Type
                                                        </TableHead>
                                                        <TableHead>
                                                            Date
                                                        </TableHead>
                                                        <TableHead>
                                                            Commission
                                                        </TableHead>
                                                        <TableHead>
                                                            Encours
                                                        </TableHead>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {entries.data.map(
                                                        (entry) => (
                                                            <tr
                                                                key={entry.uuid}
                                                            >
                                                                <TableCell>
                                                                    <span className="font-mono text-xs">
                                                                        {entry.event ||
                                                                            entry.uuid.slice(
                                                                                0,
                                                                                8,
                                                                            )}
                                                                    </span>
                                                                </TableCell>
                                                                <TableCell>
                                                                    {entry.type}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {dateLabel(
                                                                        entry.recorded_at,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell
                                                                    className={
                                                                        entry.commission_delta <
                                                                        0
                                                                            ? 'font-semibold text-destructive'
                                                                            : 'font-semibold'
                                                                    }
                                                                >
                                                                    {formatAmount(
                                                                        entry.commission_delta,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {formatAmount(
                                                                        entry.amount,
                                                                    )}
                                                                </TableCell>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                        <Pagination page={entries} />
                                    </>
                                )}
                            </Panel>

                            <Panel
                                title="Remboursements & corrections"
                                subtitle="Chaque remboursement reste lié à sa vente et à la commission reprise."
                            >
                                {refunds.data.length === 0 ? (
                                    <EmptyState>
                                        Aucun remboursement n’a été enregistré
                                        pour ce Point.
                                    </EmptyState>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {refunds.data.map((refund) => (
                                            <article
                                                key={refund.uuid}
                                                className="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {formatAmount(
                                                            refund.amount,
                                                        )}{' '}
                                                        · Vente{' '}
                                                        {refund.sale_uuid
                                                            .slice(0, 8)
                                                            .toUpperCase()}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {statusLabel(
                                                            refund.status,
                                                        )}
                                                        {refund.executed_at
                                                            ? ` · ${dateLabel(refund.executed_at)}`
                                                            : ''}
                                                        {refund.reason
                                                            ? ` · ${refund.reason}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <div className="text-sm sm:text-right">
                                                    <span className="block text-[0.68rem] text-muted-foreground">
                                                        Commission corrigée
                                                    </span>
                                                    <strong className="text-destructive">
                                                        −{' '}
                                                        {formatAmount(
                                                            refund.commission_reversed,
                                                        )}
                                                    </strong>
                                                </div>
                                            </article>
                                        ))}
                                        <Pagination page={refunds} />
                                    </div>
                                )}
                            </Panel>
                        </div>
                    </section>
                ) : null}

                {activeTab === 'remittances' ? (
                    <section
                        id="point-panel-remittances"
                        role="tabpanel"
                        aria-labelledby="point-tab-remittances"
                        tabIndex={0}
                        className="focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <Panel
                            table
                            title="Historique des reversements"
                            subtitle="Le montant affecté au Point est indiqué à côté du total du bordereau, quand celui-ci couvre plusieurs Points."
                        >
                            {remittances.data.length === 0 ? (
                                <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                                    Aucun reversement ne concerne encore ce
                                    Point.
                                </p>
                            ) : (
                                <>
                                    <div className="cash-table-scroll overflow-x-auto">
                                        <table className="w-full min-w-[760px] border-collapse">
                                            <thead className="bg-muted/40">
                                                <tr>
                                                    <TableHead>
                                                        Référence
                                                    </TableHead>
                                                    <TableHead>État</TableHead>
                                                    <TableHead>
                                                        Montant du Point
                                                    </TableHead>
                                                    <TableHead>
                                                        Total du groupe
                                                    </TableHead>
                                                    <TableHead>
                                                        Reçu groupe
                                                    </TableHead>
                                                    <TableHead>Dates</TableHead>
                                                    <TableHead>
                                                        <span className="sr-only">
                                                            Détail
                                                        </span>
                                                    </TableHead>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {remittances.data.map(
                                                    (remittance) => (
                                                        <tr
                                                            key={
                                                                remittance.uuid
                                                            }
                                                        >
                                                            <TableCell>
                                                                <span className="font-mono text-xs font-bold">
                                                                    {
                                                                        remittance.reference
                                                                    }
                                                                </span>
                                                                <span className="mt-1 block text-[0.68rem] text-muted-foreground">
                                                                    {
                                                                        remittance.sales_count
                                                                    }{' '}
                                                                    vente
                                                                    {remittance.sales_count ===
                                                                    1
                                                                        ? ''
                                                                        : 's'}{' '}
                                                                    {permissions.canViewNetworkContext
                                                                        ? 'dans le groupe'
                                                                        : 'pour ce Point'}
                                                                </span>
                                                            </TableCell>
                                                            <TableCell>
                                                                {statusLabel(
                                                                    remittance.status,
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="font-bold">
                                                                {formatAmount(
                                                                    remittance.point_amount,
                                                                )}
                                                            </TableCell>
                                                            <TableCell>
                                                                {permissions.canViewNetworkContext
                                                                    ? formatAmount(
                                                                          remittance.group_amount,
                                                                      )
                                                                    : '—'}
                                                            </TableCell>
                                                            <TableCell>
                                                                {remittance.received_amount ===
                                                                null
                                                                    ? '—'
                                                                    : formatAmount(
                                                                          remittance.received_amount,
                                                                      )}
                                                            </TableCell>
                                                            <TableCell>
                                                                <span className="block">
                                                                    Soumis{' '}
                                                                    {dateLabel(
                                                                        remittance.submitted_at,
                                                                    )}
                                                                </span>
                                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                                    Validé{' '}
                                                                    {dateLabel(
                                                                        remittance.validated_at,
                                                                    )}
                                                                </span>
                                                            </TableCell>
                                                            <TableCell>
                                                                {permissions.canViewNetworkContext &&
                                                                remittance.url ? (
                                                                    <Link
                                                                        href={
                                                                            remittance.url
                                                                        }
                                                                        className="font-semibold text-[var(--kora)] hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-ring"
                                                                    >
                                                                        Détail
                                                                    </Link>
                                                                ) : (
                                                                    '—'
                                                                )}
                                                            </TableCell>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Pagination page={remittances} />
                                </>
                            )}
                        </Panel>
                    </section>
                ) : null}

                {activeTab === 'visits' ? (
                    <section
                        id="point-panel-visits"
                        role="tabpanel"
                        aria-labelledby="point-tab-visits"
                        tabIndex={0}
                        className="focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <KoraPointFieldOperationsPanels
                            kind="visits"
                            pointUuid={point.uuid}
                            visits={visits}
                            plvOrders={plv_orders}
                            visitAssignees={visit_assignees}
                            canManage={permissions.canManageFieldOperations}
                        />
                    </section>
                ) : null}

                {activeTab === 'plv' ? (
                    <section
                        id="point-panel-plv"
                        role="tabpanel"
                        aria-labelledby="point-tab-plv"
                        tabIndex={0}
                        className="focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <KoraPointFieldOperationsPanels
                            kind="plv"
                            pointUuid={point.uuid}
                            visits={visits}
                            plvOrders={plv_orders}
                            visitAssignees={visit_assignees}
                            canManage={permissions.canManageFieldOperations}
                        />
                    </section>
                ) : null}

                {activeTab === 'members' ? (
                    <section
                        id="point-panel-members"
                        role="tabpanel"
                        aria-labelledby="point-tab-members"
                        tabIndex={0}
                        className="focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <Panel
                            table
                            title="Membres du Point"
                            subtitle="Agents actifs ou suspendus affectés à l’agence de ce Point."
                            actions={
                                permissions.canManageMembers ? (
                                    <button
                                        type="button"
                                        onClick={() => setAddingMember(true)}
                                        className="btn btn-primary whitespace-nowrap"
                                    >
                                        <UserPlus className="size-4" />
                                        Nouveau membre
                                    </button>
                                ) : null
                            }
                        >
                            {!permissions.canViewNetworkContext ? (
                                <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                                    La liste nominative des agents est
                                    accessible depuis l’administration KORA OPS.
                                </p>
                            ) : members.data.length === 0 ? (
                                <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                                    Aucun agent n’est affecté à ce Point pour le
                                    moment.
                                </p>
                            ) : (
                                <>
                                    <div className="divide-y divide-border md:hidden">
                                        {members.data.map((member) => (
                                            <article
                                                key={member.uuid}
                                                className="space-y-2 px-5 py-4"
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div>
                                                        <h3 className="text-sm font-bold text-foreground">
                                                            {member.name}
                                                        </h3>
                                                        <p className="mt-1 text-xs break-all text-muted-foreground">
                                                            {member.email ||
                                                                'E-mail non renseigné'}
                                                        </p>
                                                    </div>
                                                    <span
                                                        className={`shrink-0 rounded-full px-2.5 py-1 text-[0.65rem] font-bold ${member.status === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'}`}
                                                    >
                                                        {statusLabel(
                                                            member.status,
                                                        )}
                                                    </span>
                                                </div>
                                                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Phone className="size-3.5" />
                                                    {member.phone ||
                                                        'Téléphone non renseigné'}
                                                </p>
                                            </article>
                                        ))}
                                    </div>
                                    <div className="cash-table-scroll hidden overflow-x-auto md:block">
                                        <table className="w-full min-w-[620px] border-collapse">
                                            <thead className="bg-muted/40">
                                                <tr>
                                                    <TableHead>
                                                        Membre
                                                    </TableHead>
                                                    <TableHead>
                                                        E-mail
                                                    </TableHead>
                                                    <TableHead>
                                                        Téléphone
                                                    </TableHead>
                                                    <TableHead>
                                                        Compte
                                                    </TableHead>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {members.data.map((member) => (
                                                    <tr key={member.uuid}>
                                                        <TableCell>
                                                            <span className="inline-flex items-center gap-2 font-semibold">
                                                                <UsersRound className="size-4 text-muted-foreground" />
                                                                {member.name}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            {member.email ? (
                                                                <a
                                                                    href={`mailto:${member.email}`}
                                                                    className="inline-flex items-center gap-2 text-[var(--kora)] hover:underline"
                                                                >
                                                                    <Mail className="size-3.5" />
                                                                    {
                                                                        member.email
                                                                    }
                                                                </a>
                                                            ) : (
                                                                '—'
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {member.phone ? (
                                                                <a
                                                                    href={`tel:${member.phone}`}
                                                                    className="inline-flex items-center gap-2 text-foreground hover:underline"
                                                                >
                                                                    <Phone className="size-3.5 text-muted-foreground" />
                                                                    {
                                                                        member.phone
                                                                    }
                                                                </a>
                                                            ) : (
                                                                '—'
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            <span
                                                                className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.65rem] font-bold ${member.status === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'}`}
                                                            >
                                                                {member.status ===
                                                                'active' ? (
                                                                    <Check className="size-3" />
                                                                ) : null}
                                                                {statusLabel(
                                                                    member.status,
                                                                )}
                                                            </span>
                                                        </TableCell>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    <Pagination page={members} />
                                </>
                            )}
                        </Panel>
                    </section>
                ) : null}

                {activeTab === 'dossier' ? (
                    <section
                        id="point-panel-dossier"
                        role="tabpanel"
                        aria-labelledby="point-tab-dossier"
                        tabIndex={0}
                        className="space-y-5 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <div className="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
                            <Panel
                                title={
                                    attached
                                        ? 'Conditions héritées'
                                        : 'Conditions commerciales'
                                }
                                subtitle={
                                    attached
                                        ? 'Valeurs résolues depuis l’organisation et présentées en lecture seule sur la fiche du Point.'
                                        : 'Conditions propres au Point ou valeurs par défaut KORA OPS lorsqu’aucune surcharge n’est définie.'
                                }
                            >
                                <dl className="divide-y divide-border">
                                    <div className="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
                                        <dt className="text-sm font-medium text-muted-foreground">
                                            Commission
                                        </dt>
                                        <dd className="text-right">
                                            <strong className="block text-sm text-foreground">
                                                {(
                                                    point.commission_rate_bps /
                                                    100
                                                ).toLocaleString('fr-FR')}{' '}
                                                %
                                            </strong>
                                            <small className="text-[0.68rem] text-muted-foreground">
                                                {termSourceLabel(
                                                    sourceFor(
                                                        'commission_basis_points',
                                                    ),
                                                )}
                                            </small>
                                        </dd>
                                    </div>
                                    <div className="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
                                        <dt className="text-sm font-medium text-muted-foreground">
                                            Plafond d’encours
                                        </dt>
                                        <dd className="text-right">
                                            <strong className="block text-sm text-foreground">
                                                {formatAmount(
                                                    point.credit_limit,
                                                )}
                                            </strong>
                                            <small className="text-[0.68rem] text-muted-foreground">
                                                {termSourceLabel(
                                                    sourceFor('ceiling_amount'),
                                                )}
                                            </small>
                                        </dd>
                                    </div>
                                    <div className="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
                                        <dt className="text-sm font-medium text-muted-foreground">
                                            Fréquence
                                        </dt>
                                        <dd className="text-right">
                                            <strong className="block text-sm text-foreground capitalize">
                                                {point.terms.settlement_calendar
                                                    ?.frequency ??
                                                    'Non définie'}
                                            </strong>
                                            <small className="text-[0.68rem] text-muted-foreground">
                                                {termSourceLabel(
                                                    sourceFor(
                                                        'settlement_calendar',
                                                    ),
                                                )}
                                            </small>
                                        </dd>
                                    </div>
                                    <div className="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
                                        <dt className="text-sm font-medium text-muted-foreground">
                                            Délai de grâce
                                        </dt>
                                        <dd className="text-right">
                                            <strong className="block text-sm text-foreground">
                                                {point.grace_period_days} jour
                                                {point.grace_period_days === 1
                                                    ? ''
                                                    : 's'}
                                            </strong>
                                            <small className="text-[0.68rem] text-muted-foreground">
                                                Paramètre KORA OPS
                                            </small>
                                        </dd>
                                    </div>
                                </dl>
                                {organization ? (
                                    <div className="mx-5 mb-5 rounded-xl border border-gold/30 bg-gold/10 p-4 sm:mx-6">
                                        <div className="flex items-start gap-2.5">
                                            <Building2 className="mt-0.5 size-4 shrink-0 text-[var(--kora)]" />
                                            <p className="text-xs leading-5 text-foreground">
                                                Allocation de ce Point :{' '}
                                                <strong>
                                                    {formatAmount(
                                                        organization.point_allocation_amount,
                                                    )}
                                                </strong>
                                                {permissions.canViewNetworkContext ? (
                                                    <>
                                                        . Plafond global de{' '}
                                                        {organization.name} :{' '}
                                                        <strong>
                                                            {formatAmount(
                                                                organization.global_ceiling_amount,
                                                            )}
                                                        </strong>
                                                        .
                                                    </>
                                                ) : null}
                                            </p>
                                        </div>
                                    </div>
                                ) : null}
                            </Panel>

                            <Panel
                                title="Documents"
                                subtitle={
                                    attached
                                        ? 'Les pièces affichées proviennent du dossier partagé de l’organisation.'
                                        : 'Pièces disponibles dans le dossier propre à ce Point.'
                                }
                            >
                                {documents.length === 0 ? (
                                    <EmptyState>
                                        Aucun document n’est actuellement
                                        disponible dans ce dossier.
                                    </EmptyState>
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {documents.map((document) => (
                                            <li
                                                key={document.uuid}
                                                className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                                            >
                                                <div className="flex min-w-0 items-start gap-3">
                                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground">
                                                        <FileText className="size-4" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-semibold text-foreground">
                                                            {document.title}
                                                        </p>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {document.kind}
                                                            {document.reference
                                                                ? ` · ${document.reference}`
                                                                : ''}{' '}
                                                            ·{' '}
                                                            {document.source ===
                                                            'organization'
                                                                ? 'Partagé par l’organisation'
                                                                : 'Propre au Point'}
                                                        </p>
                                                        <p className="mt-1 text-[0.68rem] text-muted-foreground">
                                                            {document.signed_at
                                                                ? `Signé le ${dateLabel(document.signed_at)}`
                                                                : 'Date de signature non renseignée'}
                                                            {document.expires_at
                                                                ? ` · Expire le ${dateLabel(document.expires_at)}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                </div>
                                                <a
                                                    href={document.download_url}
                                                    className="inline-flex w-fit items-center gap-2 text-xs font-bold text-[var(--kora)] hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-ring"
                                                >
                                                    <Download className="size-3.5" />
                                                    Télécharger
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        </div>

                        <aside className="flex gap-3 rounded-2xl border border-blue-500/20 bg-blue-500/5 p-4 sm:p-5">
                            <CircleHelp className="mt-0.5 size-5 shrink-0 text-blue-700 dark:text-blue-300" />
                            <div>
                                <h2 className="text-sm font-bold text-foreground">
                                    Informations d’éligibilité disponibles
                                </h2>
                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                    Cette fiche présente les conditions et les
                                    pièces accessibles dans le dossier. Elle ne
                                    produit pas de décision d’éligibilité.
                                </p>
                                <p className="mt-2 text-xs font-semibold text-foreground">
                                    {documents.length} document
                                    {documents.length === 1 ? '' : 's'}{' '}
                                    disponible
                                    {documents.length === 1 ? '' : 's'} ·
                                    Conditions commerciales{' '}
                                    {point.commission_rate_bps > 0 &&
                                    point.credit_limit > 0 &&
                                    point.terms.settlement_calendar
                                        ? 'renseignées'
                                        : 'à vérifier'}
                                </p>
                            </div>
                        </aside>
                    </section>
                ) : null}
            </main>

            <KoraPointFormDialog
                open={editingPoint}
                point={point}
                organizations={organization_options}
                onOpenChange={(open) => setEditingPoint(open)}
            />
            <KoraTermsDialog
                open={editingTerms}
                target={{ type: 'point', uuid: point.uuid, label: point.name }}
                values={point.terms}
                allocationReadOnly={Boolean(point.organization_uuid)}
                onOpenChange={(open) => setEditingTerms(open)}
            />
            <KoraPointMemberDialog
                open={addingMember}
                pointUuid={point.uuid}
                pointName={point.name}
                pointCountry={point.country}
                onOpenChange={setAddingMember}
                onCreated={() => setActiveTab('members')}
            />
        </>
    );
}

KoraPointShowPage.layout = {
    breadcrumbs: [
        { title: 'Points de vente', href: '/kora-points' },
        { title: 'Fiche détaillée', href: '/kora-points' },
    ] satisfies BreadcrumbItem[],
};
