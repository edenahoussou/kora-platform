import { Head, router } from '@inertiajs/react';
import {
    Archive,
    Building2,
    CheckCircle2,
    Clock,
    Edit3,
    Globe,
    MoreHorizontal,
    Plus,
    RotateCcw,
    ShieldOff,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { AgencyFormDialog } from '@/components/agencies/agency-form-dialog';
import type {
    AgencyRecord,
    HeadCandidate,
} from '@/components/agencies/agency-form-dialog';
import { CORRIDOR_COUNTRIES } from '@/components/corridor-flags';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import agencyRoutes from '@/routes/agencies';
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

type AgenciesPageProps = {
    agencies: PaginatedData<AgencyRecord>;
    statuses: Record<string, string>;
    headCandidates: HeadCandidate[];
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canDeactivate: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    suspended: 'Suspendue',
    archived: 'Archivée',
};

const statusStyles: Record<string, string> = {
    active: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-500/20',
    draft: 'bg-gold/15 text-[var(--kora)] dark:text-gold ring-1 ring-gold/30',
    suspended:
        'bg-orange-500/10 text-orange-700 dark:text-orange-300 ring-1 ring-orange-500/20',
    archived: 'bg-muted text-muted-foreground ring-1 ring-border',
};

function KpiCard({
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

export default function AgenciesPage({
    agencies,
    statuses,
    headCandidates,
    permissions,
}: AgenciesPageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedAgency, setSelectedAgency] = useState<AgencyRecord | null>(
        null,
    );

    const stats = useMemo(() => {
        const data = agencies.data;
        const active = data.filter((a) => a.status === 'active').length;
        const draft = data.filter((a) => a.status === 'draft').length;
        const countries = new Set(data.map((a) => a.country.toUpperCase()))
            .size;

        return {
            total: agencies.total,
            active,
            draft,
            countries: Math.max(countries, 1),
        };
    }, [agencies]);

    const filterConfigs: FilterConfig[] = useMemo(
        () => [
            {
                name: 'status',
                label: 'Statut',
                placeholder: 'Tous les statuts',
                options: Object.entries(statuses).map(([value, label]) => ({
                    value,
                    label: statusLabels[value] ?? label,
                })),
            },
        ],
        [statuses],
    );

    const pagination: PaginationMeta = useMemo(
        () => ({
            currentPage: agencies.current_page,
            lastPage: agencies.last_page,
            perPage: agencies.per_page,
            total: agencies.total,
            from: agencies.from,
            to: agencies.to,
            baseUrl: '/agencies',
        }),
        [agencies],
    );

    const openCreateDialog = () => {
        setSelectedAgency(null);
        setDialogOpen(true);
    };

    const openEditDialog = (agency: AgencyRecord) => {
        setSelectedAgency(agency);
        setDialogOpen(true);
    };

    const changeStatus = (agency: AgencyRecord, status: string) => {
        const action =
            status === 'archived'
                ? 'archiver cette agence'
                : status === 'suspended'
                  ? 'suspendre cette agence'
                  : 'réactiver cette agence';

        if (!window.confirm(`Voulez-vous vraiment ${action} ?`)) {
            return;
        }

        router.patch(
            agencyRoutes.status.url({ agency: agency.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<AgencyRecord>[] = [
        {
            key: 'agency',
            header: 'Agence',
            render: (agency) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 text-[0.6875rem] font-black text-[var(--kora)]">
                        {agency.code.slice(0, 3)}
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {agency.name}
                        </strong>
                        <span className="text-xs font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                            {agency.code}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'location',
            header: 'Localisation',
            render: (agency) => {
                const countryItem = CORRIDOR_COUNTRIES.find(
                    (c) => c.code === agency.country.toUpperCase(),
                );
                const Flag = countryItem?.flag;

                return (
                    <div className="flex items-center gap-2">
                        {Flag && (
                            <span className="size-4 shrink-0 overflow-hidden rounded-full shadow-xs ring-1 ring-border">
                                <Flag className="size-full" />
                            </span>
                        )}
                        <div>
                            <span className="block font-medium text-foreground">
                                {agency.city}
                            </span>
                            <span className="text-xs font-semibold text-muted-foreground uppercase">
                                {countryItem?.name ?? agency.country}
                            </span>
                        </div>
                    </div>
                );
            },
        },
        {
            key: 'head',
            header: 'Chef d\u2019agence',
            render: (agency) =>
                agency.headName ? (
                    <span className="font-medium text-foreground">
                        {agency.headName}
                    </span>
                ) : (
                    <span className="text-xs text-muted-foreground italic">
                        Non assigné
                    </span>
                ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (agency) => (
                <span
                    className={`inline-flex rounded-full px-2.5 py-1 text-[0.6875rem] font-bold ${statusStyles[agency.status] ?? statusStyles.draft}`}
                >
                    {statusLabels[agency.status] ?? agency.status}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (agency) =>
                (permissions.canUpdate || permissions.canDeactivate) && (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions pour ${agency.name}`}
                                    onClick={(event) => event.stopPropagation()}
                                >
                                    <MoreHorizontal className="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {permissions.canUpdate && (
                                    <DropdownMenuItem
                                        onClick={() => openEditDialog(agency)}
                                    >
                                        <Edit3 className="size-4" />
                                        Modifier
                                    </DropdownMenuItem>
                                )}
                                {permissions.canDeactivate &&
                                    agency.status !== 'suspended' &&
                                    agency.status !== 'archived' && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                changeStatus(
                                                    agency,
                                                    'suspended',
                                                )
                                            }
                                        >
                                            <ShieldOff className="size-4" />
                                            Suspendre
                                        </DropdownMenuItem>
                                    )}
                                {permissions.canDeactivate &&
                                    agency.status !== 'archived' && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                changeStatus(agency, 'archived')
                                            }
                                        >
                                            <Archive className="size-4" />
                                            Archiver
                                        </DropdownMenuItem>
                                    )}
                                {permissions.canDeactivate &&
                                    agency.status !== 'active' && (
                                        <DropdownMenuItem
                                            onClick={() =>
                                                changeStatus(agency, 'active')
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
            <Head title="Agences" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Ressources / Agences
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Agences
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Gérez les sites physiques KORA, leur statut et leur
                        rattachement opérationnel.
                    </p>
                </div>
                {permissions.canCreate && (
                    <button
                        type="button"
                        onClick={openCreateDialog}
                        className="btn btn-primary"
                    >
                        <Plus className="size-3.5" />
                        Nouvelle agence
                    </button>
                )}
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={Building2}
                    label="Agences"
                    hint="Réseau physique"
                    value={stats.total}
                    tone="navy"
                />
                <KpiCard
                    icon={CheckCircle2}
                    label="Actives"
                    hint="En exploitation"
                    value={stats.active}
                    tone="green"
                />
                <KpiCard
                    icon={Clock}
                    label="En préparation"
                    hint="Brouillons"
                    value={stats.draft}
                    tone="gold"
                />
                <KpiCard
                    icon={Globe}
                    label="Corridors"
                    hint="Pays desservis"
                    value={stats.countries}
                    tone="blue"
                />
            </section>

            <KoraFilterBar
                route="/agencies"
                searchPlaceholder="Rechercher par code, nom ou ville…"
                filters={filterConfigs}
            />

            <KoraTable
                title="Référentiel des agences"
                subtitle={`${agencies.total} agence${agencies.total > 1 ? 's' : ''} au total`}
                icon={Building2}
                columns={columns}
                data={agencies.data}
                rowKey={(agency) => agency.uuid}
                onRowClick={(agency) =>
                    router.visit(agencyRoutes.show.url({ agency: agency.uuid }))
                }
                emptyTitle="Aucune agence trouvée"
                emptyMessage="Ajustez votre recherche ou créez la première agence du référentiel."
                emptyIcon={Building2}
                pagination={pagination}
            />

            <AgencyFormDialog
                open={dialogOpen}
                agency={selectedAgency}
                statuses={statuses}
                headCandidates={headCandidates}
                onOpenChange={setDialogOpen}
            />
        </>
    );
}

AgenciesPage.layout = {
    breadcrumbs: [
        { title: 'Agences', href: '/agencies' } satisfies BreadcrumbItem,
    ],
};
