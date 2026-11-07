import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    BadgeCheck,
    Building2,
    CalendarClock,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Coins,
    ClipboardList,
    Download,
    FileText,
    Gauge,
    Landmark,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    RotateCcw,
    ShieldCheck,
    ShieldOff,
    Store,
    Trash2,
    Upload,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import type { PointVisit } from '@/components/kora-points/admin/field-operations-panels';
import {
    formatAmount,
    PointStatusBadge,
} from '@/components/kora-points/admin/kora-admin-ui';
import type {
    AdminOrganization,
    AdminPoint,
    UnattachedPoint,
} from '@/components/kora-points/admin/kora-admin-ui';
import { KoraOrgFormDialog } from '@/components/kora-points/admin/kora-org-form-dialog';
import { KoraOrgMemberDialog } from '@/components/kora-points/admin/kora-org-member-dialog';
import { KoraTermsDialog } from '@/components/kora-points/admin/kora-terms-dialog';
import { KoraTable } from '@/components/kora-table';
import type { TableColumn } from '@/components/kora-table';
import koraPoints from '@/routes/kora-points';
import organizationRoutes from '@/routes/kora-points/organizations';
import type { BreadcrumbItem } from '@/types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type Sale = {
    uuid: string;
    point_uuid: string;
    point_name: string;
    point_code: string;
    reservation_uuid: string;
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

type Refund = {
    uuid: string;
    sale_uuid: string;
    point_name: string;
    status: string;
    amount: number;
    commission_reversed: number;
    reason: string | null;
    executed_at: string | null;
};

type PointAllocation = { uuid: string; name: string; amount: number };

type Remittance = {
    uuid: string;
    reference: string;
    status: string;
    group_amount: number;
    received_amount: number | null;
    submitted_at: string | null;
    validated_at: string | null;
    points: PointAllocation[];
    url: string;
};

type OrganizationVisit = PointVisit & {
    point_uuid: string;
    point_name: string;
};

type Member = {
    uuid: string;
    name: string;
    email: string;
    phone: string | null;
    status: 'active' | 'suspended' | 'locked';
    role: 'kora_point_supervisor' | 'kora_point_treasurer';
    role_label: string;
};

type Document = {
    uuid: string;
    kind: string;
    title: string;
    reference: string | null;
    signed_at: string | null;
    expires_at: string | null;
    download_url: string;
};

type AssignablePoint = Pick<
    AdminPoint,
    | 'uuid'
    | 'code'
    | 'name'
    | 'city'
    | 'department'
    | 'country'
    | 'responsible_name'
    | 'phone'
    | 'address'
    | 'latitude'
    | 'longitude'
>;

type Props = {
    organization: AdminOrganization & { can_close: boolean };
    summary: {
        global_ceiling_amount: number;
        allocated_amount: number;
        available_amount: number;
        exposure_amount: number;
    };
    points: Paginated<AdminPoint>;
    sales: Paginated<Sale>;
    refunds: Paginated<Refund>;
    remittances: Paginated<Remittance>;
    visits: Paginated<OrganizationVisit>;
    members: Paginated<Member>;
    documents: Paginated<Document>;
    unattached_points: UnattachedPoint[];
    permissions: {
        canManage: boolean;
        canManageTerms: boolean;
        canManageMembers: boolean;
    };
};

const tabs = [
    { key: 'summary', label: 'Synthèse', icon: Gauge },
    { key: 'points', label: 'Points rattachés', icon: Store },
    { key: 'sales', label: 'Ventes & commissions', icon: Coins },
    { key: 'remittances', label: 'Reversements', icon: Landmark },
    { key: 'visits', label: 'Visites', icon: ClipboardList },
    { key: 'members', label: 'Membres', icon: UsersRound },
    { key: 'dossier', label: 'Dossier', icon: FileText },
] as const;

type TabKey = (typeof tabs)[number]['key'];

const statusLabels: Record<string, string> = {
    active: 'Actif',
    suspended: 'Suspendu',
    closed: 'Clôturé',
    draft: 'Brouillon',
    submitted: 'Soumis',
    reconciliation: 'À rapprocher',
    validated: 'Validé',
    rejected: 'Rejeté',
    requested: 'Demandé',
    approved: 'Autorisé',
    executed: 'Exécuté',
    confirmed: 'Confirmé',
};

function dateLabel(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              ...(value.includes('T') ? { timeStyle: 'short' as const } : {}),
          }).format(date);
}

function Kpi({
    label,
    value,
    hint,
    icon: Icon,
    tone,
}: {
    label: string;
    value: string | number;
    hint: string;
    icon: LucideIcon;
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
                        <small>{hint}</small>
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

function InfoTile({
    icon: Icon,
    label,
    children,
    tone = 'gold',
}: {
    icon: LucideIcon;
    label: string;
    children: React.ReactNode;
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
            </div>
        </div>
    );
}

function Pagination({
    page,
    label,
}: {
    page: Paginated<unknown>;
    label: string;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label={label}
            className="flex flex-wrap justify-end gap-1 border-t border-border px-4 py-3"
        >
            {page.links.map((link, index) => {
                const previous = index === 0;
                const next = index === page.links.length - 1;
                const text = link.label
                    .replace(/&laquo;|&raquo;|<[^>]*>/g, '')
                    .replace(/Previous/i, 'Précédent')
                    .replace(/Next/i, 'Suivant')
                    .trim();
                const className = `grid size-[31px] place-items-center rounded-[8px] text-[0.6875rem] font-black transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ${link.active ? 'bg-[var(--kora)] text-white' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`;

                return link.url ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        preserveScroll
                        className={className}
                        aria-current={link.active ? 'page' : undefined}
                        aria-label={
                            previous
                                ? 'Page précédente'
                                : next
                                  ? 'Page suivante'
                                  : `Page ${text}`
                        }
                    >
                        {previous ? (
                            <ChevronLeft className="size-4" />
                        ) : next ? (
                            <ChevronRight className="size-4" />
                        ) : (
                            text
                        )}
                    </Link>
                ) : (
                    <span
                        key={`${link.label}-${index}`}
                        aria-disabled="true"
                        className={`${className} opacity-40`}
                    >
                        {previous ? (
                            <ChevronLeft className="size-4" />
                        ) : next ? (
                            <ChevronRight className="size-4" />
                        ) : (
                            text
                        )}
                    </span>
                );
            })}
        </nav>
    );
}

function TablePanel<T>({
    title,
    subtitle,
    icon,
    columns,
    data,
    rowKey,
    emptyMessage,
    page,
    paginationLabel,
    actions,
}: {
    title: string;
    subtitle?: string;
    icon: LucideIcon;
    columns: TableColumn<T>[];
    data: T[];
    rowKey: (row: T) => string;
    emptyMessage: string;
    page?: Paginated<unknown>;
    paginationLabel?: string;
    actions?: React.ReactNode;
}) {
    return (
        <div className="space-y-3">
            <div className="cash-section-intro !my-0">
                <div className="min-w-0">
                    <span className="section-kicker">
                        Données de l’organisation
                    </span>
                    <h2>{title}</h2>
                    {subtitle ? (
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {actions ? <div className="actions">{actions}</div> : null}
            </div>
            <KoraTable
                variant="cash"
                columns={columns}
                data={data}
                rowKey={rowKey}
                emptyTitle="Aucun élément"
                emptyMessage={emptyMessage}
                emptyIcon={icon}
                showHeader={false}
                footer={
                    page ? (
                        <Pagination
                            page={page}
                            label={
                                paginationLabel ??
                                `Pagination ${title.toLowerCase()}`
                            }
                        />
                    ) : null
                }
            />
        </div>
    );
}

function statusLabel(status: string): string {
    return statusLabels[status] ?? status;
}

export default function KoraPointOrganizationShowPage({
    organization,
    summary,
    points,
    sales,
    refunds,
    remittances,
    visits,
    members,
    documents,
    unattached_points,
    permissions,
}: Props) {
    const [activeTab, setActiveTab] = useState<TabKey>('summary');
    const [editingOrganization, setEditingOrganization] = useState(false);
    const [editingTerms, setEditingTerms] = useState(false);
    const [addingMember, setAddingMember] = useState(false);
    const [attachUuid, setAttachUuid] = useState('');
    const [attachError, setAttachError] = useState<string | null>(null);
    const [attaching, setAttaching] = useState(false);
    const tabRefs = useRef<Array<HTMLButtonElement | null>>([]);
    const documentForm = useForm<{
        title: string;
        kind: string;
        file: File | null;
    }>({ title: '', kind: 'agreement', file: null });

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
        const confirmation =
            status === 'closed'
                ? `Clôturer « ${organization.name} » ? L’encours de chaque Point doit être nul. La clôture ne changera pas le statut ni l’allocation des Points.`
                : `Voulez-vous vraiment ${action} « ${organization.name} » ?`;

        if (!window.confirm(confirmation)) {
            return;
        }

        router.patch(
            organizationRoutes.status.url({ organization: organization.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const setPointOrganization = (
        point: AssignablePoint,
        organizationUuid: string | null,
    ) => {
        setAttachError(null);
        setAttaching(true);
        router.put(
            koraPoints.update.url({ point: point.uuid }),
            {
                code: point.code,
                name: point.name,
                city: point.city,
                department: point.department,
                country: point.country,
                responsible_name: point.responsible_name,
                phone: point.phone,
                address: point.address,
                latitude: point.latitude,
                longitude: point.longitude,
                organization_uuid: organizationUuid,
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setAttachError(
                        Object.values(errors)[0] ??
                            'Le rattachement a été refusé.',
                    ),
                onFinish: () => setAttaching(false),
            },
        );
    };

    const attachPoint = () => {
        const point = unattached_points.find(
            (item) => item.uuid === attachUuid,
        );

        if (!point) {
            return;
        }

        setPointOrganization(
            {
                ...point,
                department: point.department ?? null,
            },
            organization.uuid,
        );
        setAttachUuid('');
    };

    const uploadDocument = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        documentForm.post(
            organizationRoutes.documents.url({
                organization: organization.uuid,
            }),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => documentForm.reset(),
            },
        );
    };

    const pointColumns: TableColumn<AdminPoint>[] = [
        {
            key: 'point',
            header: 'Point de vente',
            render: (point) => (
                <Link
                    href={koraPoints.show.url({ point: point.uuid })}
                    className="group inline-flex min-w-0 flex-col rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <strong className="font-bold text-foreground group-hover:text-[var(--kora)]">
                        {point.name}
                        <ArrowUpRight className="ml-1 inline size-3.5 opacity-0 transition-opacity group-hover:opacity-100" />
                    </strong>
                    <span className="text-xs text-muted-foreground">
                        {point.code} · {point.city}
                    </span>
                </Link>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (point) => <PointStatusBadge status={point.status} />,
        },
        {
            key: 'responsible',
            header: 'Responsable',
            render: (point) => (
                <span className="block min-w-[8rem]">
                    <span className="block font-semibold text-foreground">
                        {point.responsible_name || 'Non renseigné'}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {point.phone || 'Téléphone non renseigné'}
                    </span>
                </span>
            ),
        },
        {
            key: 'exposure',
            header: 'Encours',
            align: 'right',
            render: (point) => (
                <span className="font-semibold text-foreground tabular-nums">
                    {formatAmount(point.current_exposure)}
                </span>
            ),
        },
        {
            key: 'allocation',
            header: 'Allocation du Point',
            align: 'right',
            render: (point) => (
                <span className="font-bold text-foreground tabular-nums">
                    {formatAmount(point.credit_limit)}
                </span>
            ),
        },
        {
            key: 'usage',
            header: 'Utilisation',
            align: 'right',
            render: (point) => (
                <span className="font-semibold text-muted-foreground tabular-nums">
                    {point.usage_percentage}%
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (point) =>
                permissions.canManage ? (
                    <button
                        type="button"
                        className="btn btn-ghost btn-xs"
                        title="Détacher le Point"
                        onClick={() => {
                            if (
                                window.confirm(
                                    `Détacher « ${point.name} » de ${organization.name} ? Le Point doit être soldé et sans opération en cours.`,
                                )
                            ) {
                                setPointOrganization(point, null);
                            }
                        }}
                    >
                        <Trash2 className="size-3.5" />
                        Détacher
                    </button>
                ) : null,
        },
    ];

    const saleColumns: TableColumn<Sale>[] = [
        {
            key: 'sale',
            header: 'Vente',
            render: (sale) => (
                <span>
                    <strong className="block font-bold text-foreground">
                        {sale.reservation_uuid.slice(0, 8).toUpperCase()}
                    </strong>
                    <span className="text-xs text-muted-foreground">
                        {dateLabel(sale.paid_at)}
                    </span>
                </span>
            ),
        },
        {
            key: 'point',
            header: 'Point',
            render: (sale) => (
                <span>
                    <span className="block font-semibold text-foreground">
                        {sale.point_name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {sale.point_code}
                    </span>
                </span>
            ),
        },
        {
            key: 'gross',
            header: 'Vente brute',
            align: 'right',
            render: (sale) => (
                <span className="font-semibold text-foreground tabular-nums">
                    {formatAmount(sale.gross_amount)}
                </span>
            ),
        },
        {
            key: 'commission',
            header: 'Commission',
            align: 'right',
            render: (sale) => (
                <span className="font-semibold text-foreground tabular-nums">
                    {formatAmount(sale.commission_amount)}
                </span>
            ),
        },
        {
            key: 'correction',
            header: 'Commission corrigée',
            align: 'right',
            render: (sale) => (
                <span className="font-semibold text-destructive tabular-nums">
                    {sale.commission_reversed > 0
                        ? `−${formatAmount(sale.commission_reversed)}`
                        : '—'}
                </span>
            ),
        },
        {
            key: 'outstanding',
            header: 'Encours',
            align: 'right',
            render: (sale) => (
                <span className="font-bold text-foreground tabular-nums">
                    {formatAmount(sale.outstanding_amount)}
                </span>
            ),
        },
    ];

    const refundColumns: TableColumn<Refund>[] = [
        {
            key: 'refund',
            header: 'Remboursement',
            render: (refund) => (
                <span>
                    <strong className="block font-bold text-foreground">
                        {refund.uuid.slice(0, 8).toUpperCase()}
                    </strong>
                    <span className="text-xs text-muted-foreground">
                        {refund.point_name}
                    </span>
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (refund) => (
                <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-bold text-muted-foreground ring-1 ring-border">
                    {statusLabel(refund.status)}
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Montant remboursé',
            align: 'right',
            render: (refund) => (
                <span className="font-semibold text-foreground tabular-nums">
                    {formatAmount(refund.amount)}
                </span>
            ),
        },
        {
            key: 'commission_reversed',
            header: 'Correction de commission',
            align: 'right',
            render: (refund) => (
                <span className="font-bold text-destructive tabular-nums">
                    {refund.commission_reversed > 0
                        ? `−${formatAmount(refund.commission_reversed)}`
                        : '—'}
                </span>
            ),
        },
        {
            key: 'date',
            header: 'Exécuté le',
            render: (refund) => dateLabel(refund.executed_at),
        },
    ];

    const remittanceColumns: TableColumn<Remittance>[] = [
        {
            key: 'reference',
            header: 'Reversement',
            render: (remittance) => (
                <Link
                    href={remittance.url}
                    className="font-bold text-foreground underline-offset-4 hover:text-[var(--kora)] hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-ring"
                >
                    {remittance.reference}
                    <ArrowUpRight className="ml-1 inline size-3.5" />
                </Link>
            ),
        },
        {
            key: 'points',
            header: 'Points et montants attribués',
            render: (remittance) => (
                <span className="block min-w-[16rem] space-y-0.5">
                    {remittance.points.map((point) => (
                        <span
                            key={point.uuid}
                            className="flex items-center justify-between gap-3 text-xs"
                        >
                            <span className="truncate text-foreground">
                                {point.name}
                            </span>
                            <strong className="shrink-0 text-foreground tabular-nums">
                                {formatAmount(point.amount)}
                            </strong>
                        </span>
                    ))}
                    {remittance.points.length === 0 ? (
                        <span className="text-xs text-muted-foreground">
                            Aucune ligne attribuée à cette organisation
                        </span>
                    ) : null}
                </span>
            ),
        },
        {
            key: 'group_amount',
            header: 'Total du groupe',
            align: 'right',
            render: (remittance) => (
                <span>
                    <strong className="block font-bold text-foreground tabular-nums">
                        {formatAmount(remittance.group_amount)}
                    </strong>
                    <span className="text-xs text-muted-foreground">
                        Montant global du bordereau
                    </span>
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (remittance) => (
                <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-bold text-muted-foreground ring-1 ring-border">
                    {statusLabel(remittance.status)}
                </span>
            ),
        },
        {
            key: 'date',
            header: 'Soumis le',
            render: (remittance) => dateLabel(remittance.submitted_at),
        },
    ];

    const memberColumns: TableColumn<Member>[] = [
        {
            key: 'member',
            header: 'Membre',
            render: (member) => (
                <span>
                    <strong className="block font-bold text-foreground">
                        {member.name}
                    </strong>
                    <span className="text-xs text-muted-foreground">
                        {member.role_label}
                    </span>
                </span>
            ),
        },
        {
            key: 'email',
            header: 'Adresse e-mail',
            render: (member) => (
                <span className="inline-flex items-center gap-2 text-foreground">
                    <Mail className="size-3.5 text-muted-foreground" />
                    {member.email}
                </span>
            ),
        },
        {
            key: 'phone',
            header: 'Téléphone',
            render: (member) => (
                <span className="inline-flex items-center gap-2 text-muted-foreground">
                    <Phone className="size-3.5" />
                    {member.phone || 'Non renseigné'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut du compte',
            render: (member) => (
                <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-[0.6875rem] font-bold ring-1 ${
                        member.status === 'active'
                            ? 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-300'
                            : 'bg-muted text-muted-foreground ring-border'
                    }`}
                >
                    {statusLabel(member.status)}
                </span>
            ),
        },
    ];

    const documentColumns: TableColumn<Document>[] = [
        {
            key: 'document',
            header: 'Document partagé',
            render: (document) => (
                <span>
                    <strong className="block font-bold text-foreground">
                        {document.title}
                    </strong>
                    <span className="text-xs text-muted-foreground">
                        {document.kind.replaceAll('_', ' ')}
                    </span>
                </span>
            ),
        },
        {
            key: 'signed_at',
            header: 'Signé le',
            render: (document) => dateLabel(document.signed_at),
        },
        {
            key: 'expires_at',
            header: 'Expire le',
            render: (document) => dateLabel(document.expires_at),
        },
        {
            key: 'download',
            header: 'Document',
            align: 'right',
            render: (document) => (
                <a
                    href={document.download_url}
                    className="btn btn-navy btn-xs"
                    aria-label={`Télécharger ${document.title}`}
                >
                    <Download className="size-3.5" />
                    Télécharger
                </a>
            ),
        },
    ];

    return (
        <>
            <Head title={`${organization.name} — Organisation KORA POINT`} />

            <main className="cash-page flex flex-col gap-5 pb-8">
                <header className="kora-page-head cash-reveal flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div className="min-w-0">
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            <Link
                                href="/kora-points/organizations"
                                className="hover:text-foreground"
                            >
                                KORA OPS / Réseau commercial / Organisations
                            </Link>
                            {' / '}
                            {organization.code}
                        </div>
                        <div className="mb-1.5 flex flex-wrap items-center gap-2">
                            <span className="text-[0.625rem] font-black tracking-[0.14em] text-[var(--digital)] uppercase">
                                KORA POINT · ORGANISATION
                            </span>
                            <span
                                className={`rounded-full px-2.5 py-1 text-[0.6875rem] font-bold ring-1 ${
                                    organization.status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-300'
                                        : organization.status === 'closed'
                                          ? 'bg-destructive/10 text-destructive ring-destructive/20'
                                          : 'bg-muted text-muted-foreground ring-border'
                                }`}
                            >
                                {statusLabel(organization.status)}
                            </span>
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] leading-tight font-extrabold tracking-[-0.055em] text-foreground">
                            {organization.name}
                        </h1>
                        <p className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.8125rem] leading-6 text-muted-foreground">
                            <span className="inline-flex items-center gap-1.5">
                                <MapPin className="size-3.5 shrink-0" />
                                {[organization.address, organization.country]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span className="inline-flex items-center gap-1.5">
                                <Store className="size-3.5 shrink-0" />
                                {organization.active_points_count} Point(s)
                                actif(s) sur {organization.points_count}
                            </span>
                            {organization.phone ? (
                                <>
                                    <span aria-hidden="true">·</span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <Phone className="size-3.5 shrink-0" />
                                        {organization.phone}
                                    </span>
                                </>
                            ) : null}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Link
                            href="/kora-points/organizations"
                            className="btn btn-navy"
                        >
                            <ArrowLeft className="size-4" />
                            Retour
                        </Link>
                        {permissions.canManage ? (
                            <>
                                <button
                                    type="button"
                                    onClick={() => setEditingOrganization(true)}
                                    className="btn btn-primary"
                                >
                                    <Pencil className="size-4" />
                                    Modifier
                                </button>
                                {organization.status === 'active' ? (
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
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => changeStatus('active')}
                                        className="btn btn-success"
                                    >
                                        <RotateCcw className="size-4" />
                                        Réactiver
                                    </button>
                                )}
                                {organization.status !== 'closed' ? (
                                    <button
                                        type="button"
                                        disabled={!organization.can_close}
                                        title={
                                            organization.can_close
                                                ? undefined
                                                : 'Chaque Point doit avoir un encours nul avant la clôture.'
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

                <section
                    aria-label="Indicateurs financiers de l’organisation"
                    className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <Kpi
                        icon={Landmark}
                        tone="navy"
                        label="Plafond global"
                        value={formatAmount(summary.global_ceiling_amount)}
                        hint="Limite contractuelle de l’organisation"
                    />
                    <Kpi
                        icon={Store}
                        tone="digital"
                        label="Allocations réservées"
                        value={formatAmount(summary.allocated_amount)}
                        hint="Capacité retenue par les Points"
                    />
                    <Kpi
                        icon={Gauge}
                        tone="emerald"
                        label="Disponible à répartir"
                        value={formatAmount(summary.available_amount)}
                        hint="Plafond global moins allocations"
                    />
                    <Kpi
                        icon={Coins}
                        tone="gold"
                        label="Encours"
                        value={formatAmount(summary.exposure_amount)}
                        hint="Solde restant à reverser"
                    />
                </section>

                <nav
                    role="tablist"
                    aria-label={`Espaces de la fiche de ${organization.name}`}
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
                                id={`organization-tab-${tab.key}`}
                                type="button"
                                role="tab"
                                aria-selected={activeTab === tab.key}
                                aria-controls={`organization-panel-${tab.key}`}
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
                                {tab.key === 'members' ? (
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
                        id="organization-panel-summary"
                        role="tabpanel"
                        aria-labelledby="organization-tab-summary"
                        tabIndex={0}
                        className="grid gap-8 focus-visible:outline-2 focus-visible:outline-ring xl:grid-cols-2 xl:gap-10"
                    >
                        <section aria-labelledby="organization-profile-heading">
                            <div className="mb-4 flex items-center gap-3">
                                <span className="grid size-9 place-items-center rounded-xl bg-gold/15 text-[var(--kora)]">
                                    <Building2 className="size-[17px]" />
                                </span>
                                <div>
                                    <h2
                                        id="organization-profile-heading"
                                        className="text-base font-extrabold tracking-tight text-foreground"
                                    >
                                        Profil & contact
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Identité de l’organisation partenaire.
                                    </p>
                                </div>
                            </div>
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <InfoTile
                                    icon={Building2}
                                    label="Raison sociale"
                                    tone="navy"
                                >
                                    {organization.legal_name}
                                </InfoTile>
                                <InfoTile
                                    icon={MapPin}
                                    label="Pays / siège"
                                    tone="digital"
                                >
                                    {[
                                        organization.headquarters_city,
                                        organization.country,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || 'Non renseigné'}
                                </InfoTile>
                                <InfoTile
                                    icon={Phone}
                                    label="Téléphone"
                                    tone="emerald"
                                >
                                    {organization.phone || 'Non renseigné'}
                                </InfoTile>
                                <InfoTile
                                    icon={MapPin}
                                    label="Adresse"
                                    tone="violet"
                                >
                                    {organization.address || 'Non renseignée'}
                                </InfoTile>
                                <InfoTile
                                    icon={BadgeCheck}
                                    label="Immatriculation commerciale"
                                    tone="gold"
                                >
                                    {organization.registration_identifier ||
                                        'Non renseignée'}
                                </InfoTile>
                                <InfoTile
                                    icon={ShieldCheck}
                                    label="Identifiant fiscal"
                                    tone="navy"
                                >
                                    {organization.tax_identifier ||
                                        'Non renseigné'}
                                </InfoTile>
                            </dl>
                        </section>

                        <section aria-labelledby="organization-terms-heading">
                            <div className="mb-4 flex items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <span className="grid size-9 place-items-center rounded-xl bg-cyan-500/10 text-cyan-700 dark:text-cyan-300">
                                        <Coins className="size-[17px]" />
                                    </span>
                                    <div>
                                        <h2
                                            id="organization-terms-heading"
                                            className="text-base font-extrabold tracking-tight text-foreground"
                                        >
                                            Conditions cadres
                                        </h2>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Appliquées aux Points selon leurs
                                            ventes.
                                        </p>
                                    </div>
                                </div>
                                {permissions.canManageTerms ? (
                                    <button
                                        type="button"
                                        onClick={() => setEditingTerms(true)}
                                        className="btn btn-primary btn-sm"
                                    >
                                        <Pencil className="size-3.5" />
                                        Conditions
                                    </button>
                                ) : null}
                            </div>
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <InfoTile
                                    icon={Coins}
                                    label="Commission"
                                    tone="gold"
                                >
                                    {(
                                        organization.default_terms
                                            .commission_rate_bps / 100
                                    ).toLocaleString('fr-FR', {
                                        maximumFractionDigits: 2,
                                    })}{' '}
                                    %
                                </InfoTile>
                                <InfoTile
                                    icon={Landmark}
                                    label="Plafond global"
                                    tone="navy"
                                >
                                    {formatAmount(
                                        organization.default_terms.credit_limit,
                                    )}
                                </InfoTile>
                                <InfoTile
                                    icon={CalendarClock}
                                    label="Fréquence de reversement"
                                    tone="digital"
                                >
                                    {organization.default_terms
                                        .settlement_frequency_days === 1
                                        ? 'Quotidienne'
                                        : organization.default_terms
                                                .settlement_frequency_days ===
                                            30
                                          ? 'Mensuelle'
                                          : 'Hebdomadaire'}
                                </InfoTile>
                                <InfoTile
                                    icon={CalendarClock}
                                    label="Délai de grâce"
                                    tone="emerald"
                                >
                                    {
                                        organization.default_terms
                                            .grace_period_days
                                    }{' '}
                                    jours
                                </InfoTile>
                                <InfoTile
                                    icon={CalendarClock}
                                    label="Heure de reversement"
                                    tone="violet"
                                >
                                    {organization.settlement_calendar?.time ??
                                        'Non renseignée'}
                                </InfoTile>
                                <InfoTile
                                    icon={UsersRound}
                                    label="Membres autorisés"
                                    tone="gold"
                                >
                                    {members.total}
                                </InfoTile>
                            </dl>
                        </section>
                    </section>
                ) : null}

                {activeTab === 'points' ? (
                    <section
                        id="organization-panel-points"
                        role="tabpanel"
                        aria-labelledby="organization-tab-points"
                        tabIndex={0}
                        className="space-y-4 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        {permissions.canManage ? (
                            <div className="cash-table-card p-4">
                                <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                    <label className="field">
                                        <span className="inline-flex items-center gap-1.5">
                                            <Store className="size-3.5 text-gold" />
                                            Point autonome à rattacher
                                            <span
                                                className="required-asterisk"
                                                aria-hidden="true"
                                            >
                                                *
                                            </span>
                                        </span>
                                        <span className="relative block">
                                            <select
                                                className="control w-full appearance-none pr-9"
                                                value={attachUuid}
                                                onChange={(event) =>
                                                    setAttachUuid(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="" disabled>
                                                    Choisir un Point autonome
                                                </option>
                                                {unattached_points.map(
                                                    (point) => (
                                                        <option
                                                            key={point.uuid}
                                                            value={point.uuid}
                                                        >
                                                            {point.name} (
                                                            {point.code}) ·{' '}
                                                            {point.city}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        </span>
                                    </label>
                                    <button
                                        type="button"
                                        className="btn btn-primary"
                                        disabled={!attachUuid || attaching}
                                        onClick={attachPoint}
                                    >
                                        <Plus className="size-4" />
                                        {attaching
                                            ? 'Rattachement…'
                                            : 'Rattacher'}
                                    </button>
                                </div>
                                {unattached_points.length === 0 ? (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        Aucun Point autonome compatible dans ce
                                        pays.
                                    </p>
                                ) : null}
                                {attachError ? (
                                    <p className="mt-3 rounded-lg border border-destructive/25 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                        {attachError}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                        <TablePanel
                            title="Points rattachés"
                            subtitle="Les allocations sont en lecture seule dans KORA OPS et restent gérées depuis l’espace de l’organisation."
                            icon={Store}
                            columns={pointColumns}
                            data={points.data}
                            rowKey={(point) => point.uuid}
                            emptyMessage="Aucun Point n’est encore rattaché à cette organisation."
                            page={points}
                            paginationLabel="Pagination des Points rattachés"
                        />
                    </section>
                ) : null}

                {activeTab === 'sales' ? (
                    <section
                        id="organization-panel-sales"
                        role="tabpanel"
                        aria-labelledby="organization-tab-sales"
                        tabIndex={0}
                        className="space-y-5 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <TablePanel
                            title="Ventes & commissions cumulées"
                            subtitle="Historique depuis l’ouverture du réseau, avec corrections enregistrées à la suite des remboursements."
                            icon={Coins}
                            columns={saleColumns}
                            data={sales.data}
                            rowKey={(sale) => sale.uuid}
                            emptyMessage="Aucune vente n’est encore enregistrée pour cette organisation."
                            page={sales}
                            paginationLabel="Pagination des ventes"
                        />
                        <TablePanel
                            title="Remboursements et corrections"
                            subtitle="Chaque correction correspond à une commission reversée à la suite d’un remboursement."
                            icon={RotateCcw}
                            columns={refundColumns}
                            data={refunds.data}
                            rowKey={(refund) => refund.uuid}
                            emptyMessage="Aucun remboursement n’est enregistré pour cette organisation."
                            page={refunds}
                            paginationLabel="Pagination des remboursements"
                        />
                    </section>
                ) : null}

                {activeTab === 'remittances' ? (
                    <section
                        id="organization-panel-remittances"
                        role="tabpanel"
                        aria-labelledby="organization-tab-remittances"
                        tabIndex={0}
                        className="focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <TablePanel
                            title="Reversements groupés"
                            subtitle="Le total du bordereau est distingué des montants attribués à chaque Point rattaché."
                            icon={Landmark}
                            columns={remittanceColumns}
                            data={remittances.data}
                            rowKey={(remittance) => remittance.uuid}
                            emptyMessage="Aucun reversement groupé n’est enregistré pour cette organisation."
                            page={remittances}
                            paginationLabel="Pagination des reversements"
                        />
                    </section>
                ) : null}

                {activeTab === 'visits' ? (
                    <section
                        id="organization-panel-visits"
                        role="tabpanel"
                        aria-labelledby="organization-tab-visits"
                        tabIndex={0}
                        className="focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <TablePanel
                            title="Visites des Points rattachés"
                            subtitle="Consultation des visites terrain et de leurs actions sur le réseau de l’organisation."
                            icon={ClipboardList}
                            columns={[
                                {
                                    key: 'point_name',
                                    header: 'Point',
                                    render: (visit) => (
                                        <span className="font-semibold">
                                            {visit.point_name}
                                        </span>
                                    ),
                                },
                                {
                                    key: 'visited_at',
                                    header: 'Date',
                                    render: (visit) =>
                                        dateLabel(visit.visited_at),
                                },
                                {
                                    key: 'type',
                                    header: 'Type',
                                    render: (visit) =>
                                        ({
                                            quality: 'Visite qualité',
                                            commercial_follow_up:
                                                'Suivi commercial',
                                            plv_control: 'Contrôle PLV',
                                        })[visit.type] ?? visit.type,
                                },
                                {
                                    key: 'score',
                                    header: 'Note',
                                    align: 'right',
                                    render: (visit) => `${visit.score}/10`,
                                },
                                {
                                    key: 'report',
                                    header: 'Compte rendu',
                                    render: (visit) => (
                                        <details className="max-w-sm">
                                            <summary className="cursor-pointer text-primary">
                                                Lire
                                            </summary>
                                            <p className="mt-2 text-xs leading-5 whitespace-pre-wrap">
                                                {visit.report}
                                            </p>
                                            <ul className="mt-2 space-y-1">
                                                {visit.actions.map((action) => (
                                                    <li
                                                        key={action.uuid}
                                                        className="text-xs"
                                                    >
                                                        {action.label} ·{' '}
                                                        {action.status}
                                                    </li>
                                                ))}
                                            </ul>
                                            {visit.photos.map((photo) => (
                                                <a
                                                    key={photo.uuid}
                                                    href={photo.download_url}
                                                    className="mr-2 text-xs text-primary underline"
                                                >
                                                    {photo.name}
                                                </a>
                                            ))}
                                        </details>
                                    ),
                                },
                            ]}
                            data={visits.data}
                            rowKey={(visit) => visit.uuid}
                            emptyMessage="Aucune visite terrain n’a été enregistrée pour les Points de cette organisation."
                            page={visits}
                            paginationLabel="Pagination des visites de l’organisation"
                        />
                    </section>
                ) : null}

                {activeTab === 'members' ? (
                    <section
                        id="organization-panel-members"
                        role="tabpanel"
                        aria-labelledby="organization-tab-members"
                        tabIndex={0}
                        className="focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <TablePanel
                            title="Membres de l’organisation"
                            subtitle="Superviseurs et trésoriers habilités à accéder à l’espace partenaire."
                            icon={UsersRound}
                            columns={memberColumns}
                            data={members.data}
                            rowKey={(member) => member.uuid}
                            emptyMessage="Aucun superviseur ou trésorier n’est affecté à cette organisation."
                            page={members}
                            paginationLabel="Pagination des membres"
                            actions={
                                permissions.canManageMembers ? (
                                    <button
                                        type="button"
                                        className="btn btn-primary btn-sm"
                                        onClick={() => setAddingMember(true)}
                                        disabled={
                                            organization.status !== 'active'
                                        }
                                        title={
                                            organization.status !== 'active'
                                                ? 'Réactivez l’organisation avant d’ajouter un membre.'
                                                : undefined
                                        }
                                    >
                                        <Plus className="size-3.5" />
                                        Nouveau membre
                                    </button>
                                ) : null
                            }
                        />
                    </section>
                ) : null}

                {activeTab === 'dossier' ? (
                    <section
                        id="organization-panel-dossier"
                        role="tabpanel"
                        aria-labelledby="organization-tab-dossier"
                        tabIndex={0}
                        className="space-y-5 focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <section className="cash-table-card">
                            <div className="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 className="text-[0.9375rem] font-bold text-foreground">
                                        Conditions commerciales cadres
                                    </h2>
                                    <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                        Conditions partagées et héritées par les
                                        Points rattachés.
                                    </p>
                                </div>
                                {permissions.canManageTerms ? (
                                    <button
                                        type="button"
                                        className="btn btn-primary btn-sm"
                                        onClick={() => setEditingTerms(true)}
                                    >
                                        <Pencil className="size-3.5" />
                                        Modifier les conditions
                                    </button>
                                ) : null}
                            </div>
                            <dl className="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4">
                                <InfoTile
                                    icon={Coins}
                                    label="Commission par défaut"
                                    tone="gold"
                                >
                                    {(
                                        organization.default_terms
                                            .commission_rate_bps / 100
                                    ).toLocaleString('fr-FR', {
                                        maximumFractionDigits: 2,
                                    })}{' '}
                                    %
                                </InfoTile>
                                <InfoTile
                                    icon={Landmark}
                                    label="Plafond global"
                                    tone="navy"
                                >
                                    {formatAmount(
                                        organization.default_terms.credit_limit,
                                    )}
                                </InfoTile>
                                <InfoTile
                                    icon={CalendarClock}
                                    label="Calendrier"
                                    tone="digital"
                                >
                                    {organization.default_terms
                                        .settlement_frequency_days === 1
                                        ? 'Quotidien'
                                        : organization.default_terms
                                                .settlement_frequency_days ===
                                            30
                                          ? 'Mensuel'
                                          : 'Hebdomadaire'}
                                    {organization.settlement_calendar?.time
                                        ? ` · ${organization.settlement_calendar.time}`
                                        : ''}
                                </InfoTile>
                                <InfoTile
                                    icon={CalendarClock}
                                    label="Délai de grâce"
                                    tone="emerald"
                                >
                                    {
                                        organization.default_terms
                                            .grace_period_days
                                    }{' '}
                                    jours
                                </InfoTile>
                            </dl>
                        </section>

                        <form
                            onSubmit={uploadDocument}
                            className="cash-table-card p-4"
                        >
                            <div className="mb-4">
                                <h2 className="text-[0.9375rem] font-bold text-foreground">
                                    Documents partagés
                                </h2>
                                <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                    Les pièces de ce dossier sont partagées avec
                                    les Points rattachés.
                                </p>
                            </div>
                            {permissions.canManage ? (
                                <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)_auto] sm:items-end">
                                    <label className="field">
                                        <span className="inline-flex items-center gap-1.5">
                                            <FileText className="size-3.5 text-gold" />
                                            Titre du document
                                            <span
                                                className="required-asterisk"
                                                aria-hidden="true"
                                            >
                                                *
                                            </span>
                                        </span>
                                        <input
                                            className="control"
                                            value={documentForm.data.title}
                                            onChange={(event) =>
                                                documentForm.setData(
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                documentForm.errors.title,
                                            )}
                                            required
                                        />
                                        {documentForm.errors.title ? (
                                            <small className="field-error-text">
                                                {documentForm.errors.title}
                                            </small>
                                        ) : null}
                                    </label>
                                    <label className="field">
                                        <span className="inline-flex items-center gap-1.5">
                                            <BadgeCheck className="size-3.5 text-gold" />
                                            Type de pièce
                                            <span
                                                className="required-asterisk"
                                                aria-hidden="true"
                                            >
                                                *
                                            </span>
                                        </span>
                                        <select
                                            className="control"
                                            value={documentForm.data.kind}
                                            onChange={(event) =>
                                                documentForm.setData(
                                                    'kind',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        >
                                            <option value="agreement">
                                                Accord-cadre
                                            </option>
                                            <option value="identity">
                                                Identité
                                            </option>
                                            <option value="address">
                                                Adresse
                                            </option>
                                            <option value="bank_details">
                                                Coordonnées bancaires
                                            </option>
                                            <option value="other">Autre</option>
                                        </select>
                                    </label>
                                    <label className="field">
                                        <span className="inline-flex items-center gap-1.5">
                                            <Upload className="size-3.5 text-gold" />
                                            Fichier (PDF, JPG, PNG)
                                            <span
                                                className="required-asterisk"
                                                aria-hidden="true"
                                            >
                                                *
                                            </span>
                                        </span>
                                        <input
                                            className="control"
                                            type="file"
                                            accept="application/pdf,image/jpeg,image/png"
                                            onChange={(event) =>
                                                documentForm.setData(
                                                    'file',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                documentForm.errors.file,
                                            )}
                                            required
                                        />
                                        {documentForm.errors.file ? (
                                            <small className="field-error-text">
                                                {documentForm.errors.file}
                                            </small>
                                        ) : null}
                                    </label>
                                    <button
                                        type="submit"
                                        className="btn btn-primary"
                                        disabled={
                                            !documentForm.data.title.trim() ||
                                            !documentForm.data.file ||
                                            documentForm.processing
                                        }
                                    >
                                        <Upload className="size-4" />
                                        Ajouter
                                    </button>
                                </div>
                            ) : null}
                        </form>

                        <TablePanel
                            title="Pièces du dossier"
                            subtitle={`${documents.total} document${documents.total === 1 ? '' : 's'} partagé${documents.total === 1 ? '' : 's'}`}
                            icon={FileText}
                            columns={documentColumns}
                            data={documents.data}
                            rowKey={(document) => document.uuid}
                            emptyMessage="Aucun document n’est partagé pour cette organisation."
                            page={documents}
                            paginationLabel="Pagination des documents partagés"
                        />
                    </section>
                ) : null}
            </main>

            <KoraOrgFormDialog
                open={editingOrganization}
                organization={organization}
                onOpenChange={setEditingOrganization}
            />
            <KoraTermsDialog
                open={editingTerms}
                target={{
                    type: 'organization',
                    uuid: organization.uuid,
                    label: organization.name,
                }}
                values={{
                    commission_basis_points:
                        organization.commission_basis_points,
                    ceiling_amount: organization.ceiling_amount,
                    settlement_calendar: organization.settlement_calendar,
                }}
                onOpenChange={setEditingTerms}
            />
            <KoraOrgMemberDialog
                open={addingMember}
                organizationUuid={organization.uuid}
                organizationName={organization.name}
                country={organization.country}
                onOpenChange={setAddingMember}
            />
        </>
    );
}

KoraPointOrganizationShowPage.layout = {
    breadcrumbs: [
        {
            title: 'Organisations partenaires',
            href: '/kora-points/organizations',
        } satisfies BreadcrumbItem,
    ],
};
