import { Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Bus,
    Calendar,
    CheckCircle2,
    Clock,
    FileCheck2,
    History,
    MapPin,
    Pencil,
    ShieldAlert,
    Trash2,
    User,
    Users,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import departures from '@/routes/departures';
import programming from '@/routes/programming';
import {
    ProgrammingButton as Button,
    ProgrammingInput as Input,
    Confirm,
    crewFields,
    dateLabel,
    Errors,
    Field,
    OccupancyRing,
    PrimaryButton,
    ResourceFields,
    states,
    stateTheme,
    useProgrammingRequest,
} from './shared';
import type { PlanningItem, References, Slot } from './types';

type PanelTab = 'overview' | 'resources' | 'activity';

const tabs: { key: PanelTab; label: string; icon: LucideIcon }[] = [
    { key: 'overview', label: 'Aperçu', icon: MapPin },
    { key: 'resources', label: 'Ressources', icon: Bus },
    { key: 'activity', label: 'Activité', icon: History },
];

const crewIcons: Record<string, LucideIcon> = {
    bus_driver: User,
    conductor: Users,
    mechanic: Wrench,
};

const eventLabels: Record<string, string> = {
    'programming.departure.published': 'Départ publié et mis en vente',
    'programming.exception': 'Exception manuelle appliquée à cette date',
};

function eventLabel(event: string): string {
    return (
        eventLabels[event] ??
        event.replace(/^programming\./, '').replace(/[._]/g, ' ')
    );
}

function activityDate(at: string | null): string {
    if (!at) {
        return '';
    }

    return new Date(at).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function ItemPanel({
    item,
    canCancel,
    refs,
    onClose,
}: {
    item: PlanningItem;
    canCancel: boolean;
    refs: References;
    onClose: () => void;
}) {
    const [tab, setTab] = useState<PanelTab>('overview');
    const [editing, setEditing] = useState(false);
    const [slot, setSlot] = useState(item.snapshot);
    const [confirm, setConfirm] = useState<'publish' | 'exclude' | null>(null);
    const api = useProgrammingRequest();
    const line = refs.lines.find((l) => l.uuid === slot.line_uuid);
    const dirty = JSON.stringify(slot) !== JSON.stringify(item.snapshot);

    function close() {
        if (
            !api.busy &&
            (!dirty ||
                window.confirm(
                    'Quitter sans enregistrer les modifications de cette date ?',
                ))
        ) {
            onClose();
        }
    }

    async function act(operation: string) {
        const changes: Partial<Slot> = {
            time: slot.time,
            bus_uuid: slot.bus_uuid,
        };
        crewFields.forEach((c) => {
            changes[c.key] = slot[c.key];
        });
        const result = await api.send<{
            processed: number;
            errors: Record<string, string>;
        }>(programming.actions.url(), {
            operation,
            items: [{ uuid: item.uuid, revision: item.revision }],
            changes,
        });

        if (result?.errors && Object.keys(result.errors).length) {
            api.setErrors(result.errors);
        } else if (result) {
            router.reload();
            onClose();
        }

        setConfirm(null);
    }

    function startEditing() {
        setTab('resources');
        setEditing(true);
    }

    const assignedBus = refs.buses.find((b) => b.uuid === slot.bus_uuid);
    const DirectionIcon =
        slot.direction === 'outbound' ? ArrowRight : ArrowLeft;
    const StateIcon = stateTheme[item.state].icon;
    const occupancy = item.occupancy;
    const occupancyPct =
        occupancy?.ratio !== null && occupancy?.ratio !== undefined
            ? Math.min(100, Math.round(occupancy.ratio * 100))
            : null;
    const canEdit = !item.protected && item.state !== 'excluded';
    const canExclude =
        canCancel && !item.protected && item.state !== 'excluded';
    const history = item.history ?? [];

    return (
        <>
            <Sheet
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <SheetContent className="w-full gap-0 p-0 sm:max-w-[560px] [&>button]:top-5 [&>button]:right-5 [&>button]:z-10 [&>button]:bg-white/10 [&>button]:text-white [&>button]:opacity-90 [&>button]:hover:opacity-100">
                    {/* HERO */}
                    <div className="relative shrink-0 overflow-hidden bg-gradient-to-br from-[var(--kora)] to-[var(--digital)] px-6 pt-6 pb-6">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-20 -right-12 size-52 rounded-full bg-gold/15 blur-3xl"
                        />
                        <span className="relative flex items-center gap-1.5 text-[0.5625rem] font-black tracking-[0.14em] text-gold uppercase">
                            <DirectionIcon size={11} strokeWidth={3} />
                            {slot.direction === 'outbound'
                                ? 'Départ · Aller'
                                : 'Départ · Retour'}
                        </span>
                        <div className="relative mt-3 flex items-end justify-between gap-5">
                            <div className="min-w-0">
                                <SheetTitle className="text-[1.375rem] leading-tight font-extrabold tracking-[-0.03em] text-white">
                                    {line?.label ?? 'Départ'}
                                </SheetTitle>
                                <SheetDescription className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-medium text-white/70">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Calendar size={12} />
                                        {dateLabel(item.date)}
                                    </span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <Clock size={12} />
                                        <span className="font-mono font-bold text-white tabular-nums">
                                            {item.snapshot.time}
                                        </span>
                                        · {item.snapshot.timezone}
                                    </span>
                                </SheetDescription>
                                <div className="mt-3.5 flex flex-wrap items-center gap-2">
                                    <span className="inline-flex items-center gap-1.5 rounded-md bg-white/10 px-2 py-1 text-[0.625rem] font-extrabold text-white ring-1 ring-white/20">
                                        <StateIcon size={12} strokeWidth={2} />
                                        {states[item.state]}
                                    </span>
                                    {item.isException && (
                                        <span className="inline-flex items-center gap-1.5 rounded-md bg-violet-400/15 px-2 py-1 text-[0.625rem] font-extrabold text-violet-200 ring-1 ring-violet-300/25">
                                            Exception
                                        </span>
                                    )}
                                    {item.protected && (
                                        <span className="inline-flex items-center gap-1.5 rounded-md bg-sky-400/15 px-2 py-1 text-[0.625rem] font-extrabold text-sky-200 ring-1 ring-sky-300/25">
                                            <ShieldAlert size={11} />
                                            Protégé · billets vendus
                                        </span>
                                    )}
                                </div>
                            </div>
                            <OccupancyRing occupancy={occupancy} />
                        </div>
                    </div>

                    {/* TABS */}
                    <div className="flex shrink-0 gap-1 border-b border-border/60 px-6">
                        {tabs.map(({ key, label, icon: Icon }) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setTab(key)}
                                className={cn(
                                    '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3.5 py-3 text-[0.6875rem] font-extrabold tracking-[0.035em] whitespace-nowrap transition-colors',
                                    tab === key
                                        ? 'border-gold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                <Icon size={13} strokeWidth={2} />
                                {label}
                                {key === 'activity' && history.length > 0 && (
                                    <span className="rounded-full bg-muted px-1.5 py-0.5 text-[0.5625rem] font-bold text-muted-foreground tabular-nums">
                                        {history.length}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>

                    {/* CONTENT */}
                    <div className="flex-1 overflow-y-auto px-6 py-5">
                        {tab === 'overview' && (
                            <div className="grid gap-5">
                                <Errors errors={api.errors} />

                                {item.blockers.length > 0 && (
                                    <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-xs font-semibold text-destructive">
                                        <div className="mb-1.5 flex items-center gap-2 font-extrabold">
                                            <AlertTriangle size={15} />
                                            Blocages à résoudre avant
                                            publication :
                                        </div>
                                        <ul className="list-inside list-disc space-y-1">
                                            {item.blockers.map((b, i) => (
                                                <li key={i}>{b}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}

                                {item.warnings.map((w) => (
                                    <div
                                        key={w}
                                        className="rounded-xl border border-gold/30 bg-gold/10 p-3.5 text-xs text-foreground"
                                    >
                                        {w}
                                    </div>
                                ))}

                                {occupancy !== null && (
                                    <div className="grid grid-cols-3 gap-2.5">
                                        <div className="rounded-xl bg-muted/40 px-3.5 py-3">
                                            <span className="block text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                                Sièges vendus
                                            </span>
                                            <span className="mt-1 block text-base font-extrabold text-foreground tabular-nums">
                                                {occupancy.booked}
                                            </span>
                                        </div>
                                        <div className="rounded-xl bg-muted/40 px-3.5 py-3">
                                            <span className="block text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                                Capacité
                                            </span>
                                            <span className="mt-1 block text-base font-extrabold text-foreground tabular-nums">
                                                {occupancy.capacity ?? '—'}
                                            </span>
                                        </div>
                                        <div className="rounded-xl bg-muted/40 px-3.5 py-3">
                                            <span className="block text-[0.5625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                                                Remplissage
                                            </span>
                                            <span
                                                className={cn(
                                                    'mt-1 block text-base font-extrabold tabular-nums',
                                                    occupancyPct !== null &&
                                                        occupancyPct >= 70
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : occupancyPct !==
                                                                null &&
                                                            occupancyPct >= 30
                                                          ? 'text-gold'
                                                          : 'text-digital',
                                                )}
                                            >
                                                {occupancyPct !== null
                                                    ? `${occupancyPct}%`
                                                    : '—'}
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {/* TIMELINE DES ARRÊTS */}
                                <section className="grid gap-4 rounded-2xl border border-border bg-card p-5">
                                    <h3 className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                        <MapPin
                                            size={14}
                                            className="text-kora dark:text-gold"
                                        />
                                        Itinéraire & heures de passage
                                    </h3>
                                    <ol>
                                        {slot.stops.map((stop, i) => {
                                            const minutes =
                                                stop.arrival_minutes ??
                                                stop.departure_minutes ??
                                                0;
                                            const instant = new Date(
                                                new Date(
                                                    item.scheduledAt,
                                                ).getTime() +
                                                    minutes * 60000,
                                            );
                                            const localDate =
                                                new Intl.DateTimeFormat(
                                                    'en-CA',
                                                    { timeZone: stop.timezone },
                                                ).format(instant);
                                            const dayOffset = Math.round(
                                                (new Date(localDate).getTime() -
                                                    new Date(
                                                        item.date,
                                                    ).getTime()) /
                                                    86400000,
                                            );
                                            const first = i === 0;
                                            const last =
                                                i === slot.stops.length - 1;

                                            return (
                                                <li
                                                    key={stop.stop_uuid}
                                                    className="relative flex gap-4 pb-5 last:pb-0"
                                                >
                                                    {!last && (
                                                        <span
                                                            aria-hidden
                                                            className="absolute top-6 left-[7px] h-[calc(100%-18px)] w-px bg-border"
                                                        />
                                                    )}
                                                    <span
                                                        className={cn(
                                                            'relative z-10 mt-0.5 grid size-4 shrink-0 place-items-center rounded-full border-2 bg-card',
                                                            first
                                                                ? 'border-gold'
                                                                : last
                                                                  ? 'border-kora dark:border-gold'
                                                                  : 'border-border',
                                                        )}
                                                    >
                                                        {(first || last) && (
                                                            <span
                                                                className={cn(
                                                                    'size-1.5 rounded-full',
                                                                    first
                                                                        ? 'bg-gold'
                                                                        : 'bg-kora dark:bg-gold',
                                                                )}
                                                            />
                                                        )}
                                                    </span>
                                                    <div className="flex min-w-0 flex-1 items-start justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-bold text-foreground">
                                                                {line?.stops.find(
                                                                    (s) =>
                                                                        s.uuid ===
                                                                        stop.stop_uuid,
                                                                )?.label ??
                                                                    `Arrêt ${i + 1}`}
                                                            </p>
                                                            <p className="mt-0.5 text-[0.6875rem] font-medium text-muted-foreground">
                                                                {first
                                                                    ? 'Embarquement'
                                                                    : last
                                                                      ? 'Destination'
                                                                      : 'Arrêt intermédiaire'}
                                                            </p>
                                                        </div>
                                                        <p className="shrink-0 font-mono text-sm font-bold text-foreground tabular-nums">
                                                            {instant.toLocaleTimeString(
                                                                'fr-FR',
                                                                {
                                                                    timeZone:
                                                                        stop.timezone,
                                                                    hour: '2-digit',
                                                                    minute: '2-digit',
                                                                },
                                                            )}
                                                            {dayOffset > 0 && (
                                                                <span className="ml-1 text-[0.6875rem] font-bold text-gold">
                                                                    +J
                                                                    {dayOffset}
                                                                </span>
                                                            )}
                                                        </p>
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ol>
                                </section>

                                {/* ORIGINE DU DÉPART */}
                                <section className="rounded-xl border border-border bg-muted/30 p-3.5 text-xs text-muted-foreground">
                                    <div className="flex items-center justify-between">
                                        <span>Programme d'origine :</span>
                                        <strong className="text-foreground">
                                            {item.programmeName}
                                        </strong>
                                    </div>
                                    {item.isException && (
                                        <span className="mt-1 block font-semibold text-gold">
                                            • Ce départ comporte des exceptions
                                            manuelles.
                                        </span>
                                    )}
                                </section>
                            </div>
                        )}

                        {tab === 'resources' &&
                            (editing ? (
                                <div className="grid gap-5 rounded-2xl border border-border bg-background p-5">
                                    <div>
                                        <h3 className="text-sm font-extrabold text-foreground">
                                            Modifier ce départ ponctuel
                                        </h3>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Ces changements s'appliquent
                                            uniquement à cette date spécifique
                                            sans altérer le modèle général.
                                        </p>
                                    </div>

                                    <Field
                                        label="Heure locale de départ"
                                        icon={Clock}
                                        required
                                    >
                                        <Input
                                            type="time"
                                            value={slot.time}
                                            onChange={(e) =>
                                                setSlot({
                                                    ...slot,
                                                    time: e.target.value,
                                                })
                                            }
                                            className="h-11 font-mono font-bold"
                                        />
                                    </Field>

                                    <ResourceFields
                                        slot={slot}
                                        refs={refs}
                                        onChange={(values) =>
                                            setSlot({ ...slot, ...values })
                                        }
                                    />

                                    <div className="flex gap-2 pt-2">
                                        <PrimaryButton
                                            disabled={api.busy}
                                            onClick={() => act('change')}
                                            className="flex-1"
                                        >
                                            Enregistrer cette exception
                                        </PrimaryButton>
                                        <Button
                                            variant="ghost"
                                            onClick={() => {
                                                setSlot(item.snapshot);
                                                setEditing(false);
                                            }}
                                        >
                                            Annuler
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <section className="grid gap-3 rounded-2xl border border-border bg-card p-5">
                                    <div className="flex items-center justify-between">
                                        <h3 className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                            <Bus
                                                size={14}
                                                className="text-kora dark:text-gold"
                                            />
                                            Ressources affectées
                                        </h3>
                                        {canEdit && (
                                            <button
                                                type="button"
                                                onClick={() => setEditing(true)}
                                                className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[0.6875rem] font-extrabold text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground"
                                            >
                                                <Pencil size={12} />
                                                Ajuster
                                            </button>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-3 rounded-xl bg-muted/40 p-3.5">
                                        <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-card text-kora shadow-sm dark:text-gold">
                                            <Bus size={16} strokeWidth={2} />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-extrabold text-foreground">
                                                {assignedBus
                                                    ? assignedBus.label
                                                    : 'Bus à affecter'}
                                            </p>
                                            <p className="text-[0.6875rem] font-medium text-muted-foreground">
                                                {assignedBus
                                                    ? `${assignedBus.capacity} places · ${assignedBus.model}`
                                                    : 'Aucun véhicule assigné à cette date'}
                                            </p>
                                        </div>
                                        {assignedBus && (
                                            <span
                                                className={cn(
                                                    'shrink-0 rounded-md px-2 py-1 text-[0.5625rem] font-extrabold tracking-[0.06em] uppercase',
                                                    assignedBus.status ===
                                                        'active'
                                                        ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                                        : 'bg-destructive/15 text-destructive',
                                                )}
                                            >
                                                {assignedBus.status === 'active'
                                                    ? 'Disponible'
                                                    : 'Indisponible'}
                                            </span>
                                        )}
                                    </div>

                                    <div>
                                        {crewFields.map((c) => {
                                            const person = refs.people.find(
                                                (p) => p.uuid === slot[c.key],
                                            );
                                            const CrewIcon =
                                                crewIcons[c.role] ?? User;

                                            return (
                                                <div
                                                    key={c.key}
                                                    className="flex items-center gap-3 border-b border-border/40 py-2.5 last:border-none"
                                                >
                                                    <span className="grid size-7 shrink-0 place-items-center rounded-lg bg-muted/60 text-muted-foreground">
                                                        <CrewIcon
                                                            size={13}
                                                            strokeWidth={2}
                                                        />
                                                    </span>
                                                    <span className="flex-1 text-xs font-medium text-muted-foreground">
                                                        {c.label}
                                                    </span>
                                                    <span
                                                        className={cn(
                                                            'text-xs font-semibold',
                                                            person
                                                                ? 'text-foreground'
                                                                : 'text-muted-foreground/60',
                                                        )}
                                                    >
                                                        {person?.label ??
                                                            'À affecter'}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </section>
                            ))}

                        {tab === 'activity' &&
                            (history.length > 0 ? (
                                <ol className="px-1 py-1">
                                    {history.map((entry, i) => (
                                        <li
                                            key={i}
                                            className="relative flex gap-3.5 pb-5 last:pb-0"
                                        >
                                            {i < history.length - 1 && (
                                                <span
                                                    aria-hidden
                                                    className="absolute top-5 left-[5px] h-[calc(100%-14px)] w-px bg-border"
                                                />
                                            )}
                                            <span className="relative z-10 mt-1.5 size-3 shrink-0 rounded-full border-2 border-border bg-card" />
                                            <div className="min-w-0">
                                                <p className="text-xs leading-relaxed font-semibold text-foreground">
                                                    {eventLabel(entry.event)}
                                                </p>
                                                <p className="mt-0.5 text-[0.6875rem] font-medium text-muted-foreground">
                                                    {entry.actor ?? 'Système'} ·{' '}
                                                    {activityDate(entry.at)}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            ) : (
                                <div className="grid place-items-center gap-3 py-14 text-center">
                                    <span className="rounded-2xl bg-muted p-3.5 text-muted-foreground">
                                        <History size={22} strokeWidth={1.5} />
                                    </span>
                                    <div>
                                        <p className="text-sm font-bold text-foreground">
                                            Aucune activité enregistrée
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Les publications et exceptions de ce
                                            départ apparaîtront ici.
                                        </p>
                                    </div>
                                </div>
                            ))}
                    </div>

                    {/* ACTION BAR */}
                    {!editing &&
                        (item.state === 'ready' ||
                            item.departureUuid ||
                            canEdit ||
                            canExclude) && (
                            <div className="shrink-0 border-t border-border/70 bg-card/80 px-6 py-4">
                                <div className="flex items-center gap-2.5">
                                    {item.state === 'ready' && (
                                        <PrimaryButton
                                            onClick={() =>
                                                setConfirm('publish')
                                            }
                                            className="flex-1 gap-2"
                                        >
                                            <CheckCircle2 size={16} />
                                            Publier ce départ
                                        </PrimaryButton>
                                    )}

                                    {item.departureUuid && (
                                        <Button
                                            asChild
                                            variant={
                                                item.state === 'ready'
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                            className={cn(
                                                'gap-2',
                                                item.state !== 'ready' &&
                                                    'flex-1',
                                            )}
                                        >
                                            <Link
                                                href={departures.show(
                                                    item.departureUuid,
                                                )}
                                            >
                                                <FileCheck2 size={15} />
                                                Fiche de vente et contrôle
                                            </Link>
                                        </Button>
                                    )}

                                    {canEdit && (
                                        <Button
                                            variant="outline"
                                            onClick={startEditing}
                                            className="gap-2"
                                        >
                                            <Pencil size={14} />
                                            Modifier
                                        </Button>
                                    )}

                                    {canExclude && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Exclure cette date du programme"
                                            aria-label="Exclure cette date du programme"
                                            onClick={() =>
                                                setConfirm('exclude')
                                            }
                                            className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        >
                                            <Trash2 size={15} />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                </SheetContent>
            </Sheet>

            <Confirm
                open={confirm !== null}
                title={
                    confirm === 'publish'
                        ? 'Ouvrir ce départ à la vente ?'
                        : 'Exclure cette date du programme ?'
                }
                onClose={() => setConfirm(null)}
                action={
                    <PrimaryButton
                        disabled={api.busy}
                        onClick={() =>
                            act(confirm === 'publish' ? 'publish' : 'exclude')
                        }
                    >
                        {api.busy
                            ? 'Traitement en cours…'
                            : confirm === 'publish'
                              ? 'Confirmer la publication'
                              : 'Confirmer l’exclusion'}
                    </PrimaryButton>
                }
            >
                <p className="text-xs leading-relaxed text-muted-foreground">
                    {confirm === 'publish'
                        ? 'Le départ deviendra visible pour tous les guichets et canaux de vente autorisés.'
                        : 'Le départ sera retiré du planning pour cette date. Vous pourrez le rétablir ultérieurement si nécessaire.'}
                </p>
            </Confirm>
        </>
    );
}
