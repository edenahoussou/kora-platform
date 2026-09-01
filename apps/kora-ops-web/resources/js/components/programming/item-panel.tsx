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
    MapPin,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
    PrimaryButton,
    ResourceFields,
    StateBadge,
    useProgrammingRequest,
} from './shared';
import type { PlanningItem, References, Slot } from './types';

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

    const assignedBus = refs.buses.find((b) => b.uuid === slot.bus_uuid);
    const DirectionIcon =
        slot.direction === 'outbound' ? ArrowRight : ArrowLeft;

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
                <SheetContent className="w-full overflow-y-auto px-6 pb-8 sm:max-w-[520px]">
                    <SheetHeader className="border-b border-border px-0 pb-4">
                        <div className="flex items-center gap-2">
                            <span
                                className={`grid size-7 place-items-center rounded-lg text-xs font-black ${
                                    slot.direction === 'outbound'
                                        ? 'bg-digital/15 text-digital'
                                        : 'bg-gold/20 text-kora dark:text-gold'
                                }`}
                            >
                                <DirectionIcon size={15} strokeWidth={2.5} />
                            </span>
                            <SheetTitle className="text-xl font-extrabold tracking-[-0.03em] text-foreground">
                                {line?.label ?? 'Départ'}
                            </SheetTitle>
                        </div>
                        <SheetDescription className="mt-1 flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <Calendar size={13} /> {dateLabel(item.date)} ·{' '}
                            <Clock size={13} />{' '}
                            <span className="font-mono font-bold text-foreground">
                                {item.snapshot.time}
                            </span>{' '}
                            · {item.snapshot.timezone}
                        </SheetDescription>
                        <div className="pt-2">
                            <StateBadge state={item.state} />
                        </div>
                    </SheetHeader>

                    <div className="mt-5 grid gap-5">
                        <Errors errors={api.errors} />

                        {item.blockers.length > 0 && (
                            <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-xs font-semibold text-destructive">
                                <div className="mb-1.5 flex items-center gap-2 font-extrabold">
                                    <AlertTriangle size={15} />
                                    Blocages à résoudre avant publication :
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

                        {editing ? (
                            <div className="grid gap-5 rounded-2xl border border-border bg-background p-5">
                                <div>
                                    <h3 className="text-sm font-extrabold text-foreground">
                                        Modifier ce départ ponctuel
                                    </h3>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Ces changements s'appliquent uniquement
                                        à cette date spécifique sans altérer le
                                        modèle général.
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
                            <>
                                {/* PARCOURS ET ARRÊTS */}
                                <section className="grid gap-3 rounded-2xl border border-border bg-card p-4">
                                    <h3 className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                        <MapPin
                                            size={14}
                                            className="text-kora dark:text-gold"
                                        />
                                        Itinéraire & Heures de passage
                                    </h3>
                                    <div className="space-y-3 pt-1">
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

                                            return (
                                                <div
                                                    key={stop.stop_uuid}
                                                    className="flex items-center justify-between text-xs"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span className="size-2 rounded-full bg-gold" />
                                                        <span className="font-semibold text-foreground">
                                                            {line?.stops.find(
                                                                (s) =>
                                                                    s.uuid ===
                                                                    stop.stop_uuid,
                                                            )?.label ??
                                                                `Arrêt ${i + 1}`}
                                                        </span>
                                                    </div>
                                                    <span className="font-mono font-bold text-foreground">
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
                                                                +J{dayOffset}
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </section>

                                {/* RESSOURCES AFFECTÉES */}
                                <section className="grid gap-3 rounded-2xl border border-border bg-card p-4">
                                    <h3 className="flex items-center gap-1.5 text-xs font-extrabold text-foreground">
                                        <Bus
                                            size={14}
                                            className="text-kora dark:text-gold"
                                        />
                                        Ressources affectées
                                    </h3>

                                    <div className="flex items-center justify-between rounded-lg bg-muted/40 p-2.5 text-xs">
                                        <span className="font-medium text-muted-foreground">
                                            Bus :
                                        </span>
                                        <span className="font-extrabold text-foreground">
                                            {assignedBus
                                                ? `${assignedBus.label} (${assignedBus.capacity} pl)`
                                                : 'Non affecté'}
                                        </span>
                                    </div>

                                    <div className="grid gap-2 text-xs">
                                        {crewFields.map((c) => {
                                            const person = refs.people.find(
                                                (p) => p.uuid === slot[c.key],
                                            );

                                            return (
                                                <div
                                                    key={c.key}
                                                    className="flex items-center justify-between border-b border-border/40 py-1 text-xs last:border-none"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {c.label}
                                                    </span>
                                                    <span className="font-semibold text-foreground">
                                                        {person?.label ??
                                                            'Non affecté'}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
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
                                    {item.protected && (
                                        <span className="mt-1 flex items-center gap-1 font-semibold text-sky-700 dark:text-sky-300">
                                            <ShieldAlert size={13} /> Départ
                                            protégé (billets vendus).
                                        </span>
                                    )}
                                </section>

                                {/* ACTIONS */}
                                <div className="mt-auto grid gap-2.5 pt-4">
                                    {item.departureUuid && (
                                        <Button asChild className="gap-2">
                                            <Link
                                                href={departures.show(
                                                    item.departureUuid,
                                                )}
                                            >
                                                <FileCheck2 size={15} />
                                                Ouvrir la fiche de vente et
                                                contrôle
                                            </Link>
                                        </Button>
                                    )}

                                    {!item.protected &&
                                        item.state !== 'excluded' && (
                                            <Button
                                                variant="outline"
                                                onClick={() => setEditing(true)}
                                                className="gap-2"
                                            >
                                                Modifier cette date / affecter
                                                les ressources
                                            </Button>
                                        )}

                                    {item.state === 'ready' && (
                                        <PrimaryButton
                                            onClick={() =>
                                                setConfirm('publish')
                                            }
                                            className="gap-2"
                                        >
                                            <CheckCircle2 size={16} />
                                            Publier ce départ (Mettre en vente)
                                        </PrimaryButton>
                                    )}

                                    {canCancel &&
                                        !item.protected &&
                                        item.state !== 'excluded' && (
                                            <Button
                                                variant="ghost"
                                                className="gap-1.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    setConfirm('exclude')
                                                }
                                            >
                                                <Trash2 size={14} />
                                                Exclure cette date du programme
                                            </Button>
                                        )}
                                </div>
                            </>
                        )}
                    </div>
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
