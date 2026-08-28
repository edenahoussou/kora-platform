import { Head, router } from '@inertiajs/react';
import {
    Archive,
    CheckCircle,
    Edit3,
    Landmark,
    MoreHorizontal,
    Plus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { TaxRuleFormDialog } from '@/components/pricing/tax-rule-form-dialog';
import type { TaxRuleRecord } from '@/components/pricing/tax-rule-form-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import taxRules from '@/routes/tax-rules';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    taxRules: {
        data: TaxRuleRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    serviceTypes: Record<string, string>;
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    archived: 'Archivée',
};
const statusTones: Record<string, 'navy' | 'gold' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    archived: 'muted',
};
const serviceTypeLabels: Record<string, string> = {
    transport: 'Transport',
    parcel: 'Colis',
    fee: 'Frais',
    other: 'Autre',
};

export default function TaxRulesPage({
    taxRules: taxRulesData,
    statuses,
    serviceTypes,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<TaxRuleRecord | null>(null);

    const filters: FilterConfig[] = useMemo(
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
            {
                name: 'service_type',
                label: 'Service',
                placeholder: 'Tous les services',
                options: Object.entries(serviceTypes).map(([value, label]) => ({
                    value,
                    label: serviceTypeLabels[value] ?? label,
                })),
            },
        ],
        [statuses, serviceTypes],
    );

    const pagination: PaginationMeta = {
        currentPage: taxRulesData.current_page,
        lastPage: taxRulesData.last_page,
        perPage: taxRulesData.per_page,
        total: taxRulesData.total,
        from: taxRulesData.from,
        to: taxRulesData.to,
        baseUrl: '/tax-rules',
    };

    const changeStatus = (rule: TaxRuleRecord, status: string) => {
        router.patch(
            taxRules.status.url({ taxRule: rule.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<TaxRuleRecord>[] = [
        {
            key: 'rule',
            header: 'Taux fiscal',
            render: (rule) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 text-[var(--kora)]">
                        <Landmark className="size-4" />
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {rule.taxCode}
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {serviceTypeLabels[rule.serviceType] ??
                                rule.serviceType}{' '}
                            · {rule.scope}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'rate',
            header: 'Taux',
            render: (rule) => (
                <span className="font-medium text-foreground">
                    {rule.taxRate}%
                </span>
            ),
        },
        {
            key: 'period',
            header: 'Période',
            render: (rule) => (
                <span className="text-xs text-muted-foreground">
                    {rule.startsOn} {rule.endsOn ? `→ ${rule.endsOn}` : '→ …'}
                </span>
            ),
        },
        {
            key: 'validated',
            header: 'Validé par',
            render: (rule) => (
                <span className="text-xs text-muted-foreground">
                    {rule.validatedByName ?? '—'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Statut',
            render: (rule) => (
                <KoraStatusBadge
                    label={statusLabels[rule.status] ?? rule.status}
                    tone={statusTones[rule.status] ?? 'gold'}
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            align: 'right',
            render: (rule) => (
                <div
                    className="flex justify-end"
                    onClick={(e) => e.stopPropagation()}
                >
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Actions"
                            >
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {permissions.canUpdate && (
                                <DropdownMenuItem
                                    onClick={() => {
                                        setSelected(rule);
                                        setOpen(true);
                                    }}
                                >
                                    <Edit3 className="size-4" />
                                    Modifier
                                </DropdownMenuItem>
                            )}
                            {permissions.canUpdate &&
                                rule.status !== 'active' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(rule, 'active')
                                        }
                                    >
                                        <CheckCircle className="size-4" />
                                        Activer
                                    </DropdownMenuItem>
                                )}
                            {permissions.canUpdate &&
                                rule.status !== 'archived' && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            changeStatus(rule, 'archived')
                                        }
                                    >
                                        <Archive className="size-4" />
                                        Archiver
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
            <Head title="Taux fiscaux" />
            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Taux fiscaux
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Groupes/taux confirmés avec la DGI — paramètres
                        historisés, aucun taux codé en dur. Alimentera la
                        facturation MECeF.
                    </p>
                </div>
                {permissions.canCreate && (
                    <Button
                        onClick={() => {
                            setSelected(null);
                            setOpen(true);
                        }}
                        className="h-11 rounded-[0.75rem] bg-gold px-5 font-medium text-[var(--kora)] hover:bg-[var(--kora)] hover:text-white"
                    >
                        <Plus className="size-4" />
                        Nouveau taux
                    </Button>
                )}
            </div>
            <KoraFilterBar
                route="/tax-rules"
                searchPlaceholder="Rechercher par code ou portée…"
                filters={filters}
            />
            <KoraTable
                title="Référentiel des taux fiscaux"
                subtitle={`${taxRulesData.total} taux au total`}
                icon={Landmark}
                columns={columns}
                data={taxRulesData.data}
                rowKey={(rule) => rule.uuid}
                emptyTitle="Aucun taux trouvé"
                emptyMessage="Ajustez vos filtres ou créez le premier taux fiscal."
                emptyIcon={Landmark}
                pagination={pagination}
            />
            <TaxRuleFormDialog
                open={open}
                rule={selected}
                serviceTypes={serviceTypes}
                onOpenChange={setOpen}
            />
        </>
    );
}

TaxRulesPage.layout = {
    breadcrumbs: [
        { title: 'Tarifs', href: '/fares' } satisfies BreadcrumbItem,
        { title: 'Taux fiscaux', href: '/tax-rules' } satisfies BreadcrumbItem,
    ],
};
