import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CalendarClock,
    Check,
    Clock3,
    Copy,
    Globe2,
    Info,
    Layers,
    Plus,
    Repeat2,
    Route,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import {
    ProgrammingButton as Button,
    ProgrammingInput as Input,
    days,
    DirectionSection,
    Errors,
    Field,
    freshSlot,
    PrimaryButton,
    ResourceFields,
    SelectField,
    SlotSummary,
    Surface,
    useUnsavedChanges,
} from '@/components/programming/shared';
import { StopTimetable } from '@/components/programming/stop-timetable';
import { formatTimezoneLabel } from '@/components/programming/timezone-utils';
import type {
    References,
    Slot,
    Template,
} from '@/components/programming/types';
import { cn, uuid } from '@/lib/utils';
import network from '@/routes/network';
import programming from '@/routes/programming';
import templateRoutes from '@/routes/programming/templates';

const steps = [
    'Liaison et sens',
    'Semaine type & Fréquence',
    'Arrêts, Horaires & Ressources',
    'Vérification',
];
const stepDescriptions = [
    'Définissez les lignes pour le sens aller (et retour si applicable).',
    'Indiquez les heures de départ, la fréquence et les jours de circulation.',
    'Ajustez les heures d’arrêt intermédiaires et pré-affectez les ressources.',
    'Vérifiez le récapitulatif avant de rendre le modèle disponible.',
];

export default function TemplateEditor({
    template,
    initial,
    references: refs,
}: {
    template: Template | null;
    initial?: { name: string; slots: Slot[] };
    references: References;
}) {
    const [step, setStep] = useState(0);
    const form = useForm({
        name: template?.name ?? initial?.name ?? '',
        revision: template?.revision ?? 0,
        ready: false,
        slots: template?.draft?.slots ??
            template?.versions[0]?.slots ??
            initial?.slots ?? [freshSlot(refs.timezone)],
    });

    useUnsavedChanges(form.isDirty && !form.processing);
    const slots = form.data.slots;

    const patch = (key: string, values: Partial<Slot>) =>
        form.setData(
            'slots',
            slots.map((s) => (s.key === key ? { ...s, ...values } : s)),
        );

    const updateDirection = (
        direction: Slot['direction'],
        values: Partial<Slot>,
    ) =>
        form.setData(
            'slots',
            slots.map((s) =>
                s.direction === direction ? { ...s, ...values } : s,
            ),
        );

    const lineChange = (slot: Slot, lineUuid: string) => {
        const line = refs.lines.find((l) => l.uuid === lineUuid);
        const duration = line?.duration ?? 180;
        const isReturn = slot.direction === 'return';
        // Pour le retour d'une ligne bidirectionnelle, on inverse les arrêts.
        const orderedStops =
            isReturn && line?.bidirectional
                ? [...(line?.stops ?? [])].reverse()
                : (line?.stops ?? []);

        updateDirection(slot.direction, {
            line_uuid: lineUuid || null,
            duration_minutes: duration,
            stops: orderedStops.map((stop, i) => ({
                stop_uuid: stop.uuid,
                timezone: slot.timezone,
                arrival_minutes:
                    i === orderedStops.length - 1 ? duration : null,
                departure_minutes: i === 0 ? 0 : null,
            })),
        });

        // Auto-proposition du retour si la ligne est bidirectionnelle,
        // qu'aucun retour n'existe encore, et que l'utilisateur vient
        // de sélectionner la ligne aller.
        if (
            slot.direction === 'outbound' &&
            line?.bidirectional &&
            line.stops.length >= 2 &&
            !slots.some((s) => s.direction === 'return')
        ) {
            const reversedStops = [...line.stops].reverse();
            const returnSlot: Slot = {
                ...freshSlot(slot.timezone || refs.timezone),
                direction: 'return',
                time: '14:00',
                days: slot.days.length > 0 ? slot.days : [1, 2, 3, 4, 5],
                line_uuid: lineUuid,
                duration_minutes: duration,
                stops: reversedStops.map((stop, i) => ({
                    stop_uuid: stop.uuid,
                    timezone: slot.timezone,
                    arrival_minutes:
                        i === reversedStops.length - 1 ? duration : null,
                    departure_minutes: i === 0 ? 0 : null,
                })),
            };
            form.setData('slots', [
                ...slots.map((s) =>
                    s.direction === 'outbound'
                        ? {
                            ...s,
                            line_uuid: lineUuid || null,
                            duration_minutes: duration,
                            stops:
                                line?.stops.map((stop, i) => ({
                                    stop_uuid: stop.uuid,
                                    timezone: s.timezone,
                                    arrival_minutes:
                                        i === line.stops.length - 1
                                            ? duration
                                            : null,
                                    departure_minutes: i === 0 ? 0 : null,
                                })) ?? [],
                        }
                        : s,
                ),
                returnSlot,
            ]);
        }
    };

    const addReturnDirection = () => {
        const outbound = slots.find((s) => s.direction === 'outbound');
        let returnLineUuid: string | null = null;

        if (outbound?.line_uuid) {
            const outboundLine = refs.lines.find(
                (l) => l.uuid === outbound.line_uuid,
            );

            // Priorité 1 : la ligne est bidirectionnelle → on réutilise
            // la même ligne avec les arrêts inversés.
            if (outboundLine?.bidirectional && outboundLine.stops.length >= 2) {
                returnLineUuid = outboundLine.uuid;
            } else if (outboundLine && outboundLine.stops.length >= 2) {
                // Priorité 2 : chercher une ligne opposée (heuristique legacy)
                const firstStop = outboundLine.stops[0].label;
                const lastStop =
                    outboundLine.stops[outboundLine.stops.length - 1].label;
                const oppositeLine = refs.lines.find((l) => {
                    if (l.stops.length < 2) {
                        return false;
                    }

                    const opFirst = l.stops[0].label;
                    const opLast = l.stops[l.stops.length - 1].label;

                    return opFirst === lastStop && opLast === firstStop;
                });

                if (oppositeLine) {
                    returnLineUuid = oppositeLine.uuid;
                }
            }
        }

        const newSlot = {
            ...freshSlot(outbound?.timezone || refs.timezone),
            direction: 'return' as const,
            time: '14:00',
            days: outbound?.days || [1, 2, 3, 4, 5],
        };

        if (returnLineUuid) {
            const line = refs.lines.find((l) => l.uuid === returnLineUuid);
            const duration = line?.duration ?? 180;
            newSlot.line_uuid = returnLineUuid;
            newSlot.duration_minutes = duration;

            // Si la ligne est bidirectionnelle, on inverse les arrêts.
            const isBidirectional = line?.bidirectional ?? false;
            const orderedStops = line?.stops ?? [];
            const stopsForReturn = isBidirectional
                ? [...orderedStops].reverse()
                : orderedStops;

            newSlot.stops = stopsForReturn.map((stop, i) => ({
                stop_uuid: stop.uuid,
                timezone: newSlot.timezone,
                arrival_minutes:
                    i === stopsForReturn.length - 1 ? duration : null,
                departure_minutes: i === 0 ? 0 : null,
            }));
        }

        form.setData('slots', [...slots, newSlot]);
    };

    const removeReturnDirection = () => {
        form.setData(
            'slots',
            slots
                .filter((s) => s.direction !== 'return')
                .map((s) => ({ ...s, return_key: null })),
        );
    };

    const save = (ready: boolean) => {
        form.transform((data) => ({ ...data, ready }));
        const options = {
            preserveScroll: true,
            onSuccess: (page: { props: Record<string, unknown> }) => {
                const saved = page.props.template as Template;
                form.setData('revision', saved.revision);
                form.setDefaults({ ...form.data, revision: saved.revision });
            },
            onError: () => setStep(3),
        };

        if (template) {
            form.put(templateRoutes.update.url(template.uuid), options);
        } else {
            form.post(templateRoutes.store.url(), options);
        }
    };

    const duplicate = (slot: Slot) =>
        form.setData('slots', [
            ...slots,
            { ...slot, key: uuid(), return_key: null, time: '08:00' },
        ]);

    const hasReturn = slots.some((s) => s.direction === 'return');
    const outboundSlot = slots.find((s) => s.direction === 'outbound');
    const returnSlot = slots.find((s) => s.direction === 'return');

    return (
        <>
            <Head title={template ? 'Modifier le modèle' : 'Nouveau modèle de départs'} />
            <div className="w-full grid gap-6 pb-24">
                <header className="grid gap-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Link
                            href={programming.index({
                                query: { tab: 'templates' },
                            })}
                            className="inline-flex items-center gap-2 rounded-[10px] border border-border bg-card px-3.5 py-2 text-xs font-bold text-foreground transition-colors hover:border-gold hover:text-foreground"
                        >
                            <ArrowLeft size={15} strokeWidth={2} />
                            Retour aux modèles
                        </Link>
                        <span className="text-xs font-semibold text-muted-foreground">
                            Exploitation / Programmation / Modèles
                        </span>
                    </div>

                    <div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.4rem)] font-extrabold tracking-[-0.04em] text-foreground">
                            {template ? template.name : 'Nouveau modèle de départs'}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Configurez une semaine type réutilisable pour générer vos plannings de départs en quelques clics.
                        </p>
                    </div>

                    <div className="grid gap-3 rounded-2xl border border-border bg-card p-5 sm:grid-cols-[auto_1fr_auto] sm:items-center">
                        <span className="grid size-11 place-items-center rounded-xl bg-gold/15 text-kora dark:text-gold">
                            <Info size={20} strokeWidth={2} />
                        </span>
                        <div>
                            <p className="text-sm font-extrabold text-foreground">
                                Comment fonctionne un modèle de départs ?
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                Un modèle regroupe vos liaisons régulières (ex&nbsp;: Cotonou ↔ Lomé) avec leurs heures habituelles et arrêts.
                                Vous pourrez ensuite l'appliquer sur n'importe quel mois ou période de votre choix dans «&nbsp;Préparer une période&nbsp;».
                            </p>
                        </div>
                        <span className="hidden rounded-lg bg-muted px-3 py-1.5 text-xs font-bold text-muted-foreground sm:block">
                            Étape {step + 1} / {steps.length}
                        </span>
                    </div>
                </header>

                <nav
                    aria-label="Étapes de création"
                    className="grid grid-cols-1 gap-2 rounded-2xl border border-border bg-card p-2 sm:grid-cols-4"
                >
                    {steps.map((label, i) => {
                        const isActive = step === i;
                        const isDone = step > i;

                        return (
                            <button
                                key={label}
                                onClick={() => setStep(i)}
                                aria-current={isActive ? 'step' : undefined}
                                title={stepDescriptions[i]}
                                className={`flex items-center gap-3 rounded-xl px-3 py-3 text-left transition-all duration-200 ${isActive
                                    ? 'bg-gold/15 text-foreground ring-1 ring-gold/40'
                                    : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                                    }`}
                            >
                                <span
                                    className={`grid size-8 shrink-0 place-content-center rounded-full border text-xs font-extrabold transition-colors ${isDone
                                        ? 'border-emerald-500 bg-emerald-500 text-white'
                                        : isActive
                                            ? 'border-gold bg-gold text-kora'
                                            : 'border-border bg-card text-muted-foreground'
                                        }`}
                                >
                                    {isDone ? <Check size={14} strokeWidth={3} /> : i + 1}
                                </span>
                                <span className="min-w-0">
                                    <strong className="block text-xs font-extrabold text-foreground">
                                        {label}
                                    </strong>
                                    <small className="mt-0.5 block truncate text-[0.6875rem] font-medium text-muted-foreground">
                                        {stepDescriptions[i]}
                                    </small>
                                </span>
                            </button>
                        );
                    })}
                </nav>

                <Errors errors={form.errors} />

                {template?.status === 'archived' && (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-xs font-semibold text-destructive">
                        Ce modèle est actuellement archivé. Vous devez le réactiver ou créer un nouveau modèle pour générer de nouveaux départs.
                    </div>
                )}

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <Surface>
                        <div className="grid gap-6">
                            <div className="flex items-start justify-between gap-4 border-b border-border pb-5">
                                <div>
                                    <span className="text-[0.6875rem] font-extrabold tracking-wider text-gold uppercase">
                                        Étape {step + 1} sur {steps.length}
                                    </span>
                                    <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                        {steps[step]}
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {stepDescriptions[step]}
                                    </p>
                                </div>
                                <span className="hidden rounded-full bg-muted px-3 py-1 text-xs font-bold text-muted-foreground sm:block">
                                    Brouillon conservé
                                </span>
                            </div>

                            {/* ÉTAPE 0 : LIAISON ET SENS */}
                            {step === 0 && (
                                <div className="grid gap-6">
                                    <Field
                                        label="Nom du modèle"
                                        icon={Layers}
                                        required
                                        hint="Donnez un nom explicite pour identifier facilement cette liaison dans vos plannings."
                                    >
                                        <Input
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData('name', e.target.value)
                                            }
                                            placeholder="Ex: Cotonou ↔ Lomé — Départs Express"
                                            className="h-11 font-semibold"
                                        />
                                    </Field>

                                    {/* SENS ALLER */}
                                    {outboundSlot && (
                                        <DirectionSection
                                            direction="outbound"
                                            title="Sens Aller (Principal)"
                                            badge={
                                                <span className="rounded-md bg-digital/10 px-2 py-0.5 text-[0.6875rem] font-bold text-digital">
                                                    Obligatoire
                                                </span>
                                            }
                                            summary={
                                                outboundSlot.line_uuid
                                                    ? `${refs.lines.find((l) => l.uuid === outboundSlot.line_uuid)?.label ?? 'Ligne à sélectionner'} · ${outboundSlot.time || '07:00'}`
                                                    : 'Ligne à sélectionner'
                                            }
                                        >
                                            <div className="grid gap-4">
                                                <SelectField
                                                    label="Ligne de transport (Aller)"
                                                    icon={Route}
                                                    required
                                                    hint={
                                                        refs.lines.find((l) => l.uuid === outboundSlot.line_uuid)?.bidirectional
                                                            ? 'Cette ligne est Aller-Retour : le retour sera proposé automatiquement avec les arrêts inversés.'
                                                            : 'Sélectionnez l\'itinéraire définissant la ville de départ et d\'arrivée.'
                                                    }
                                                    value={outboundSlot.line_uuid}
                                                    options={refs.lines.map((l) => ({
                                                        ...l,
                                                        label: l.bidirectional
                                                            ? `${l.label} · Aller-Retour`
                                                            : l.label,
                                                    }))}
                                                    onChange={(v) => lineChange(outboundSlot, v)}
                                                />

                                                <SelectField
                                                    label="Fuseau horaire de départ"
                                                    icon={Globe2}
                                                    required
                                                    hint="Fuseau utilisé pour exprimer les heures de départ."
                                                    value={outboundSlot.timezone}
                                                    options={refs.timezones.map((z) => ({
                                                        uuid: z,
                                                        label: formatTimezoneLabel(z),
                                                    }))}
                                                    onChange={(v) =>
                                                        updateDirection('outbound', {
                                                            timezone: v || refs.timezone,
                                                        })
                                                    }
                                                />

                                                {outboundSlot.line_uuid && (
                                                    <div className="rounded-xl border border-digital/20 bg-digital/[0.03] p-3.5">
                                                        <span className="block text-[0.6875rem] font-bold text-digital uppercase">
                                                            Arrêts de la ligne sélectionnée
                                                        </span>
                                                        <ol className="mt-2 grid gap-1.5 text-xs text-foreground">
                                                            {refs.lines
                                                                .find((l) => l.uuid === outboundSlot.line_uuid)
                                                                ?.stops.map((stop, i) => (
                                                                    <li
                                                                        key={stop.uuid}
                                                                        className="flex items-center gap-2"
                                                                    >
                                                                        <span className="grid size-5 place-items-center rounded-full bg-digital/10 text-[0.625rem] font-bold text-digital">
                                                                            {i + 1}
                                                                        </span>
                                                                        <span className="font-semibold">{stop.label}</span>
                                                                        {stop.country && (
                                                                            <span className="text-muted-foreground">({stop.country})</span>
                                                                        )}
                                                                    </li>
                                                                ))}
                                                        </ol>
                                                    </div>
                                                )}
                                            </div>
                                        </DirectionSection>
                                    )}

                                    {/* SENS RETOUR */}
                                    {hasReturn && returnSlot ? (
                                        <DirectionSection
                                            direction="return"
                                            title="Sens Retour"
                                            actions={
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={removeReturnDirection}
                                                    className="h-8 gap-1.5 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <Trash2 size={14} />
                                                    Retirer
                                                </Button>
                                            }
                                            summary={
                                                returnSlot.line_uuid
                                                    ? `${refs.lines.find((l) => l.uuid === returnSlot.line_uuid)?.label ?? 'Ligne à sélectionner'} · ${returnSlot.time || '07:00'}`
                                                    : 'Ligne à sélectionner'
                                            }
                                        >
                                            <div className="grid gap-4">
                                                <SelectField
                                                    label="Ligne de transport (Retour)"
                                                    icon={Route}
                                                    required
                                                    hint="Sélectionnez l'itinéraire de retour (ex: sens inverse)."
                                                    value={returnSlot.line_uuid}
                                                    options={refs.lines}
                                                    onChange={(v) => lineChange(returnSlot, v)}
                                                />

                                                <SelectField
                                                    label="Fuseau horaire de départ"
                                                    icon={Globe2}
                                                    required
                                                    hint="Fuseau horaire pour le départ retour."
                                                    value={returnSlot.timezone}
                                                    options={refs.timezones.map((z) => ({
                                                        uuid: z,
                                                        label: formatTimezoneLabel(z),
                                                    }))}
                                                    onChange={(v) =>
                                                        updateDirection('return', {
                                                            timezone: v || refs.timezone,
                                                        })
                                                    }
                                                />

                                                {returnSlot.line_uuid && (
                                                    <div className="rounded-xl border border-gold/20 bg-gold/[0.03] p-3.5">
                                                        <span className="block text-[0.6875rem] font-bold text-kora dark:text-gold uppercase">
                                                            Arrêts du retour
                                                        </span>
                                                        <ol className="mt-2 grid gap-1.5 text-xs text-foreground">
                                                            {returnSlot.stops.map((stop, i) => {
                                                                const stopRef = refs.lines
                                                                    .find((l) => l.uuid === returnSlot.line_uuid)
                                                                    ?.stops.find((s) => s.uuid === stop.stop_uuid);

                                                                return (
                                                                    <li
                                                                        key={stop.stop_uuid}
                                                                        className="flex items-center gap-2"
                                                                    >
                                                                        <span className="grid size-5 place-items-center rounded-full bg-gold/10 text-[0.625rem] font-bold text-kora dark:text-gold">
                                                                            {i + 1}
                                                                        </span>
                                                                        <span className="font-semibold">{stopRef?.label ?? `Arrêt ${i + 1}`}</span>
                                                                        {stopRef?.country && (
                                                                            <span className="text-muted-foreground">({stopRef.country})</span>
                                                                        )}
                                                                    </li>
                                                                );
                                                            })}
                                                        </ol>
                                                    </div>
                                                )}
                                            </div>
                                        </DirectionSection>
                                    ) : (
                                        <div className="flex flex-col items-start justify-between gap-3 rounded-2xl border border-dashed border-border bg-muted/20 p-5 sm:flex-row sm:items-center">
                                            <div>
                                                <h3 className="text-xs font-extrabold text-foreground">
                                                    Ajouter un sens retour coordonné ?
                                                </h3>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    Si le bus ou l'équipage effectue le trajet retour (ex: Lomé → Cotonou), vous pouvez le configurer ensemble.
                                                </p>
                                            </div>
                                            <Button
                                                variant="outline"
                                                onClick={addReturnDirection}
                                                className="shrink-0 gap-2 border-gold/40 hover:border-gold hover:bg-gold/10"
                                            >
                                                <Plus size={16} className="text-gold" />
                                                Ajouter le sens retour
                                            </Button>
                                        </div>
                                    )}

                                    <div className="flex justify-end">
                                        <Link
                                            href={network.index()}
                                            target="_blank"
                                            className="inline-flex items-center gap-1.5 text-xs font-bold text-kora hover:underline dark:text-gold"
                                        >
                                            <Route size={14} /> Gérer les lignes et arrêts dans le référentiel
                                        </Link>
                                    </div>
                                </div>
                            )}

                            {/* ÉTAPE 1 : SEMAINE TYPE & FRÉQUENCE */}
                            {step === 1 && (
                                <div className="grid gap-6">
                                    <div>
                                        <h3 className="text-sm font-extrabold text-foreground">
                                            Créneaux de départs et jours de circulation
                                        </h3>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Définissez les heures auxquelles les bus partent chaque semaine, ainsi que les jours actifs.
                                        </p>
                                    </div>

                                    {slots.map((slot, index) => (
                                        <div
                                            key={slot.key}
                                            className={cn(
                                                'grid gap-4 rounded-2xl border bg-background p-5',
                                                slot.direction === 'outbound'
                                                    ? 'border-digital/25 bg-digital/[0.015]'
                                                    : 'border-gold/25 bg-gold/[0.015]',
                                            )}
                                        >
                                            <div className={cn(
                                                'flex items-center justify-between gap-3 border-b pb-3',
                                                slot.direction === 'outbound'
                                                    ? 'border-digital/20'
                                                    : 'border-gold/20',
                                            )}>
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`grid size-6 place-items-center rounded-md text-xs font-extrabold ${slot.direction === 'outbound'
                                                            ? 'bg-digital/15 text-digital'
                                                            : 'bg-gold/20 text-kora dark:text-gold'
                                                            }`}
                                                    >
                                                        {slot.direction === 'outbound' ? (
                                                            <ArrowRight size={13} strokeWidth={2.5} />
                                                        ) : (
                                                            <ArrowLeft size={13} strokeWidth={2.5} />
                                                        )}
                                                    </span>
                                                    <strong className="text-xs font-extrabold text-foreground">
                                                        {slot.direction === 'outbound' ? 'Aller' : 'Retour'} · Départ {index + 1}
                                                    </strong>
                                                    <span className="font-mono text-xs font-bold text-muted-foreground">
                                                        ({slot.time || '07:00'})
                                                    </span>
                                                </div>

                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => duplicate(slot)}
                                                        title="Dupliquer cet horaire"
                                                        aria-label="Dupliquer cet horaire"
                                                    >
                                                        <Copy size={15} />
                                                    </Button>
                                                    {slots.filter((s) => s.direction === slot.direction).length > 1 && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                form.setData(
                                                                    'slots',
                                                                    slots
                                                                        .filter((s) => s.key !== slot.key)
                                                                        .map((s) =>
                                                                            s.return_key === slot.key
                                                                                ? { ...s, return_key: null }
                                                                                : s,
                                                                        ),
                                                                )
                                                            }
                                                            title="Supprimer cet horaire"
                                                            aria-label="Supprimer cet horaire"
                                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                        >
                                                            <Trash2 size={15} />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Field
                                                    label="Heure locale de départ"
                                                    icon={CalendarClock}
                                                    required
                                                    hint="Heure habituelle à laquelle le bus part de l’agence d’origine."
                                                >
                                                    <Input
                                                        type="time"
                                                        value={slot.time}
                                                        onChange={(e) =>
                                                            patch(slot.key, { time: e.target.value })
                                                        }
                                                        className="h-11 font-mono text-sm font-bold"
                                                    />
                                                </Field>

                                                <Field
                                                    label="Fréquence de répétition"
                                                    icon={Repeat2}
                                                    required
                                                    hint="Choisissez la périodicité de ce créneau."
                                                >
                                                    <select
                                                        value={slot.interval_weeks}
                                                        onChange={(e) =>
                                                            patch(slot.key, {
                                                                interval_weeks: Number(e.target.value),
                                                            })
                                                        }
                                                        className="h-11 w-full rounded-[10px] border border-border bg-background px-3 text-xs font-semibold text-foreground outline-none focus:border-gold focus:ring-2 focus:ring-gold/20"
                                                    >
                                                        <option value={1}>Toutes les semaines (Standard)</option>
                                                        <option value={2}>Toutes les 2 semaines (Quinzaine)</option>
                                                        <option value={3}>Toutes les 3 semaines</option>
                                                        <option value={4}>Toutes les 4 semaines (Mensuel)</option>
                                                    </select>
                                                </Field>
                                            </div>

                                            <div className="grid gap-2">
                                                <label className="text-xs font-extrabold text-foreground">
                                                    Jours de circulation
                                                </label>
                                                <div
                                                    className="flex flex-wrap gap-2"
                                                    role="group"
                                                    aria-label="Jours de circulation"
                                                >
                                                    {days.map((d, i) => {
                                                        const isSelected = slot.days.includes(i + 1);

                                                        return (
                                                            <button
                                                                key={d}
                                                                type="button"
                                                                aria-pressed={isSelected}
                                                                onClick={() =>
                                                                    patch(slot.key, {
                                                                        days: isSelected
                                                                            ? slot.days.filter((v) => v !== i + 1)
                                                                            : [...slot.days, i + 1].sort(),
                                                                    })
                                                                }
                                                                className={`h-10 min-w-10 rounded-[10px] border px-3 text-xs font-black transition-all ${isSelected
                                                                    ? 'border-gold bg-gold text-kora shadow-xs'
                                                                    : 'border-border bg-card text-muted-foreground hover:border-gold/60 hover:text-foreground'
                                                                    }`}
                                                            >
                                                                {d}
                                                            </button>
                                                        );
                                                    })}
                                                </div>

                                                <div className="flex flex-wrap gap-3 pt-1 text-xs">
                                                    {[
                                                        ['Tous les jours', [1, 2, 3, 4, 5, 6, 7]],
                                                        ['Du lundi au vendredi', [1, 2, 3, 4, 5]],
                                                        ['Week-end uniquement', [6, 7]],
                                                        ['Effacer', []],
                                                    ].map(([label, values]) => (
                                                        <button
                                                            key={String(label)}
                                                            type="button"
                                                            className="font-semibold text-kora underline-offset-4 hover:underline dark:text-gold"
                                                            onClick={() =>
                                                                patch(slot.key, {
                                                                    days: values as number[],
                                                                })
                                                            }
                                                        >
                                                            {label}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    ))}

                                    <div className="flex flex-wrap gap-3">
                                        {(['outbound', 'return'] as const).map((dir) => {
                                            const sample = slots.find((s) => s.direction === dir);

                                            return (
                                                sample && (
                                                    <Button
                                                        key={dir}
                                                        variant="outline"
                                                        onClick={() => duplicate(sample)}
                                                        className="gap-2"
                                                    >
                                                        <Plus size={15} />
                                                        Ajouter un autre horaire {dir === 'outbound' ? 'aller' : 'retour'}
                                                    </Button>
                                                )
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* ÉTAPE 2 : HORAIRES D'ARRÊTS ET RESSOURCES */}
                            {step === 2 && (
                                <div className="grid gap-6">
                                    <div>
                                        <h3 className="text-sm font-extrabold text-foreground">
                                            Horaires d'arrêt et ressources habituelles
                                        </h3>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Ajustez les heures d'arrivée à chaque ville et pré-assignez vos bus et équipages (optionnel).
                                        </p>
                                    </div>

                                    {slots.map((slot, index) => {
                                        const line = refs.lines.find((l) => l.uuid === slot.line_uuid);

                                        return (
                                            <DirectionSection
                                                key={slot.key}
                                                direction={slot.direction}
                                                title={
                                                    <>
                                                        <span
                                                            className={`grid size-5 place-items-center rounded-md text-[0.625rem] font-black ${slot.direction === 'outbound'
                                                                ? 'bg-digital/15 text-digital'
                                                                : 'bg-gold/20 text-kora dark:text-gold'
                                                                }`}
                                                        >
                                                            {slot.direction === 'outbound' ? 'A' : 'R'}
                                                        </span>
                                                        {line?.label ?? 'Ligne non sélectionnée'} · Départ à{' '}
                                                        <span className="font-mono">{slot.time}</span>
                                                    </>
                                                }
                                                summary={`${slot.stops.length} arrêt(s) · Créneau ${index + 1}`}
                                            >
                                                <div className="grid gap-6">
                                                    <StopTimetable
                                                        slot={slot}
                                                        references={refs}
                                                        onChange={(changes) => patch(slot.key, changes)}
                                                    />

                                                    <div className="rounded-xl border border-border bg-card p-5">
                                                        <div className="mb-4">
                                                            <h4 className="text-xs font-extrabold text-foreground">
                                                                Ressources habituelles (Optionnel)
                                                            </h4>
                                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                                Vous pouvez affecter un bus et un équipage par défaut. Vous pourrez toujours les modifier plus tard sur le planning.
                                                            </p>
                                                        </div>

                                                        <ResourceFields
                                                            slot={slot}
                                                            refs={refs}
                                                            onChange={(values) => patch(slot.key, values)}
                                                        />
                                                    </div>

                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <Field
                                                            label="Marge de préparation avant départ (min)"
                                                            icon={Clock3}
                                                            hint="Temps nécessaire avant le départ pour l’embarquement et le contrôle."
                                                        >
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                max={1440}
                                                                value={slot.preparation_minutes}
                                                                onChange={(e) =>
                                                                    patch(slot.key, {
                                                                        preparation_minutes: Number(e.target.value),
                                                                    })
                                                                }
                                                            />
                                                        </Field>

                                                        {slot.direction === 'outbound' && hasReturn && (
                                                            <SelectField
                                                                label="Rotation : lier avec un retour"
                                                                icon={Route}
                                                                hint="Associe le même bus pour le trajet retour."
                                                                value={slot.return_key}
                                                                empty="Trajets indépendants"
                                                                options={slots
                                                                    .filter((s) => s.direction === 'return')
                                                                    .map((s) => ({
                                                                        uuid: s.key,
                                                                        label: `${s.time} · Retour`,
                                                                    }))}
                                                                onChange={(v) =>
                                                                    patch(slot.key, {
                                                                        return_key: v || null,
                                                                    })
                                                                }
                                                            />
                                                        )}
                                                    </div>
                                                </div>
                                            </DirectionSection>
                                        );
                                    })}
                                </div>
                            )}

                            {/* ÉTAPE 3 : VÉRIFICATION ET RÉCAPITULATIF */}
                            {step === 3 && (
                                <div className="grid gap-6">
                                    <div>
                                        <h3 className="text-sm font-extrabold text-foreground">
                                            Récapitulatif de votre semaine type
                                        </h3>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Relisez attentivement la configuration. Une fois validé, ce modèle sera prêt à générer vos programmes.
                                        </p>
                                    </div>

                                    <div className="rounded-2xl border border-border bg-background p-5">
                                        <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                            Nom du modèle
                                        </span>
                                        <h4 className="mt-1 text-base font-extrabold text-foreground">
                                            {form.data.name || 'Sans titre'}
                                        </h4>
                                    </div>

                                    <div className="grid gap-3">
                                        {slots.map((slot) => {
                                            const line = refs.lines.find((l) => l.uuid === slot.line_uuid);

                                            return (
                                                <div
                                                    key={slot.key}
                                                    className={cn(
                                                        'rounded-xl border bg-card p-4',
                                                        slot.direction === 'outbound'
                                                            ? 'border-l-4 border-l-digital/50 border-border'
                                                            : 'border-l-4 border-l-gold/50 border-border',
                                                    )}
                                                >
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <span className="flex items-center gap-2 font-extrabold text-xs">
                                                            <span
                                                                className={`grid size-5 place-items-center rounded-md text-[0.625rem] font-black ${slot.direction === 'outbound'
                                                                    ? 'bg-digital/15 text-digital'
                                                                    : 'bg-gold/20 text-kora dark:text-gold'
                                                                    }`}
                                                            >
                                                                {slot.direction === 'outbound' ? 'A' : 'R'}
                                                            </span>
                                                            {line?.label ?? 'Ligne non sélectionnée'}
                                                        </span>
                                                        <strong className="font-mono text-sm font-extrabold text-foreground">
                                                            {slot.time}
                                                        </strong>
                                                    </div>

                                                    <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                                        <span>
                                                            Jours :{' '}
                                                            <strong className="text-foreground">
                                                                {slot.days.map((d) => days[d - 1]).join(', ')}
                                                            </strong>
                                                        </span>
                                                        <span>·</span>
                                                        <span>
                                                            Arrêts : <strong className="text-foreground">{slot.stops.length}</strong>
                                                        </span>
                                                        <span>·</span>
                                                        <span>
                                                            Durée :{' '}
                                                            <strong className="text-foreground">
                                                                {Math.floor(slot.duration_minutes / 60)}h{' '}
                                                                {slot.duration_minutes % 60}m
                                                            </strong>
                                                        </span>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    {template && (
                                        <div className="rounded-xl border border-gold/30 bg-gold/10 p-4 text-xs leading-relaxed text-foreground">
                                            <strong>Note de révision :</strong> Les programmes existants continueront d'utiliser leur version actuelle jusqu'à ce que vous décidiez d'appliquer cette nouvelle version.
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </Surface>

                    {/* PANNEAU LATÉRAL RECAPITULATIF */}
                    <aside className="hidden xl:block">
                        <Surface className="sticky top-6">
                            <span className="text-[0.6875rem] font-extrabold tracking-wider text-gold uppercase">
                                Aperçu de la semaine
                            </span>
                            <h3 className="mt-2 text-base font-extrabold text-foreground">
                                {slots.reduce((n, s) => n + s.days.length, 0)} départ(s) / semaine
                            </h3>
                            <p className="mt-1 mb-4 text-xs text-muted-foreground">
                                Nombre de départs automatiques prévus chaque semaine active.
                            </p>

                            <div className="grid gap-2.5">
                                {slots.map((slot) => (
                                    <SlotSummary key={slot.key} slot={slot} refs={refs} />
                                ))}
                            </div>
                        </Surface>
                    </aside>
                </div >

                <footer className="sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card/95 p-4 shadow-[0_8px_30px_rgba(0,29,78,0.12)] backdrop-blur">
                    <span className="text-xs font-semibold text-muted-foreground">
                        {form.isDirty
                            ? 'Modifications en cours'
                            : 'Toutes les modifications sont prêtes'}
                    </span>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="ghost"
                            disabled={step === 0 || form.processing}
                            onClick={() => setStep(step - 1)}
                            className="gap-1.5"
                        >
                            <ArrowLeft size={15} />
                            Retour
                        </Button>
                        <Button
                            variant="outline"
                            disabled={form.processing || template?.status === 'archived'}
                            onClick={() => save(false)}
                        >
                            Enregistrer le brouillon
                        </Button>
                        <PrimaryButton
                            disabled={form.processing || template?.status === 'archived'}
                            onClick={() => (step < 3 ? setStep(step + 1) : save(true))}
                            className="gap-1.5"
                        >
                            {form.processing ? (
                                'Enregistrement…'
                            ) : step < 3 ? (
                                <>
                                    Continuer <ArrowRight size={15} />
                                </>
                            ) : template ? (
                                'Enregistrer la version'
                            ) : (
                                'Valider et rendre disponible'
                            )}
                        </PrimaryButton>
                    </div>
                </footer>
            </div >
        </>
    );
}
