import { ArrowRight, CalendarDays, Clock3, Globe2, MapPin } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ProgrammingInput as Input, Field, SelectField, today } from './shared';
import type { References, Slot, StopTime } from './types';
import { formatTimezoneLabel } from './timezone-utils';

function safeLocalParts(instant: number, timezone: string) {
    if (isNaN(instant) || instant <= 0) {
        return { date: '2026-01-01', time: '00:00' };
    }
    try {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone || 'UTC',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date(instant));
        const value = (type: string) =>
            parts.find((p) => p.type === type)?.value ?? '';

        const y = value('year') || '2026';
        const m = value('month') || '01';
        const d = value('day') || '01';
        const hr = value('hour') || '00';
        const min = value('minute') || '00';

        return {
            date: `${y}-${m}-${d}`,
            time: `${hr.padStart(2, '0')}:${min.padStart(2, '0')}`,
        };
    } catch {
        return { date: '2026-01-01', time: '00:00' };
    }
}

function safeInstantFor(date: string, time: string, timezone: string): number {
    const validDate = date && /^\d{4}-\d{2}-\d{2}$/.test(date) ? date : '2026-01-01';
    const validTime = time && /^\d{1,2}:\d{2}$/.test(time) ? (time.length === 4 ? `0${time}` : time) : '00:00';

    const target = Date.parse(`${validDate}T${validTime}:00Z`);
    if (isNaN(target)) {
        return Date.now();
    }

    let instant = target;
    for (let i = 0; i < 3; i++) {
        const local = safeLocalParts(instant, timezone);
        const localParsed = Date.parse(`${local.date}T${local.time}:00Z`);
        if (!isNaN(localParsed)) {
            instant += target - localParsed;
        }
    }

    return isNaN(instant) ? target : instant;
}

function ClockField({
    label,
    value,
    base,
    date,
    timezone,
    disabled,
    hint,
    required = false,
    onChange,
}: {
    label: string;
    value: number | null;
    base: number;
    date: string;
    timezone: string;
    disabled?: boolean;
    hint?: string;
    required?: boolean;
    onChange: (value: number | null) => void;
}) {
    const validValue = value !== null && !isNaN(value) ? value : null;
    const local = useMemo(() => {
        if (validValue === null) {
            return { date, time: '' };
        }
        return safeLocalParts(base + validValue * 60000, timezone);
    }, [base, validValue, timezone, date]);

    const day = useMemo(() => {
        if (validValue === null) {
            return 0;
        }
        const baseDay = Date.parse(`${date}T00:00Z`);
        const targetDay = Date.parse(`${local.date}T00:00Z`);
        if (isNaN(baseDay) || isNaN(targetDay)) {
            return 0;
        }
        return Math.round((targetDay - baseDay) / 86400000);
    }, [date, local.date, validValue]);

    function change(timeStr: string, dayOffset: number) {
        if (!timeStr || !/^\d{1,2}:\d{2}$/.test(timeStr)) {
            onChange(null);
            return;
        }

        const baseTimestamp = Date.parse(`${date}T00:00Z`);
        if (isNaN(baseTimestamp)) {
            return;
        }

        const targetDate = new Date(baseTimestamp + dayOffset * 86400000)
            .toISOString()
            .slice(0, 10);

        const targetInstant = safeInstantFor(targetDate, timeStr, timezone);
        const diffMinutes = Math.round((targetInstant - base) / 60000);

        if (!isNaN(diffMinutes)) {
            onChange(diffMinutes);
        }
    }

    return (
        <Field
            label={label}
            icon={Clock3}
            required={required}
            hint={
                hint ||
                (validValue !== null && validValue < 0
                    ? 'Cet horaire précède le départ initial.'
                    : undefined)
            }
        >
            <div className="flex items-center gap-2">
                <Input
                    aria-label={`${label} — heure locale`}
                    type="time"
                    disabled={disabled}
                    value={validValue === null ? '' : local.time}
                    onChange={(e) => change(e.target.value, day)}
                    className="h-10 w-full min-w-0 font-mono text-xs"
                />
                <select
                    aria-label={`${label} — jour de passage`}
                    disabled={disabled || validValue === null}
                    value={day}
                    onChange={(e) => {
                        const newDay = Number(e.target.value);
                        if (local.time) {
                            change(local.time, newDay);
                        }
                    }}
                    className="h-10 shrink-0 rounded-[10px] border border-border bg-background px-2.5 text-xs font-semibold text-foreground outline-none transition-colors focus:border-gold focus:ring-2 focus:ring-gold/20 disabled:opacity-40"
                >
                    <option value={0}>Même jour (J)</option>
                    <option value={1}>Lendemain (J+1)</option>
                    <option value={2}>Surlendemain (J+2)</option>
                    <option value={-1}>Veille (J-1)</option>
                </select>
            </div>
        </Field>
    );
}

export function StopTimetable({
    slot,
    references: refs,
    onChange,
}: {
    slot: Slot;
    references: References;
    onChange: (changes: Partial<Slot>) => void;
}) {
    const [date, setDate] = useState(today());
    const slotTime = slot.time && /^\d{1,2}:\d{2}$/.test(slot.time) ? slot.time : '07:00';
    const base = safeInstantFor(date, slotTime, slot.timezone || refs.timezone);
    const line = refs.lines.find((l) => l.uuid === slot.line_uuid);

    function patch(index: number, changes: Partial<StopTime>) {
        const stops = slot.stops.map((stop, i) =>
            i === index ? { ...stop, ...changes } : stop,
        );
        const lastArrival = stops.at(-1)?.arrival_minutes;
        onChange({
            stops,
            duration_minutes:
                lastArrival !== null && lastArrival !== undefined && !isNaN(lastArrival)
                    ? lastArrival
                    : slot.duration_minutes,
        });
    }

    if (!slot.stops || slot.stops.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-border bg-muted/30 p-6 text-center text-xs text-muted-foreground">
                <MapPin className="mx-auto mb-2 size-6 text-muted-foreground/60" />
                Veuillez sélectionner une ligne à l’étape précédente pour configurer les arrêts et horaires.
            </div>
        );
    }

    const totalDurationMinutes = slot.stops.at(-1)?.arrival_minutes ?? slot.duration_minutes ?? 0;
    const hours = Math.floor(Math.max(0, totalDurationMinutes) / 60);
    const mins = Math.max(0, totalDurationMinutes) % 60;

    return (
        <div className="grid gap-5">
            <div className="rounded-xl border border-border bg-muted/40 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h4 className="text-xs font-extrabold text-foreground">
                            Itinéraire et horaires d'arrêt
                        </h4>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Indiquez les heures locales de passage pour chaque agence/arrêt. Les durées de trajet sont calculées automatiquement.
                        </p>
                    </div>
                    <div className="inline-flex items-center gap-2 rounded-lg bg-card px-3 py-1.5 text-xs font-bold shadow-xs border border-border">
                        <span className="text-muted-foreground">Durée totale estimée :</span>
                        <span className="font-extrabold text-kora dark:text-gold">
                            {hours}h {mins > 0 ? `${String(mins).padStart(2, '0')}min` : ''}
                        </span>
                    </div>
                </div>
            </div>

            <div className="relative space-y-4 before:absolute before:top-4 before:bottom-4 before:left-[1.35rem] before:w-0.5 before:bg-border">
                {slot.stops.map((stop, i) => {
                    const isFirst = i === 0;
                    const isLast = i === slot.stops.length - 1;
                    const stopRef = line?.stops.find((s) => s.uuid === stop.stop_uuid);
                    const stopName = stopRef?.label || `Arrêt ${i + 1}`;

                    const arrivalMin = stop.arrival_minutes;
                    const arrivalHours =
                        arrivalMin !== null && !isNaN(arrivalMin)
                            ? Math.floor(arrivalMin / 60)
                            : null;
                    const arrivalMins =
                        arrivalMin !== null && !isNaN(arrivalMin)
                            ? Math.abs(arrivalMin % 60)
                            : null;

                    return (
                        <div
                            key={stop.stop_uuid}
                            className="relative flex items-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-gold/50"
                        >
                            <span
                                className={`relative z-10 mt-1 flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-black shadow-xs ${isFirst
                                    ? 'bg-emerald-500 text-white'
                                    : isLast
                                        ? 'bg-kora text-white dark:bg-gold dark:text-kora'
                                        : 'border border-border bg-muted text-foreground'
                                    }`}
                            >
                                {i + 1}
                            </span>

                            <div className="grid min-w-0 flex-1 gap-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h5 className="text-xs font-extrabold text-foreground">
                                            {stopName}
                                        </h5>
                                        <span className="text-[0.6875rem] font-semibold text-muted-foreground">
                                            {isFirst
                                                ? 'Départ initial'
                                                : isLast
                                                    ? 'Terminus / Destination finale'
                                                    : 'Arrêt intermédiaire'}
                                            {stopRef?.country ? ` · ${stopRef.country}` : ''}
                                        </span>
                                    </div>

                                    {arrivalMin !== null && !isFirst && arrivalHours !== null && (
                                        <span className="rounded-md bg-muted px-2 py-0.5 text-[0.6875rem] font-bold text-muted-foreground">
                                            +{arrivalHours}h {arrivalMins ? `${arrivalMins}m` : ''} après le départ
                                        </span>
                                    )}
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    {!isFirst && (
                                        <ClockField
                                            label="Heure d’arrivée"
                                            value={stop.arrival_minutes}
                                            base={base}
                                            date={date}
                                            timezone={stop.timezone || slot.timezone}
                                            disabled={false}
                                            required={isLast}
                                            hint="Heure locale à laquelle le bus arrive à cet arrêt."
                                            onChange={(v) => patch(i, { arrival_minutes: v })}
                                        />
                                    )}

                                    {!isLast && (
                                        <ClockField
                                            label={isFirst ? 'Heure de départ (initial)' : 'Heure de redémarrage'}
                                            value={isFirst ? 0 : stop.departure_minutes}
                                            base={base}
                                            date={date}
                                            timezone={stop.timezone || slot.timezone}
                                            disabled={isFirst}
                                            required={isFirst}
                                            hint={
                                                isFirst
                                                    ? 'Départ initial calé sur l’horaire du modèle.'
                                                    : 'Heure locale à laquelle le bus repart de l’arrêt.'
                                            }
                                            onChange={(v) => patch(i, { departure_minutes: v })}
                                        />
                                    )}
                                </div>

                                <div className="pt-1">
                                    <SelectField
                                        label="Fuseau horaire de l’arrêt"
                                        icon={Globe2}
                                        required
                                        hint="Utilisé pour afficher l’heure exacte aux passagers de cette ville."
                                        value={stop.timezone || slot.timezone}
                                        options={refs.timezones.map((z) => ({
                                            uuid: z,
                                            label: formatTimezoneLabel(z),
                                        }))}
                                        onChange={(v) =>
                                            patch(i, { timezone: v || slot.timezone })
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
