import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    Coins,
    Link2,
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
import { KoraAdminPointWizardDialog } from '@/components/kora-points/admin/kora-admin-point-wizard-dialog';
import type {
    AdminOrganization,
    AdminPermissions,
    AdminPoint,
} from '@/components/kora-points/admin/kora-admin-ui';
import {
    formatAmount,
    KpiCard,
    PointStatusBadge,
} from '@/components/kora-points/admin/kora-admin-ui';
import { KoraPointAttachDialog } from '@/components/kora-points/admin/kora-point-attach-dialog';
import { KoraPointFormDialog } from '@/components/kora-points/admin/kora-point-form-dialog';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import koraPoints from '@/routes/kora-points';
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
    points: PaginatedData<AdminPoint>;
    organizations: AdminOrganization[];
    defaults?: Record<string, unknown> | null;
    permissions: AdminPermissions;
};

export default function KoraPointsIndexPage({
    points,
    organizations,
    permissions,
}: Props) {
    const [wizardOpen, setWizardOpen] = useState(false);
    const [editingPoint, setEditingPoint] = useState<AdminPoint | null>(null);
    const [attachPoint, setAttachPoint] = useState<AdminPoint | null>(null);

    const stats = useMemo(() => {
        let totalExposure = 0;
        let totalMonthlySales = 0;
        let alertCount = 0;

        points.data.forEach((pt) => {
            totalExposure += pt.current_exposure;
            totalMonthlySales += pt.monthly_sales_amount;

            if (pt.status !== 'ACTIVE') {
                alertCount += 1;
            }
        });

        return { totalExposure, totalMonthlySales, alertCount };
    }, [points.data]);

    const orgBadgeByUuid = useMemo(() => {
        const map = new Map<string, string>();

        organizations.forEach((org) => {
            map.set(org.uuid, org.code.slice(0, 3).toUpperCase());
        });

        return map;
    }, [organizations]);

    const filterConfigs: FilterConfig[] = [
        {
            name: 'type',
            label: 'Rattachement',
            placeholder: 'Tous les points',
            options: [
                { value: 'AUTONOMOUS', label: 'Points autonomes' },
                {
                    value: 'ORGANIZATION_ATTACHED',
                    label: 'Rattachés à une organisation',
                },
            ],
        },
        {
            name: 'state',
            label: 'Statut',
            placeholder: 'Tous les statuts',
            options: [
                { value: 'active', label: 'Actifs' },
                { value: 'suspended', label: 'Suspendus' },
            ],
        },
    ];

    const pagination: PaginationMeta = {
        currentPage: points.current_page,
        lastPage: points.last_page,
        perPage: points.per_page,
        total: points.total,
        from: points.from,
        to: points.to,
        baseUrl: '/kora-points',
    };

    const openCreate = () => {
        setWizardOpen(true);
    };

    const openEdit = (point: AdminPoint) => {
        setEditingPoint(point);
    };

    const handleWizardSubmit = (data: {
        name: string;
        type: 'AUTONOMOUS' | 'ORGANIZATION_ATTACHED';
        organizationName?: string;
        organizationUuid?: string;
        city: string;
        department: string;
        managerName: string;
        phone: string;
        creditLimit: number;
        commissionRateBps: number;
        settlementFrequencyDays: number;
        gracePeriodDays: number;
    }) => {
        const frequencyMap: Record<number, string> = {
            1: 'daily',
            7: 'weekly',
            30: 'monthly',
        };
        const frequency = frequencyMap[data.settlementFrequencyDays];

        router.post(
            koraPoints.store.url(),
            {
                name: data.name,
                city: data.city,
                country: 'BJ',
                responsible_name: data.managerName,
                phone: data.phone,
                organization_uuid:
                    data.type === 'ORGANIZATION_ATTACHED'
                        ? data.organizationUuid
                        : null,
                commission_basis_points: data.commissionRateBps,
                ceiling_amount: data.creditLimit,
                ...(frequency
                    ? {
                          settlement_calendar: {
                              frequency,
                              time: '18:00',
                              timezone: 'Africa/Porto-Novo',
                              ...(frequency === 'weekly' ? { weekday: 1 } : {}),
                              ...(frequency === 'monthly' ? { day: 1 } : {}),
                          },
                      }
                    : {}),
            },
            { preserveScroll: true },
        );
    };

    const changeStatus = (
        point: AdminPoint,
        status: 'active' | 'suspended',
    ) => {
        const action = status === 'suspended' ? 'suspendre' : 'réactiver';

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

    const columns: TableColumn<AdminPoint>[] = [
        {
            key: 'point',
            header: 'Point de vente',
            render: (point) => {
                const attachedOrgBadge = point.organization_uuid
                    ? orgBadgeByUuid.get(point.organization_uuid)
                    : null;
                const badgeLabel =
                    attachedOrgBadge ?? point.code.slice(0, 3).toUpperCase();

                return (
                    <div className="flex items-center gap-3">
                        <span
                            className={
                                attachedOrgBadge
                                    ? 'grid size-9 place-items-center rounded-[0.65rem] bg-kora text-[0.6875rem] font-black text-white'
                                    : 'grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 text-[0.6875rem] font-black text-[var(--kora)]'
                            }
                            title={
                                attachedOrgBadge
                                    ? `Rattaché à ${point.organization_name}`
                                    : 'Point autonome'
                            }
                        >
                            {badgeLabel}
                        </span>
                        <div>
                            <strong className="block font-bold text-foreground">
                                {point.name}
                            </strong>
                            <span className="text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                                {point.code} · {point.city}
                            </span>
                        </div>
                    </div>
                );
            },
        },
        {
            key: 'manager',
            header: 'Responsable',
            render: (point) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {point.manager_name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {point.phone}
                    </span>
                </div>
            ),
        },
        {
            key: 'organization',
            header: 'Rattachement',
            render: (point) =>
                point.organization_name ? (
                    <div className="flex items-center gap-2">
                        <Building2 className="size-3.5 text-muted-foreground" />
                        <span className="font-medium text-foreground">
                            {point.organization_name}
                        </span>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground italic">
                        Point autonome
                    </span>
                ),
        },
        {
            key: 'terms',
            header: 'Conditions',
            render: (point) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {(point.commission_rate_bps / 100).toLocaleString(
                            'fr-FR',
                            { maximumFractionDigits: 2 },
                        )}
                        {' % commission'}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Plafond {formatAmount(point.credit_limit)}
                    </span>
                </div>
            ),
        },
        {
            key: 'exposure',
            header: 'Encours',
            align: 'right',
            render: (point) => (
                <div>
                    <span className="block font-bold text-foreground">
                        {formatAmount(point.current_exposure)}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {point.usage_percentage} % du plafond
                    </span>
                </div>
            ),
        },
        {
            key: 'sales',
            header: 'Ventes du mois',
            align: 'right',
            render: (point) => (
                <div>
                    <span className="block font-medium text-foreground">
                        {formatAmount(point.monthly_sales_amount)}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {point.sales_count} vente(s)
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (point) => <PointStatusBadge status={point.status} />,
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (point) =>
                permissions.canManage && (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions pour ${point.name}`}
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onClick={() => openEdit(point)}
                                >
                                    <Pencil className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                                {!point.organization_uuid && (
                                    <DropdownMenuItem
                                        onClick={() => setAttachPoint(point)}
                                    >
                                        <Link2 className="size-4" />
                                        Rattacher à une organisation
                                    </DropdownMenuItem>
                                )}
                                {point.raw_status !== 'suspended' ? (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(point, 'suspended')
                                        }
                                    >
                                        <ShieldOff className="size-4" />
                                        Suspendre
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(point, 'active')
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
            <Head title="Points de vente KORA POINT" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Réseau commercial / Points de vente
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Points de vente partenaires
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Administrez le réseau KORA POINT : guichets autonomes et
                        points rattachés aux organisations.
                    </p>
                </div>
                {permissions.canManage && (
                    <button
                        type="button"
                        onClick={openCreate}
                        className="btn btn-primary"
                    >
                        <Plus className="size-3.5" />
                        Nouveau point
                    </button>
                )}
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={Store}
                    label="Points du réseau"
                    hint="Guichets partenaires"
                    value={points.total}
                    tone="navy"
                />
                <KpiCard
                    icon={Coins}
                    label="Volume mensuel"
                    hint="Recettes billetterie"
                    value={formatAmount(stats.totalMonthlySales)}
                    tone="green"
                />
                <KpiCard
                    icon={AlertTriangle}
                    label="Encours global"
                    hint="Dettes en circulation"
                    value={formatAmount(stats.totalExposure)}
                    tone="gold"
                />
                <KpiCard
                    icon={AlertTriangle}
                    label="Points en alerte"
                    hint="Seuil ou suspension"
                    value={stats.alertCount}
                    tone="rose"
                />
            </section>

            <KoraFilterBar
                route="/kora-points"
                searchPlaceholder="Rechercher par code, nom ou ville…"
                filters={filterConfigs}
            />

            <div className="mt-4">
                <KoraTable
                    title="Réseau de points de vente"
                    subtitle={`${points.total} point${points.total > 1 ? 's' : ''} référencé${points.total > 1 ? 's' : ''}`}
                    icon={Store}
                    columns={columns}
                    data={points.data}
                    rowKey={(point) => point.uuid}
                    emptyTitle="Aucun point de vente"
                    emptyMessage="Ajustez votre recherche ou créez le premier point du réseau."
                    emptyIcon={Store}
                    pagination={pagination}
                />
            </div>

            <KoraAdminPointWizardDialog
                isOpen={wizardOpen}
                organizations={organizations}
                onClose={() => setWizardOpen(false)}
                onSubmit={handleWizardSubmit}
            />

            <KoraPointFormDialog
                open={editingPoint !== null}
                point={editingPoint}
                organizations={organizations}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingPoint(null);
                    }
                }}
            />

            <KoraPointAttachDialog
                open={attachPoint !== null}
                point={attachPoint}
                organizations={organizations}
                onOpenChange={(open) => {
                    if (!open) {
                        setAttachPoint(null);
                    }
                }}
            />
        </>
    );
}

KoraPointsIndexPage.layout = {
    breadcrumbs: [
        {
            title: 'Points de vente',
            href: '/kora-points',
        } satisfies BreadcrumbItem,
    ],
};
