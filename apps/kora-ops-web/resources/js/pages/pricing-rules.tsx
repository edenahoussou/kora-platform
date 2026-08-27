import { Form, Head, router } from '@inertiajs/react';
import {
    Archive,
    CheckCircle,
    Edit3,
    MoreHorizontal,
    Plus,
    Save,
    Settings2,
    TrendingUp,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { KoraTable } from '@/components/kora-table';
import type { PaginationMeta, TableColumn } from '@/components/kora-table';
import { PricingRuleFormDialog } from '@/components/pricing/pricing-rule-form-dialog';
import type { PricingRuleRecord } from '@/components/pricing/pricing-rule-form-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import pricingRules from '@/routes/pricing-rules';
import type { BreadcrumbItem } from '@/types';

type LineOption = { uuid: string; code: string; name: string };

type PageProps = {
    rules: {
        data: PricingRuleRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    types: Record<string, string>;
    lines: LineOption[];
    settings: {
        floorCoefficient: number | null;
        ceilingCoefficient: number | null;
        updatedAt: string | null;
    };
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canManageSettings: boolean;
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
const typeLabels: Record<string, string> = {
    increase: 'Majoration',
    decrease: 'Réduction',
};

function formatConditions(rule: PricingRuleRecord): string {
    const parts: string[] = [];
    const {
        fillRateMin,
        fillRateMax,
        hoursBeforeDepartureMin,
        hoursBeforeDepartureMax,
    } = rule.conditions;

    if (fillRateMin !== null || fillRateMax !== null) {
        if (fillRateMin !== null && fillRateMax !== null) {
            parts.push(`remplissage ${fillRateMin}–${fillRateMax}%`);
        } else if (fillRateMin !== null) {
            parts.push(`remplissage ≥ ${fillRateMin}%`);
        } else {
            parts.push(`remplissage ≤ ${fillRateMax}%`);
        }
    }

    if (hoursBeforeDepartureMin !== null || hoursBeforeDepartureMax !== null) {
        if (
            hoursBeforeDepartureMin !== null &&
            hoursBeforeDepartureMax !== null
        ) {
            parts.push(
                `départ dans ${hoursBeforeDepartureMin}–${hoursBeforeDepartureMax}h`,
            );
        } else if (hoursBeforeDepartureMax !== null) {
            parts.push(`départ dans < ${hoursBeforeDepartureMax}h`);
        } else {
            parts.push(`départ dans > ${hoursBeforeDepartureMin}h`);
        }
    }

    return parts.length > 0 ? parts.join(' · ') : 'Aucune (toujours active)';
}

export default function PricingRulesPage({
    rules,
    statuses,
    lines,
    settings,
    permissions,
}: PageProps) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<PricingRuleRecord | null>(null);

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
                name: 'line',
                label: 'Ligne',
                placeholder: 'Toutes les lignes',
                options: lines.map((line) => ({
                    value: line.uuid,
                    label: `${line.code} · ${line.name}`,
                })),
            },
        ],
        [statuses, lines],
    );

    const pagination: PaginationMeta = {
        currentPage: rules.current_page,
        lastPage: rules.last_page,
        perPage: rules.per_page,
        total: rules.total,
        from: rules.from,
        to: rules.to,
        baseUrl: '/pricing-rules',
    };

    const changeStatus = (rule: PricingRuleRecord, status: string) => {
        router.patch(
            pricingRules.status.url({ pricingRule: rule.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    const columns: TableColumn<PricingRuleRecord>[] = [
        {
            key: 'rule',
            header: 'Règle',
            render: (rule) => (
                <div className="flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-gold/12 text-[var(--kora)]">
                        <TrendingUp className="size-4" />
                    </span>
                    <div>
                        <strong className="block font-bold text-foreground">
                            {typeLabels[rule.type] ?? rule.type}{' '}
                            {rule.percentage}%
                        </strong>
                        <span className="text-xs text-muted-foreground">
                            {rule.lineCode
                                ? `${rule.lineCode} · ${rule.lineName}`
                                : 'Toutes les lignes'}
                        </span>
                    </div>
                </div>
            ),
        },
        {
            key: 'conditions',
            header: 'Conditions',
            render: (rule) => (
                <span className="text-xs text-muted-foreground">
                    {formatConditions(rule)}
                </span>
            ),
        },
        {
            key: 'priority',
            header: 'Priorité',
            render: (rule) => (
                <span className="font-medium text-foreground">
                    {rule.priority}
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
            <Head title="Tarification dynamique" />
            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Tarification dynamique
                    </h1>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        Règles de majoration/réduction selon remplissage et
                        délai avant départ — ordre de priorité déterministe.
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
                        Nouvelle règle
                    </Button>
                )}
            </div>

            {/* Plancher / plafond global */}
            <section className="mb-6 rounded-[var(--kora-radius)] border border-border/50 bg-card p-6 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                <div className="mb-4 flex items-center gap-3">
                    <span className="grid size-9 place-items-center rounded-[0.65rem] bg-muted text-foreground">
                        <Settings2 className="size-4" />
                    </span>
                    <div>
                        <h2 className="font-bold text-foreground">
                            Plancher / plafond global
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Borne le coefficient final, toutes règles
                            confondues. Aucune valeur = aucun bornage.
                        </p>
                    </div>
                </div>
                {permissions.canManageSettings ? (
                    <Form
                        {...pricingRules.settings.form()}
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-4 sm:flex-row sm:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="settings-floor">
                                        Plancher (ex : 0.5 = -50% max)
                                    </Label>
                                    <Input
                                        id="settings-floor"
                                        name="floor_coefficient"
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        max={1}
                                        defaultValue={
                                            settings.floorCoefficient ?? ''
                                        }
                                        className="h-11 w-40 rounded-[0.75rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <InputError
                                        message={errors.floor_coefficient}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="settings-ceiling">
                                        Plafond (ex : 2.0 = +100% max)
                                    </Label>
                                    <Input
                                        id="settings-ceiling"
                                        name="ceiling_coefficient"
                                        type="number"
                                        step="0.01"
                                        min={1}
                                        max={10}
                                        defaultValue={
                                            settings.ceilingCoefficient ?? ''
                                        }
                                        className="h-11 w-40 rounded-[0.75rem] border-0 bg-muted shadow-[inset_0_0_0_1px_var(--border)]"
                                    />
                                    <InputError
                                        message={errors.ceiling_coefficient}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    variant="ghost"
                                    className="h-11 rounded-[0.75rem] border border-border/60"
                                >
                                    <Save className="size-4" />
                                    Enregistrer
                                </Button>
                            </>
                        )}
                    </Form>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Plancher : {settings.floorCoefficient ?? 'non défini'} ·
                        Plafond : {settings.ceilingCoefficient ?? 'non défini'}
                    </p>
                )}
            </section>

            <KoraFilterBar
                route="/pricing-rules"
                searchPlaceholder="Rechercher…"
                filters={filters}
            />
            <KoraTable
                title="Règles de pricing"
                subtitle={`${rules.total} règle${rules.total > 1 ? 's' : ''} au total`}
                icon={TrendingUp}
                columns={columns}
                data={rules.data}
                rowKey={(rule) => rule.uuid}
                emptyTitle="Aucune règle trouvée"
                emptyMessage="Ajustez vos filtres ou créez la première règle de pricing."
                emptyIcon={TrendingUp}
                pagination={pagination}
            />
            <PricingRuleFormDialog
                open={open}
                rule={selected}
                lines={lines}
                onOpenChange={setOpen}
            />
        </>
    );
}

PricingRulesPage.layout = {
    breadcrumbs: [
        { title: 'Tarifs', href: '/fares' } satisfies BreadcrumbItem,
        {
            title: 'Tarification dynamique',
            href: '/pricing-rules',
        } satisfies BreadcrumbItem,
    ],
};
