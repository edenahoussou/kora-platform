import { MapPin, Satellite } from 'lucide-react';
import { formatMoney } from '@/components/sale/sale-utils';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type {
    ProfitabilityCard,
    ProfitabilityDetail,
} from './profitability-types';

const categoryLabels: Record<string, string> = {
    fuel: 'Carburant',
    maintenance: 'Maintenance',
    tolls: 'Péages',
    tires: 'Pneus',
    insurance: 'Assurance',
    leasing: 'Leasing',
    agency_supplies: 'Fournitures',
    services: 'Services',
    other: 'Autres',
};

const statusLabels: Record<ProfitabilityCard['breakEvenStatus'], string> = {
    atteint: 'Seuil atteint',
    sous_seuil: 'Sous le seuil',
    non_calculable: 'Seuil non calculable',
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    detail: ProfitabilityDetail | null;
    loading: boolean;
};

function Row({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div className="flex items-baseline justify-between gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'tabular-nums',
                    strong ? 'font-black' : 'font-semibold',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

export function ProfitabilityDrawer({
    open,
    onOpenChange,
    detail,
    loading,
}: Props) {
    const card = detail?.card ?? null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="flex h-full w-full flex-col gap-0 border-l border-border bg-card p-0 shadow-[-20px_0_60px_rgba(0,29,78,0.18)] sm:max-w-[38rem]"
            >
                <SheetTitle className="sr-only">
                    Détail de rentabilité
                </SheetTitle>
                <SheetDescription className="sr-only">
                    Recettes, coûts, TCO, seuil et départs concernés pour l’axe
                    sélectionné.
                </SheetDescription>

                {loading || detail === null || card === null ? (
                    <div className="space-y-4 p-6">
                        <Skeleton className="h-7 w-2/3" />
                        <Skeleton className="h-4 w-1/2" />
                        <Skeleton className="h-32 w-full" />
                        <Skeleton className="h-32 w-full" />
                    </div>
                ) : (
                    <div className="flex-1 overflow-y-auto">
                        <header className="border-b border-border px-6 py-5">
                            <span className="section-kicker">
                                {card.subtitle}
                            </span>
                            <div className="mt-1 flex items-start justify-between gap-3">
                                <h2 className="text-[1.6rem] font-extrabold tracking-[-0.04em]">
                                    {card.label}
                                </h2>
                                <span
                                    className={cn(
                                        'profit-status',
                                        card.breakEvenStatus === 'atteint' &&
                                            'profit-status-ok',
                                        card.breakEvenStatus === 'sous_seuil' &&
                                            'profit-status-risk',
                                    )}
                                >
                                    {statusLabels[card.breakEvenStatus]}
                                </span>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {card.departureCount} départ(s) concerné(s) ·{' '}
                                {card.marginPercent === null
                                    ? 'marge non calculable'
                                    : `${card.marginPercent.toLocaleString('fr-FR')} % de marge`}
                            </p>
                        </header>

                        <section className="border-b border-border px-6 py-5">
                            <h3 className="profit-drawer-title">Recettes</h3>
                            <dl className="mt-3 space-y-2 text-sm">
                                <Row
                                    label="Recettes brutes"
                                    value={formatMoney(
                                        detail.revenue.gross ?? 0,
                                    )}
                                />
                                <Row
                                    label="Annulations reconnues"
                                    value={formatMoney(
                                        -(detail.revenue.deductions ?? 0),
                                    )}
                                />
                                <Row
                                    label="Recettes nettes"
                                    value={formatMoney(detail.revenue.net ?? 0)}
                                    strong
                                />
                            </dl>
                        </section>

                        <section className="border-b border-border px-6 py-5">
                            <h3 className="profit-drawer-title">Coûts</h3>
                            <dl className="mt-3 space-y-2 text-sm">
                                <Row
                                    label="Coûts directs"
                                    value={formatMoney(
                                        detail.costs.direct ?? 0,
                                    )}
                                />
                                <Row
                                    label="Coûts répartis"
                                    value={formatMoney(
                                        detail.costs.distributed ?? 0,
                                    )}
                                />
                                <Row
                                    label="Coûts totaux"
                                    value={formatMoney(detail.costs.total ?? 0)}
                                    strong
                                />
                                <Row
                                    label="Dont fixes"
                                    value={formatMoney(detail.costs.fixed ?? 0)}
                                />
                                <Row
                                    label="Dont variables"
                                    value={formatMoney(
                                        detail.costs.variable ?? 0,
                                    )}
                                />
                            </dl>
                            {Object.keys(detail.categories).length > 0 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {Object.entries(detail.categories).map(
                                        ([code, amount]) => (
                                            <span
                                                key={code}
                                                className="profit-category-chip"
                                            >
                                                {categoryLabels[code] ?? code}
                                                <b>{formatMoney(amount)}</b>
                                            </span>
                                        ),
                                    )}
                                </div>
                            )}
                        </section>

                        <section className="border-b border-border px-6 py-5">
                            <h3 className="profit-drawer-title">
                                Exploitation
                            </h3>
                            <dl className="mt-3 space-y-2 text-sm">
                                <Row
                                    label="Contribution"
                                    value={formatMoney(card.contribution)}
                                    strong
                                />
                                <Row
                                    label="Kilomètres"
                                    value={`${card.km.toLocaleString('fr-FR')} km`}
                                />
                                <Row
                                    label="Coût/km"
                                    value={
                                        card.costPerKm === null
                                            ? '—'
                                            : `${card.costPerKm.toLocaleString('fr-FR')} FCFA`
                                    }
                                />
                                <Row
                                    label="TCO opérationnel"
                                    value={formatMoney(card.costTotal)}
                                />
                                <Row
                                    label="Sièges vendus"
                                    value={
                                        card.capacity
                                            ? `${card.seatsSold} / ${card.capacity}`
                                            : String(card.seatsSold)
                                    }
                                />
                                <Row
                                    label="Sièges seuil"
                                    value={
                                        card.breakEvenSeats === null
                                            ? '—'
                                            : `${card.breakEvenSeats} sièges`
                                    }
                                />
                                <Row
                                    label="Couverture GPS"
                                    value={`${card.gpsCoverage.toLocaleString('fr-FR')} %`}
                                />
                            </dl>
                        </section>

                        <section className="px-6 py-5">
                            <h3 className="profit-drawer-title">
                                Départs concernés
                            </h3>
                            {detail.departures.length === 0 ? (
                                <p className="mt-3 text-sm text-muted-foreground">
                                    Aucun départ rattaché à cet axe.
                                </p>
                            ) : (
                                <ul className="mt-3 space-y-2">
                                    {detail.departures.map((departure) => (
                                        <li
                                            key={departure.uuid}
                                            className="rounded-xl border border-border/70 bg-background/50 px-3 py-2.5"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-xs font-black">
                                                        {departure.label}
                                                    </p>
                                                    <p className="text-[0.6875rem] text-muted-foreground">
                                                        {new Intl.DateTimeFormat(
                                                            'fr-FR',
                                                            {
                                                                dateStyle:
                                                                    'medium',
                                                                timeStyle:
                                                                    'short',
                                                            },
                                                        ).format(
                                                            new Date(
                                                                departure.scheduledAt,
                                                            ),
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-xs font-black tabular-nums">
                                                        {formatMoney(
                                                            departure.revenueNet,
                                                        )}
                                                    </p>
                                                    <p className="text-[0.6875rem] text-muted-foreground tabular-nums">
                                                        coûts{' '}
                                                        {formatMoney(
                                                            departure.costTotal,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="mt-1.5 flex items-center gap-3 text-[0.625rem] text-muted-foreground">
                                                <span>
                                                    {departure.seatsSold}{' '}
                                                    siège(s)
                                                </span>
                                                <span>
                                                    {departure.distanceKm.toLocaleString(
                                                        'fr-FR',
                                                    )}{' '}
                                                    km
                                                </span>
                                                <span
                                                    className={cn(
                                                        'profit-distance',
                                                        departure.distanceSource ===
                                                            'gps'
                                                            ? 'profit-distance-gps'
                                                            : 'profit-distance-estimated',
                                                    )}
                                                >
                                                    {departure.distanceSource ===
                                                    'gps' ? (
                                                        <Satellite
                                                            className="size-3"
                                                            aria-hidden="true"
                                                        />
                                                    ) : (
                                                        <MapPin
                                                            className="size-3"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    {departure.distanceSource ===
                                                    'gps'
                                                        ? 'GPS'
                                                        : 'Estimé'}
                                                </span>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
