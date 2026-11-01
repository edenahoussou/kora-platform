import { Head, router } from '@inertiajs/react';
import {
    Building2,
    Coins,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    ShieldOff,
    Store,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import type {
    AdminOrganization,
    AdminPermissions,
    OrgSubPoint,
    UnattachedPoint,
} from '@/components/kora-points/admin/kora-admin-ui';
import {
    formatAmount,
    KpiCard,
} from '@/components/kora-points/admin/kora-admin-ui';
import { KoraOrgDetailSheet } from '@/components/kora-points/admin/kora-org-detail-sheet';
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
    subpoints: OrgSubPoint[];
    unattached_points: UnattachedPoint[];
    permissions: AdminPermissions;
};

const frequencyLabels: Record<number, string> = {
    1: 'Quotidien',
    7: 'Hebdomadaire',
    30: 'Mensuel',
};

const filterConfigs: FilterConfig[] = [
    {
        name: 'status',
        label: 'Statut',
        placeholder: 'Tous les statuts',
        options: [
            { value: 'active', label: 'Actives' },
            { value: 'suspended', label: 'Suspendues' },
            { value: 'closed', label: 'Clôturées' },
        ],
    },
];

export default function KoraPointOrganizationsIndexPage({
    organizations,
    subpoints,
    unattached_points,
    permissions,
}: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingOrg, setEditingOrg] = useState<AdminOrganization | null>(
        null,
    );
    const [detailOrg, setDetailOrg] = useState<AdminOrganization | null>(null);

    const stats = useMemo(() => {
        let totalPoints = 0;
        let totalExposure = 0;

        organizations.data.forEach((org) => {
            totalPoints += org.points_count;
            totalExposure += org.total_exposure;
        });

        return { totalPoints, totalExposure };
    }, [organizations.data]);

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

    const changeStatus = (
        org: AdminOrganization,
        status: 'active' | 'suspended',
    ) => {
        const action = status === 'suspended' ? 'suspendre' : 'réactiver';

        if (
            !window.confirm(`Voulez-vous vraiment ${action} « ${org.name} » ?`)
        ) {
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
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-kora text-[0.6875rem] font-black text-white">
                        {org.code.slice(0, 3)}
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {org.name}
                        </strong>
                        <span className="text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                            {org.code}
                            {org.address ? ` · ${org.address}` : ''}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'points',
            header: 'Points rattachés',
            align: 'right',
            render: (org) => (
                <div>
                    <span className="block font-bold text-foreground">
                        {org.points_count}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {org.active_points_count} actif(s)
                    </span>
                </div>
            ),
        },
        {
            key: 'terms',
            header: 'Conditions cadres',
            render: (org) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {(
                            org.default_terms.commission_rate_bps / 100
                        ).toLocaleString('fr-FR', {
                            maximumFractionDigits: 2,
                        })}
                        {' % commission'}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Plafond {formatAmount(org.default_terms.credit_limit)} ·{' '}
                        {frequencyLabels[
                            org.default_terms.settlement_frequency_days
                        ] ?? 'Hebdomadaire'}
                    </span>
                </div>
            ),
        },
        {
            key: 'exposure',
            header: 'Encours réseau',
            align: 'right',
            render: (org) => (
                <span className="font-bold text-foreground">
                    {formatAmount(org.total_exposure)}
                </span>
            ),
        },
        {
            key: 'sales',
            header: 'Ventes cumulées',
            align: 'right',
            render: (org) => (
                <span className="font-medium text-foreground">
                    {formatAmount(org.total_monthly_sales)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (org) => (
                <span
                    className={
                        org.status === 'active'
                            ? 'inline-flex rounded-full bg-emerald-500/10 px-2.5 py-1 text-[0.6875rem] font-bold text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-300'
                            : 'inline-flex rounded-full bg-muted px-2.5 py-1 text-[0.6875rem] font-bold text-muted-foreground ring-1 ring-border'
                    }
                >
                    {org.status === 'active' ? 'Active' : 'Suspendue'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (org) =>
                permissions.canManage && (
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
                                {org.status !== 'suspended' ? (
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
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                ),
        },
    ];

    return (
        <>
            <Head title="Organisations partenaires KORA POINT" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Réseau commercial / Organisations partenaires
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Organisations partenaires
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Pilotez les organisations KORA POINT, leurs conditions
                        cadres et les points de vente rattachés.
                    </p>
                </div>
                {permissions.canManage && (
                    <button
                        type="button"
                        onClick={openCreate}
                        className="btn btn-primary"
                    >
                        <Plus className="size-3.5" />
                        Nouvelle organisation
                    </button>
                )}
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-3">
                <KpiCard
                    icon={Building2}
                    label="Organisations"
                    hint="Partenaires contractuels"
                    value={organizations.total}
                    tone="navy"
                />
                <KpiCard
                    icon={Store}
                    label="Points rattachés"
                    hint="Guichets du réseau"
                    value={stats.totalPoints}
                    tone="blue"
                />
                <KpiCard
                    icon={Coins}
                    label="Encours cumulé"
                    hint="Dettes des organisations"
                    value={formatAmount(stats.totalExposure)}
                    tone="gold"
                />
            </section>

            <KoraFilterBar
                route="/kora-points/organizations"
                searchPlaceholder="Rechercher par code, nom ou adresse…"
                filters={filterConfigs}
            />

            <div className="mt-4">
            <KoraTable
                title="Matrice des organisations"
                subtitle={`${organizations.total} organisation${organizations.total > 1 ? 's' : ''} partenaire${organizations.total > 1 ? 's' : ''}`}
                icon={Building2}
                columns={columns}
                data={organizations.data}
                rowKey={(org) => org.uuid}
                onRowClick={(org) => setDetailOrg(org)}
                emptyTitle="Aucune organisation partenaire"
                emptyMessage="Créez la première organisation pour y rattacher des points de vente."
                emptyIcon={Building2}
                pagination={pagination}
            />
            </div>

            <KoraOrgFormDialog
                open={formOpen}
                organization={editingOrg}
                onOpenChange={setFormOpen}
            />

            <KoraOrgDetailSheet
                open={detailOrg !== null}
                organization={detailOrg}
                subpoints={subpoints}
                unattachedPoints={unattached_points}
                permissions={permissions}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailOrg(null);
                    }
                }}
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
