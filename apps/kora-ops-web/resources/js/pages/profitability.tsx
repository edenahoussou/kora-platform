import { Head, router } from '@inertiajs/react';
import { CalendarDays, ChevronDown, Download, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { profitabilityAxisIcon } from '@/components/profitability/profitability-axis-icons';
import { ProfitabilityAxisTabs } from '@/components/profitability/profitability-axis-tabs';
import { ProfitabilityBreakEven } from '@/components/profitability/profitability-break-even';
import { ProfitabilityCarousel } from '@/components/profitability/profitability-carousel';
import { ProfitabilityDrawer } from '@/components/profitability/profitability-drawer';
import { ProfitabilityEmpty } from '@/components/profitability/profitability-empty';
import { ProfitabilityHero } from '@/components/profitability/profitability-hero';
import { ProfitabilityKpis } from '@/components/profitability/profitability-kpis';
import { ProfitabilityPnl } from '@/components/profitability/profitability-pnl';
import { ProfitabilityReconciliation } from '@/components/profitability/profitability-reconciliation';
import { ProfitabilityTco } from '@/components/profitability/profitability-tco';
import type {
    ProfitabilityCard,
    ProfitabilityDetail,
    ProfitabilityProps,
} from '@/components/profitability/profitability-types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { profitability } from '@/routes';
import { exportMethod } from '@/routes/profitability';
import type { BreadcrumbItem } from '@/types';

export default function ProfitabilityPage(props: ProfitabilityProps) {
    const {
        axis,
        axisOptions,
        entity,
        hero,
        kpis,
        cards,
        breakEvenCards,
        pnl,
        tco,
        reconciliation,
        controls,
        empty,
        period,
        periodOptions,
        detail,
    } = props;
    const [isLoading, setIsLoading] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(detail !== null);
    const [detailState, setDetailState] = useState<ProfitabilityDetail | null>(
        detail,
    );
    const [customOpen, setCustomOpen] = useState(false);
    const [customFrom, setCustomFrom] = useState(period.from);
    const [customTo, setCustomTo] = useState(period.to);
    const axisLabel = axisOptions[axis] ?? axis;

    const navigate = (query: Record<string, string>) => {
        setIsLoading(true);
        router.get(profitability().url, query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => setIsLoading(false),
        });
    };

    const periodQuery = (): Record<string, string> =>
        period.preset === 'custom'
            ? { period: 'custom', from: period.from, to: period.to }
            : { period: period.preset };

    const entityQuery = (): Record<string, string> =>
        entity === null
            ? {}
            : { entity_type: entity.type, entity: entity.uuid };

    const changePeriod = (value: string) => {
        if (value === 'custom') {
            setCustomOpen(true);

            return;
        }

        navigate({ ...periodQuery(), period: value, axis, ...entityQuery() });
    };

    const applyEntity = (card: ProfitabilityCard) => {
        navigate({
            ...periodQuery(),
            axis,
            entity_type: card.axisType,
            entity: card.key,
        });
    };

    const clearEntity = () => {
        navigate({ ...periodQuery(), axis });
    };

    const openDetail = (key: string) => {
        setDrawerOpen(true);
        setDetailState(null);
        setIsLoading(true);
        router.get(
            profitability().url,
            { ...periodQuery(), axis, detail: key, ...entityQuery() },
            {
                only: ['detail'],
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    setDetailState(
                        (page.props.detail as ProfitabilityDetail) ?? null,
                    );
                },
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const closeDetail = () => {
        setDrawerOpen(false);
        setDetailState(null);
        router.get(
            profitability().url,
            { ...periodQuery(), axis, ...entityQuery() },
            {
                only: ['detail'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const exportUrl = exportMethod.url({
        query: { ...periodQuery(), axis, ...entityQuery() },
    });

    return (
        <>
            <Head title="Rentabilité" />
            <main
                className={cn(
                    'cash-page profit-page transition-opacity',
                    isLoading && 'pointer-events-none opacity-60',
                )}
                aria-busy={isLoading}
            >
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Finance / Rentabilité
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Rentabilité
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Comprenez où l’activité gagne ou perd, par bus,
                            ligne, départ et agence.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <label className="relative min-w-[12rem]">
                            <span className="sr-only">Période du rapport</span>
                            <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <select
                                value={period.preset}
                                onChange={(event) =>
                                    changePeriod(event.target.value)
                                }
                                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 pr-10! pl-10! text-xs font-black transition outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                            >
                                {Object.entries(periodOptions).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <ChevronDown className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        </label>
                        <a href={exportUrl} className="btn btn-primary">
                            <Download className="size-3.5" />
                            Exporter
                        </a>
                    </div>
                </div>

                {entity !== null && (
                    <EntityScopeChip
                        icon={profitabilityAxisIcon(entity.type)}
                        label={`${axisOptions[entity.type] ?? entity.type} · ${entity.label}`}
                        onClear={clearEntity}
                    />
                )}

                {empty ? (
                    <ProfitabilityEmpty axisLabel={axisLabel} />
                ) : (
                    <>
                        <ProfitabilityHero
                            revenueNet={hero.revenueNet}
                            result={hero.result}
                            marginPercent={hero.marginPercent}
                            costUnassigned={hero.costUnassigned}
                            networkUnassigned={hero.networkUnassigned}
                            filtered={entity !== null}
                            coveragePercent={hero.coveragePercent}
                            breakEven={hero.breakEven}
                        />
                        <ProfitabilityKpis {...kpis} />
                        <ProfitabilityReconciliation
                            reconciliation={reconciliation}
                            controls={controls}
                        />
                        <ProfitabilityAxisTabs
                            axis={axis}
                            axisOptions={axisOptions}
                            onChange={(value) =>
                                navigate({
                                    ...periodQuery(),
                                    axis: value,
                                    ...entityQuery(),
                                })
                            }
                        />
                        <ProfitabilityBreakEven
                            cards={breakEvenCards}
                            axisLabel={axisLabel}
                            entity={entity}
                            onOpen={openDetail}
                            onFilter={applyEntity}
                        />
                        <ProfitabilityCarousel
                            key={`${axis}:${period.preset}:${period.from}:${period.to}:${entity?.uuid ?? 'all'}`}
                            cards={cards}
                            axisLabel={axisLabel}
                            axisIcon={profitabilityAxisIcon(axis)}
                            suspended={drawerOpen}
                            entity={entity}
                            onOpen={openDetail}
                            onFilter={applyEntity}
                        />
                        <div className="profit-panels">
                            <ProfitabilityPnl rows={pnl} />
                            {tco.length > 0 && <ProfitabilityTco rows={tco} />}
                        </div>
                    </>
                )}
            </main>

            <ProfitabilityDrawer
                open={drawerOpen}
                onOpenChange={(open) => {
                    if (open) {
                        setDrawerOpen(true);
                    } else {
                        closeDetail();
                    }
                }}
                detail={detailState}
                loading={isLoading && detailState === null}
            />

            <Dialog open={customOpen} onOpenChange={setCustomOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Période personnalisée</DialogTitle>
                        <DialogDescription>
                            Bornes incluses, fuseau Africa/Porto-Novo.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <label className="field">
                            <span>Du</span>
                            <input
                                type="date"
                                value={customFrom}
                                onChange={(event) =>
                                    setCustomFrom(event.target.value)
                                }
                                className="control"
                            />
                        </label>
                        <label className="field">
                            <span>Au</span>
                            <input
                                type="date"
                                value={customTo}
                                onChange={(event) =>
                                    setCustomTo(event.target.value)
                                }
                                className="control"
                            />
                        </label>
                    </div>
                    <DialogFooter>
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={() => setCustomOpen(false)}
                        >
                            Annuler
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => {
                                setCustomOpen(false);
                                navigate({
                                    period: 'custom',
                                    from: customFrom,
                                    to: customTo,
                                    axis,
                                    ...entityQuery(),
                                });
                            }}
                        >
                            Appliquer
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProfitabilityPage.layout = {
    breadcrumbs: [
        {
            title: 'Rentabilité',
            href: '/profitability',
        } satisfies BreadcrumbItem,
    ],
};

function EntityScopeChip({
    icon: Icon,
    label,
    onClear,
}: {
    icon: LucideIcon;
    label: string;
    onClear: () => void;
}) {
    return (
        <div className="profit-scope-chip">
            <Icon className="size-3.5" aria-hidden="true" />
            <span>{label}</span>
            <button
                type="button"
                aria-label="Retirer le filtre"
                onClick={onClear}
            >
                <X className="size-3.5" aria-hidden="true" />
            </button>
        </div>
    );
}
