import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Bus,
    ChevronRight,
    Info,
    User,
    Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import {
    ProgrammingButton as Button,
    dateLabel,
    days,
    Empty,
    FillGauge,
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
                Préparez une période depuis un modèle ou ajustez les filtres
                ci-dessus pour afficher vos départs.
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
        <div className="grid gap-3">
            {(blockedCount > 0 || unassignedCount > 0) && (
                <div
                    role="status"
                    className="flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-xl border border-border bg-kora/[0.03] px-4 py-3 text-xs text-muted-foreground"
                >
                    <span className="inline-flex items-center gap-1.5 font-bold text-foreground">
                        <Info
                            size={15}
                            strokeWidth={2}
                            className="text-kora dark:text-gold"
                        />
                        À traiter sur cette sélection :
                    </span>
                    {blockedCount > 0 && (
                        <span className="inline-flex items-center gap-1.5 font-bold text-destructive">
                            <span className="size-2 rounded-full bg-destructive" />
                            {blockedCount} à corriger
                        </span>
                    )}
                    {unassignedCount > 0 && (
                        <span className="inline-flex items-center gap-1.5 font-bold text-digital dark:text-sky-300">
                            <span className="size-2 rounded-full bg-digital dark:bg-sky-400" />
                            {unassignedCount} à affecter
                        </span>
                    )}
                </div>
            )}
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {items.map((item, idx) => {
                    const DirectionIcon =
                        item.snapshot.direction === 'outbound'
                            ? ArrowRight
                            : ArrowLeft;
                    const isOutbound = item.snapshot.direction === 'outbound';
                    const line = refs.lines.find(
                        (l) => l.uuid === item.snapshot.line_uuid,
                    );
                    const bus = refs.buses.find(
                        (b) => b.uuid === item.snapshot.bus_uuid,
                    );
                    const driver = refs.people.find(
                        (p) => p.uuid === item.snapshot.driver_uuid,
                    );
                    const conductor = refs.people.find(
                        (p) => p.uuid === item.snapshot.conductor_uuid,
                    );
                    const hasBus = item.snapshot.bus_uuid !== null;
                    const dayName =
                        days[
                            new Date(item.date + 'T12:00:00').getUTCDay() === 0
                                ? 6
                                : new Date(
                                      item.date + 'T12:00:00',
                                  ).getUTCDay() - 1
                        ];
                    const dayNum = new Date(
                        item.date + 'T12:00:00',
                    ).getUTCDate();

                    return (
                        <div
                            key={item.uuid}
                            style={{
                                animationDelay: `${Math.min(idx * 30, 300)}ms`,
                            }}
                            className={cn(
                                'group relative animate-in rounded-2xl border bg-card p-4 transition-all duration-200 duration-400 fade-in slide-in-from-bottom-2 hover:-translate-y-0.5 hover:shadow-[0_8px_30px_rgba(0,29,78,0.08)]',
                                item.state === 'blocked'
                                    ? 'border-destructive/30 hover:border-destructive/50 hover:shadow-[0_8px_30px_rgba(220,38,38,0.08)]'
                                    : 'border-border hover:border-gold hover:ring-1 hover:ring-gold/20',
                            )}
                        >
                            {/* Checkbox de sélection */}
                            <input
                                type="checkbox"
                                className="absolute top-3 left-3 size-4 cursor-pointer rounded-sm accent-gold opacity-0 transition-opacity group-hover:opacity-100 [.touch_&]:opacity-100"
                                checked={selected.includes(item.uuid)}
                                disabled={
                                    item.manual || item.state === 'excluded'
                                }
                                onChange={() => onSelect(item.uuid)}
                                aria-label={`Sélectionner ${dateLabel(item.date)} à ${item.snapshot.time}`}
                            />

                            <button
                                className="grid w-full cursor-pointer gap-3 text-left"
                                onClick={() => onOpen(item)}
                            >
                                {/* En-tête : badge date + direction + heure */}
                                <div className="flex items-start gap-3">
                                    <div className="flex shrink-0 flex-col items-center rounded-lg bg-kora/[0.06] px-2 py-1.5 dark:bg-gold/[0.08]">
                                        <span className="text-[0.5625rem] font-bold tracking-wider text-muted-foreground uppercase">
                                            {dayName}
                                        </span>
                                        <strong className="text-lg leading-none font-black text-foreground tabular-nums">
                                            {dayNum}
                                        </strong>
                                    </div>
                                    <div className="grid min-w-0 flex-1 gap-1">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'grid size-5 place-items-center rounded-md transition-transform duration-200',
                                                    isOutbound
                                                        ? 'bg-digital/15 text-digital group-hover:translate-x-0.5'
                                                        : 'bg-gold/20 text-kora group-hover:-translate-x-0.5 dark:text-gold',
                                                )}
                                            >
                                                <DirectionIcon
                                                    size={12}
                                                    strokeWidth={2.5}
                                                />
                                            </span>
                                            <strong className="text-base text-foreground tabular-nums">
                                                {item.snapshot.time}
                                            </strong>
                                            {item.isException && (
                                                <span className="rounded bg-violet-500/15 px-1 py-0.5 text-[0.5625rem] font-bold text-violet-600 dark:text-violet-400">
                                                    Exception
                                                </span>
                                            )}
                                        </div>
                                        <span className="truncate text-xs font-extrabold text-foreground">
                                            {line?.label ??
                                                'Ligne indisponible'}
                                        </span>
                                        <span className="truncate text-[0.6875rem] text-muted-foreground">
                                            {item.programmeName}
                                        </span>
                                    </div>
                                </div>

                                {/* Ressources : bus + équipage */}
                                <div className="grid gap-1.5 border-t border-border/60 pt-2.5">
                                    <span
                                        className={cn(
                                            'flex items-center gap-1.5 truncate text-xs',
                                            hasBus
                                                ? 'text-muted-foreground'
                                                : 'font-semibold text-sky-700 dark:text-sky-300',
                                        )}
                                    >
                                        <Bus
                                            size={12}
                                            strokeWidth={1.75}
                                            className={
                                                hasBus ? '' : 'animate-pulse'
                                            }
                                        />
                                        {bus?.label ?? 'Bus à affecter'}
                                        {bus && hasBus && (
                                            <span className="text-muted-foreground/60">
                                                · {bus.capacity} pl.
                                            </span>
                                        )}
                                    </span>
                                    {(driver || conductor) && (
                                        <div className="flex items-center gap-3 text-[0.625rem] text-muted-foreground">
                                            {driver && (
                                                <span className="flex items-center gap-1 truncate">
                                                    <User
                                                        size={10}
                                                        strokeWidth={2}
                                                        className="text-kora dark:text-gold"
                                                    />
                                                    {driver.label}
                                                </span>
                                            )}
                                            {conductor && (
                                                <span className="flex items-center gap-1 truncate">
                                                    <Users
                                                        size={10}
                                                        strokeWidth={2}
                                                        className="text-kora dark:text-gold"
                                                    />
                                                    {conductor.label}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Jauge de remplissage */}
                                {item.occupancy !== null && (
                                    <FillGauge occupancy={item.occupancy} />
                                )}

                                {/* État + alertes */}
                                <div className="flex items-center justify-between gap-2 border-t border-border/60 pt-2.5">
                                    <StateBadge state={item.state} />
                                    {item.blockers.length > 0 && (
                                        <span className="flex items-center gap-1 text-[0.625rem] font-semibold text-destructive">
                                            <AlertTriangle size={11} />
                                            {item.blockers.length} alerte(s)
                                        </span>
                                    )}
                                </div>
                                {item.blockers.length > 0 && (
                                    <p className="line-clamp-2 text-[0.625rem] leading-4 text-destructive/90">
                                        {item.blockers[0]}
                                    </p>
                                )}
                            </button>
                        </div>
                    );
                })}
            </div>
        </div>
    );

    return (
        <div className="@container">
            {view === 'week' && (
                <div className="hidden @[1100px]:block">
                    <div className="grid grid-cols-[180px_repeat(7,minmax(0,1fr))] gap-px overflow-hidden rounded-2xl border border-kora/10 bg-kora/5">
                        <div className="bg-kora/[0.04] p-3.5 text-[0.6875rem] font-extrabold tracking-wider text-muted-foreground uppercase">
                            Liaison / Sens
                        </div>
                        {dates.map((date, i) => (
                            <div
                                key={date}
                                className={cn(
                                    'p-3 text-center transition-colors',
                                    date === today()
                                        ? 'bg-gold/15 ring-1 ring-gold/30 ring-inset'
                                        : 'bg-kora/[0.04]',
                                )}
                            >
                                <span className="text-[0.625rem] font-extrabold tracking-wider text-muted-foreground uppercase">
                                    {days[i]}
                                </span>
                                <strong
                                    className={cn(
                                        'mt-0.5 block text-xs font-extrabold tabular-nums',
                                        date === today()
                                            ? 'text-gold'
                                            : 'text-foreground',
                                    )}
                                >
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
                                    <div className="border-l-2 border-gold/30 bg-card p-4">
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
                                                className={`rounded px-1.5 py-0.5 text-[0.625rem] font-bold ${
                                                    sample.snapshot
                                                        .direction ===
                                                    'outbound'
                                                        ? 'bg-digital/15 text-digital'
                                                        : 'bg-gold/20 text-kora dark:text-gold'
                                                }`}
                                            >
                                                {sample.snapshot.direction ===
                                                'outbound'
                                                    ? 'Aller'
                                                    : 'Retour'}
                                            </span>
                                            <span className="truncate text-[0.6875rem] text-muted-foreground">
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
                                                className={cn(
                                                    'grid content-start gap-2 p-2 transition-colors',
                                                    date === today()
                                                        ? 'bg-gold/[0.05]'
                                                        : 'bg-card hover:bg-gold/[0.02]',
                                                )}
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
                                                        className="animate-pulse p-1.5 text-center text-xs font-semibold text-kora hover:underline dark:text-gold"
                                                        onClick={() =>
                                                            onDay(date)
                                                        }
                                                    >
                                                        + {daily.length - 3}{' '}
                                                        autre(s)
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
                        Horaires locaux au départ · Cliquez sur un départ pour
                        voir les détails, affecter un bus ou publier.
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
                                    'grid min-h-28 content-start gap-1.5 rounded-xl border p-3 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_30px_rgba(0,29,78,0.08)]',
                                    date === today()
                                        ? 'border-gold bg-gold/[0.08] ring-1 ring-gold/40'
                                        : 'border-border bg-gradient-to-br from-card to-kora/[0.02] hover:border-gold',
                                )}
                            >
                                <span
                                    className={cn(
                                        'text-xs font-extrabold tabular-nums',
                                        date === today()
                                            ? 'text-gold'
                                            : 'text-foreground',
                                    )}
                                >
                                    {dateLabel(date)}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs font-semibold',
                                        daily.length === 0
                                            ? 'text-muted-foreground/50'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {daily.length} départ(s)
                                </span>
                                {statesPresent.length > 0 && (
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {statesPresent.map((s) => (
                                            <span
                                                key={s}
                                                className={cn(
                                                    'inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[0.625rem] font-bold transition-transform hover:scale-105',
                                                    stateTheme[s].badge,
                                                )}
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
                        Afficher la vue liste complète{' '}
                        <ChevronRight size={14} />
                    </Button>
                </div>
            )}
        </div>
    );
}
