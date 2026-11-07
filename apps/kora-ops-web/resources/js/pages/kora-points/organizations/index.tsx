import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    ChevronRight,
    Coins,
    Gauge,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    SlidersHorizontal,
    ShieldOff,
    Store,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type {
    AdminOrganization,
    AdminPermissions,
} from '@/components/kora-points/admin/kora-admin-ui';
import { formatAmount } from '@/components/kora-points/admin/kora-admin-ui';
import { KoraOrgFormDialog } from '@/components/kora-points/admin/kora-org-form-dialog';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import organizationRoutes from '@/routes/kora-points/organizations';
import type { BreadcrumbItem } from '@/types';

type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    organizations: PaginatedData<AdminOrganization>;
    index_summary: {
        organizations: number;
        active_points: number;
        exposure: number;
        available_allocation: number;
    };
    permissions: AdminPermissions;
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    suspended: 'Suspendue',
    closed: 'Clôturée',
};

function Kpi({
    icon: Icon,
    label,
    hint,
    value,
    tone,
}: {
    icon: typeof Building2;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'digital' | 'emerald';
}) {
    return (
        <article
            className={`cash-ledger-card cash-ledger-card-kpi-${tone} group transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-[0_22px_48px_rgba(0,29,78,0.13)]`}
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

export default function KoraPointOrganizationsIndexPage({
    organizations,
    index_summary,
    permissions,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingOrg, setEditingOrg] = useState<AdminOrganization | null>(
        null,
    );
    const { url: currentUrl } = usePage();
    const urlParams = useMemo(() => {
        const queryString = currentUrl.includes('?')
            ? currentUrl.slice(currentUrl.indexOf('?') + 1)
            : '';

        return new URLSearchParams(queryString);
    }, [currentUrl]);
    const [query, setQuery] = useState(urlParams.get('q') ?? '');
    const [status, setStatus] = useState(urlParams.get('status') ?? '');

    const pagination: PaginationMeta = {
        currentPage: organizations.current_page,
        lastPage: organizations.last_page,
        perPage: organizations.per_page,
        total: organizations.total,
        from: organizations.from,
        to: organizations.to,
        baseUrl: '/kora-points/organizations',
    };

    const openCreate = () => {
        setEditingOrg(null);
        setFormOpen(true);
    };

    const openEdit = (org: AdminOrganization) => {
        setEditingOrg(org);
        setFormOpen(true);
    };

    const visit = (
        overrides: {
            q?: string;
            status?: string;
            page?: number;
        } = {},
    ) => {
        const nextQuery = overrides.q ?? query;
        const nextStatus = overrides.status ?? status;
        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextStatus !== '') {
            params.status = nextStatus;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        router.get('/kora-points/organizations', params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setQuery('');
        setStatus('');
        router.get(
            '/kora-points/organizations',
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const changeStatus = (
        org: AdminOrganization,
        status: 'active' | 'suspended' | 'closed',
    ) => {
        const action =
            status === 'closed'
                ? 'clôturer'
                : status === 'suspended'
                  ? 'suspendre'
                  : 'réactiver';
        const confirmation =
            status === 'closed'
                ? `Clôturer « ${org.name} » ? Tous les Points doivent avoir un encours nul. Les statuts et allocations des Points ne seront pas modifiés.`
                : `Voulez-vous vraiment ${action} « ${org.name} » ?`;

        if (!window.confirm(confirmation)) {
            return;
        }

        router.patch(
            organizationRoutes.status.url({ organization: org.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<AdminOrganization>[] = [
        {
            key: 'organization',
            header: 'Organisation',
            render: (org) => (
                <Link
                    href={organizationRoutes.show.url({
                        organization: org.uuid,
                    })}
                    onClick={(event) => event.stopPropagation()}
                    className="group flex min-w-0 items-center gap-3 rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
                    aria-label={`Ouvrir la fiche de ${org.name}`}
                >
                    {org.logo_url ? (
                        <img
                            src={org.logo_url}
                            alt={`Logo ${org.name}`}
                            className="size-10 shrink-0 rounded-xl bg-white object-contain ring-1 ring-border"
                        />
                    ) : (
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-kora text-[0.6875rem] font-black text-white">
                            {org.code.slice(0, 3)}
                        </span>
                    )}
                    <span className="min-w-0">
                        <strong className="block truncate font-bold text-foreground group-hover:text-[var(--kora)]">
                            {org.name}
                            <ArrowUpRight className="ml-1 inline size-3.5 opacity-0 transition-opacity group-hover:opacity-100" />
                        </strong>
                        <span className="block truncate text-xs font-semibold tracking-[0.045em] text-muted-foreground">
                            {org.code}
                            {org.address ? ` · ${org.address}` : ''}
                        </span>
                    </span>
                </Link>
            ),
        },
        {
            key: 'points',
            header: 'Points actifs / total',
            align: 'right',
            render: (org) => (
                <span className="font-bold text-foreground tabular-nums">
                    {org.active_points_count}
                    <span className="font-medium text-muted-foreground">
                        {' '}
                        / {org.points_count}
                    </span>
                </span>
            ),
        },
        {
            key: 'allocation',
            header: 'Allocations / plafond',
            align: 'right',
            render: (org) => (
                <div>
                    <span className="block font-bold text-foreground tabular-nums">
                        {formatAmount(org.allocated_credit_limit ?? 0)}
                    </span>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        sur {formatAmount(org.credit_limit)}
                    </span>
                </div>
            ),
        },
        {
            key: 'exposure',
            header: 'Encours',
            align: 'right',
            render: (org) => (
                <span className="font-bold text-foreground tabular-nums">
                    {formatAmount(org.total_exposure)}
                </span>
            ),
        },
        {
            key: 'sales',
            header: 'Ventes cumulées',
            align: 'right',
            render: (org) => (
                <span className="font-semibold text-foreground tabular-nums">
                    {formatAmount(org.total_cumulative_sales)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (org) => (
                <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-[0.6875rem] font-bold ring-1 ${
                        org.status === 'active'
                            ? 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/20 dark:text-emerald-300'
                            : org.status === 'closed'
                              ? 'bg-destructive/10 text-destructive ring-destructive/20'
                              : 'bg-muted text-muted-foreground ring-border'
                    }`}
                >
                    {statusLabels[org.status] ?? org.status}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (org) =>
                permissions.canManage ? (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions pour ${org.name}`}
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => openEdit(org)}>
                                    <Pencil className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                                {org.status === 'active' ? (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(org, 'suspended')
                                        }
                                    >
                                        <ShieldOff className="size-4" />
                                        Suspendre
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(org, 'active')
                                        }
                                    >
                                        <RotateCcw className="size-4" />
                                        Réactiver
                                    </DropdownMenuItem>
                                )}
                                {org.status !== 'closed' ? (
                                    <DropdownMenuItem
                                        disabled={org.can_close === false}
                                        title={
                                            org.can_close === false
                                                ? 'L’encours de tous les Points doit être nul avant la clôture.'
                                                : undefined
                                        }
                                        onClick={() =>
                                            changeStatus(org, 'closed')
                                        }
                                    >
                                        <ShieldOff className="size-4" />
                                        Clôturer
                                    </DropdownMenuItem>
                                ) : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ) : null,
        },
    ];

    return (
        <>
            <Head title="Organisations partenaires KORA POINT" />

            <main className="cash-page flex flex-col gap-5 pb-8">
                <header className="kora-page-head cash-reveal flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Réseau commercial / Organisations
                            partenaires
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.4rem)] leading-tight font-extrabold tracking-[-0.05em] text-foreground">
                            Organisations partenaires
                        </h1>
                        <p className="mt-1.5 max-w-2xl text-[0.8125rem] leading-6 text-muted-foreground">
                            Suivez les Points rattachés, les allocations et
                            l’encours de chaque réseau commercial.
                        </p>
                    </div>
                    {permissions.canManage ? (
                        <Button
                            onClick={openCreate}
                            className="btn btn-primary"
                        >
                            <Plus className="size-4" />
                            Nouvelle organisation
                        </Button>
                    ) : null}
                </header>

                <section
                    aria-label="Indicateurs des organisations filtrées"
                    className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <Kpi
                        icon={Building2}
                        label="Organisations"
                        hint="Résultats des filtres actifs"
                        value={index_summary.organizations}
                        tone="navy"
                    />
                    <Kpi
                        icon={Store}
                        label="Points actifs"
                        hint="Dans les organisations filtrées"
                        value={index_summary.active_points}
                        tone="digital"
                    />
                    <Kpi
                        icon={Coins}
                        label="Encours cumulé"
                        hint="Solde restant à reverser"
                        value={formatAmount(index_summary.exposure)}
                        tone="gold"
                    />
                    <Kpi
                        icon={Gauge}
                        label="Plafond disponible"
                        hint="Allocations encore possibles"
                        value={formatAmount(index_summary.available_allocation)}
                        tone="emerald"
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro !my-0">
                    <div>
                        <span className="section-kicker">
                            Réseau commercial
                        </span>
                        <h2>Liste des organisations</h2>
                    </div>
                </div>

                <div className="cash-filterbar cash-reveal cash-reveal-delay-2">
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                visit({ page: 1 });
                            }
                        }}
                        aria-label="Rechercher une organisation partenaire"
                        placeholder="Rechercher par code, nom ou adresse…"
                        className="cash-filter-search"
                    />
                    <label className="cash-filter-field relative block">
                        <span className="sr-only">Statut</span>
                        <select
                            value={status}
                            onChange={(event) => {
                                const nextStatus = event.target.value;
                                setStatus(nextStatus);
                                visit({ status: nextStatus, page: 1 });
                            }}
                            className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                        >
                            <option value="">Tous les statuts</option>
                            <option value="active">Actives</option>
                            <option value="suspended">Suspendues</option>
                            <option value="closed">Clôturées</option>
                        </select>
                        <ChevronRight
                            className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                            strokeWidth={1.7}
                        />
                    </label>
                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={() => visit({ page: 1 })}
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

                <KoraTable
                    variant="cash"
                    columns={columns}
                    data={organizations.data}
                    rowKey={(org) => org.uuid}
                    onRowClick={(org) =>
                        router.visit(
                            organizationRoutes.show.url({
                                organization: org.uuid,
                            }),
                        )
                    }
                    emptyTitle="Aucune organisation partenaire"
                    emptyMessage="Ajustez la recherche ou les filtres, ou créez une organisation pour y rattacher des Points."
                    emptyIcon={Building2}
                    pagination={pagination}
                    showHeader={false}
                />
            </main>

            <KoraOrgFormDialog
                open={formOpen}
                organization={editingOrg}
                onOpenChange={setFormOpen}
            />
        </>
    );
}

KoraPointOrganizationsIndexPage.layout = {
    breadcrumbs: [
        {
            title: 'Organisations partenaires',
            href: '/kora-points/organizations',
        } satisfies BreadcrumbItem,
    ],
};
