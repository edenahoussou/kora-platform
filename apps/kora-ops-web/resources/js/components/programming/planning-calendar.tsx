import { ArrowLeft, ArrowRight, Bus, CalendarDays, CheckCircle2, ChevronRight, Info } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
    ProgrammingButton as Button,
    dateLabel,
    days,
    Empty,
    PlanningCard,
    shiftDate,
    StateBadge,
    states,
    stateTheme,
    today,
} from './shared';
import type { PlanningItem, References, State } from './types';

export function PlanningCalendar({
    items,
    refs,
    from,
    until,
    view,
    selected,
    onSelect,
    onOpen,
    onDay,
}: {
    items: PlanningItem[];
    refs: References;
    from: string;
    until: string;
    view: string;
    selected: string[];
    onSelect: (uuid: string) => void;
    onOpen: (item: PlanningItem) => void;
    onDay: (date: string) => void;
}) {
    if (items.length === 0) {
        return (
            <Empty title="Aucun départ sur cette sélection">
                Préparez une période depuis un modèle ou ajustez les filtres ci-dessus pour afficher vos départs.
            </Empty>
        );
    }

    const dates: string[] = [];
    for (let d = from; d <= until; d = shiftDate(d, 1)) {
        dates.push(d);
    }

    const groupIds = [
        ...new Set(
            items.map((i) => `${i.snapshot.line_uuid}:${i.snapshot.direction}`),
        ),
    ];
    const blockedCount = items.filter((i) => i.state === 'blocked').length;
    const unassignedCount = items.filter(
        (i) => i.state === 'unassigned',
    ).length;

    const list = (
        <div className="grid gap-2">
            {(blockedCount > 0 || unassignedCount > 0) && (
                <div
                    role="status"
                    className="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl border border-border bg-muted/50 px-4 py-3 text-xs text-muted-foreground"
                >
                    <span className="inline-flex items-center gap-1.5 font-bold text-foreground">
                        <Info size={15} strokeWidth={2} className="text-kora dark:text-gold" />
                        À traiter sur cette sélection :
                    </span>
                    {blockedCount > 0 && (
                        <span className="inline-flex items-center gap-1.5 font-bold text-destructive">
                            <span className="size-2 rounded-full bg-destructive" />
                            {blockedCount} à corriger
                        </span>
                    )}
                    {unassignedCount > 0 && (
                        <span className="inline-flex items-center gap-1.5 font-bold text-sky-700 dark:text-sky-300">
                            <span className="size-2 rounded-full bg-sky-500" />
                            {unassignedCount} à affecter
                        </span>
                    )}
                </div>
            )}
            {items.map((item) => {
                const DirectionIcon =
                    item.snapshot.direction === 'outbound'
                        ? ArrowRight
                        : ArrowLeft;
                const hasBus = item.snapshot.bus_uuid !== null;

                return (
                    <div
                        key={item.uuid}
                        className={cn(
                            'flex items-center gap-3 rounded-xl border border-border bg-card p-3.5 transition-all hover:border-gold hover:shadow-xs',
                            item.state === 'blocked' &&
                            'border-destructive/30 bg-destructive/[0.02]',
                        )}
                    >
                        <input
                            type="checkbox"
                            className="size-4.5 rounded-sm accent-gold cursor-pointer"
                            checked={selected.includes(item.uuid)}
                            disabled={
                                item.manual || item.state === 'excluded'
                            }
                            onChange={() => onSelect(item.uuid)}
                            aria-label={`Sélectionner ${dateLabel(item.date)} à ${item.snapshot.time}`}
                        />
                        <button
                            className="grid min-w-0 flex-1 cursor-pointer gap-2 text-left sm:grid-cols-[110px_1fr_160px_140px] sm:items-center"
                            onClick={() => onOpen(item)}
                        >
                            <span className="flex items-center gap-2 text-xs">
                                <span
                                    className={`grid size-6 place-items-center rounded-md ${item.snapshot.direction === 'outbound'
                                            ? 'bg-digital/15 text-digital'
                                            : 'bg-gold/20 text-kora dark:text-gold'
                                        }`}
                                >
                                    <DirectionIcon size={13} strokeWidth={2.5} />
                                </span>
                                <div>
                                    <span className="text-[0.6875rem] font-semibold text-muted-foreground block">
                                        {dateLabel(item.date)}
                                    </span>
                                    <strong className="font-mono text-xs font-bold text-foreground">
                                        {item.snapshot.time}
                                    </strong>
                                </div>
                            </span>
                            <span className="truncate text-xs font-extrabold text-foreground">
                                {refs.lines.find(
                                    (l) =>
                                        l.uuid === item.snapshot.line_uuid,
                                )?.label ?? 'Ligne indisponible'}
                                <small className="mt-0.5 block truncate font-normal text-muted-foreground">
                                    {item.programmeName}
                                    {item.isException ? ' · Exception' : ''}
                                </small>
                            </span>
                            <span
                                className={cn(
                                    'flex items-center gap-1.5 truncate text-xs',
                                    hasBus
                                        ? 'text-muted-foreground'
                                        : 'font-semibold text-sky-700 dark:text-sky-300',
                                )}
                            >
                                <Bus size={13} strokeWidth={1.75} />
                                {refs.buses.find(
                                    (b) =>
                                        b.uuid === item.snapshot.bus_uuid,
                                )?.label ?? 'Bus à affecter'}
                            </span>
                            <div className="flex items-center justify-end sm:justify-start">
                                <StateBadge state={item.state} />
                            </div>
                        </button>
                    </div>
                );
            })}
        </div>
    );

    return (
        <div className="@container">
            {view === 'week' && (
                <div className="hidden @[1100px]:block">
                    <div className="grid grid-cols-[180px_repeat(7,minmax(0,1fr))] gap-px overflow-hidden rounded-2xl border border-border bg-border">
                        <div className="bg-muted/70 p-3.5 text-[0.6875rem] font-extrabold tracking-wider text-muted-foreground uppercase">
                            Liaison / Sens
                        </div>
                        {dates.map((date, i) => (
                            <div
                                key={date}
                                className={`bg-muted/70 p-3 text-center ${date === today() ? 'bg-gold/15 text-foreground' : ''}`}
                            >
                                <span className="text-[0.625rem] font-extrabold tracking-wider uppercase text-muted-foreground">
                                    {days[i]}
                                </span>
                                <strong className="mt-0.5 block text-xs font-extrabold tabular-nums text-foreground">
                                    {dateLabel(date)}
                                </strong>
                            </div>
                        ))}
                        {groupIds.map((key) => {
                            const row = items.filter(
                                (i) =>
                                    `${i.snapshot.line_uuid}:${i.snapshot.direction}` ===
                                    key,
                            );
                            const sample = row[0];

                            return (
                                <div key={key} className="contents">
                                    <div className="bg-card p-4">
                                        <p className="text-xs font-extrabold text-foreground">
                                            {
                                                refs.lines.find(
                                                    (l) =>
                                                        l.uuid ===
                                                        sample.snapshot
                                                            .line_uuid,
                                                )?.label
                                            }
                                        </p>
                                        <div className="mt-2 flex items-center gap-1.5">
                                            <span
                                                className={`rounded px-1.5 py-0.5 text-[0.625rem] font-bold ${sample.snapshot.direction === 'outbound'
                                                        ? 'bg-digital/15 text-digital'
                                                        : 'bg-gold/20 text-kora dark:text-gold'
                                                    }`}
                                            >
                                                {sample.snapshot.direction === 'outbound'
                                                    ? 'Aller'
                                                    : 'Retour'}
                                            </span>
                                            <span className="text-[0.6875rem] text-muted-foreground truncate">
                                                {sample.snapshot.timezone}
                                            </span>
                                        </div>
                                    </div>
                                    {dates.map((date) => {
                                        const daily = row.filter(
                                            (i) => i.date === date,
                                        );

                                        return (
                                            <div
                                                key={date}
                                                className={`grid content-start gap-2 p-2 ${date === today() ? 'bg-gold/[0.04]' : 'bg-card'}`}
                                            >
                                                {daily
                                                    .slice(0, 3)
                                                    .map((item) => (
                                                        <PlanningCard
                                                            key={item.uuid}
                                                            item={item}
                                                            refs={refs}
                                                            onOpen={() =>
                                                                onOpen(item)
                                                            }
                                                        />
                                                    ))}
                                                {daily.length > 3 && (
                                                    <button
                                                        className="p-1.5 text-center text-xs font-semibold text-kora hover:underline dark:text-gold"
                                                        onClick={() =>
                                                            onDay(date)
                                                        }
                                                    >
                                                        + {daily.length - 3} autre(s)
                                                    </button>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                        Horaires locaux au départ · Cliquez sur un départ pour voir les détails, affecter un bus ou publier.
                    </p>
                </div>
            )}
            {view === 'month' && (
                <div className="hidden grid-cols-7 gap-2 md:grid">
                    {dates.map((date) => {
                        const daily = items.filter((i) => i.date === date);
                        const stateCounts: Partial<Record<State, number>> = {};

                        for (const i of daily) {
                            stateCounts[i.state] =
                                (stateCounts[i.state] ?? 0) + 1;
                        }

                        const statesPresent = Object.keys(
                            stateCounts,
                        ) as State[];

                        return (
                            <button
                                key={date}
                                onClick={() => onDay(date)}
                                className={cn(
                                    'grid min-h-28 content-start gap-1.5 rounded-xl border border-border bg-card p-3 text-left transition-all hover:border-gold hover:shadow-xs',
                                    date === today() && 'border-gold bg-gold/[0.04] ring-1 ring-gold/40',
                                )}
                            >
                                <span className="text-xs font-extrabold tabular-nums text-foreground">
                                    {dateLabel(date)}
                                </span>
                                <span className="text-xs font-semibold text-muted-foreground">
                                    {daily.length} départ(s)
                                </span>
                                {statesPresent.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {statesPresent.map((s) => (
                                            <span
                                                key={s}
                                                className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-[0.625rem] font-bold text-foreground"
                                                title={`${stateCounts[s]} ${states[s].toLowerCase()}`}
                                            >
                                                <span
                                                    className={cn(
                                                        'size-1.5 rounded-full',
                                                        stateTheme[s].dot,
                                                    )}
                                                />
                                                {stateCounts[s]}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
            <div
                className={
                    view === 'week'
                        ? '@[1100px]:hidden'
                        : view === 'month'
                            ? 'md:hidden'
                            : ''
                }
            >
                {list}
            </div>
            {view !== 'list' && (
                <div className="mt-4 flex justify-end">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onDay(from)}
                        className="gap-1 text-xs text-kora hover:underline dark:text-gold"
                    >
                        Afficher la vue liste complète <ChevronRight size={14} />
                    </Button>
                </div>
            )}
        </div>
    );
}
