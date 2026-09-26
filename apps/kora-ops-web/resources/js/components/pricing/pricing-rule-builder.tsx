import { Form } from '@inertiajs/react';
import {
    ArrowRight,
    Clock3,
    Gauge,
    Globe2,
    Info,
    Milestone,
    Route,
    Target,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import pricingRules from '@/routes/pricing-rules';
import type {
    PricingRuleDirection,
    PricingRuleRecord,
    PricingRuleScope,
} from './pricing-rule-types';

export type LineStopOption = { uuid: string; name: string; stopOrder: number };

type LineOption = {
    uuid: string;
    code: string;
    name: string;
    stops: LineStopOption[];
};

type Draft = {
    scope: PricingRuleScope;
    lineId: string;
    originStopId: string;
    destinationStopId: string;
    direction: PricingRuleDirection;
    type: 'increase' | 'decrease';
    percentage: string;
    priority: string;
    fillRateMin: string;
    fillRateMax: string;
    hoursBeforeDepartureMin: string;
    hoursBeforeDepartureMax: string;
    startsAt: string;
    endsAt: string;
};

type Props = {
    rule: PricingRuleRecord | null;
    lines: LineOption[];
    canCreate: boolean;
    canUpdate: boolean;
    onSaved: () => void;
};

function toDateTimeLocal(iso: string | null): string {
    return iso ? iso.slice(0, 16) : '';
}

function createDraft(rule: PricingRuleRecord | null): Draft {
    return {
        scope: rule?.scope ?? (rule?.lineUuid ? 'line' : 'network'),
        lineId: rule?.lineUuid ?? '',
        originStopId: rule?.originStopUuid ?? '',
        destinationStopId: rule?.destinationStopUuid ?? '',
        direction: rule?.direction ?? 'both',
        type: (rule?.type as Draft['type']) ?? 'increase',
        percentage: rule ? String(rule.percentage) : '',
        priority: rule ? String(rule.priority) : '10',
        fillRateMin:
            rule?.conditions.fillRateMin != null
                ? String(rule.conditions.fillRateMin)
                : '',
        fillRateMax:
            rule?.conditions.fillRateMax != null
                ? String(rule.conditions.fillRateMax)
                : '',
        hoursBeforeDepartureMin:
            rule?.conditions.hoursBeforeDepartureMin != null
                ? String(rule.conditions.hoursBeforeDepartureMin)
                : '',
        hoursBeforeDepartureMax:
            rule?.conditions.hoursBeforeDepartureMax != null
                ? String(rule.conditions.hoursBeforeDepartureMax)
                : '',
        startsAt: toDateTimeLocal(rule?.startsAt ?? null),
        endsAt: toDateTimeLocal(rule?.endsAt ?? null),
    };
}

function FieldLabel({
    children,
    required,
}: {
    children: React.ReactNode;
    required?: boolean;
}) {
    return (
        <Label className="text-[0.7rem] font-bold tracking-[0.04em] text-foreground">
            {children}
            {required && (
                <span className="ml-0.5 text-red-600" aria-hidden="true">
                    *
                </span>
            )}
        </Label>
    );
}

function FieldHint({ children }: { children: React.ReactNode }) {
    return (
        <p className="text-[0.68rem] leading-relaxed text-muted-foreground">
            {children}
        </p>
    );
}

function formatCondition(draft: Draft): string {
    const conditions: string[] = [];

    if (draft.fillRateMin && draft.fillRateMax) {
        conditions.push(
            `remplissage entre ${draft.fillRateMin} et ${draft.fillRateMax} %`,
        );
    } else if (draft.fillRateMin) {
        conditions.push(`remplissage supérieur à ${draft.fillRateMin} %`);
    } else if (draft.fillRateMax) {
        conditions.push(`remplissage inférieur à ${draft.fillRateMax} %`);
    }

    if (draft.hoursBeforeDepartureMin && draft.hoursBeforeDepartureMax) {
        conditions.push(
            `départ dans ${draft.hoursBeforeDepartureMin} à ${draft.hoursBeforeDepartureMax} h`,
        );
    } else if (draft.hoursBeforeDepartureMax) {
        conditions.push(
            `départ dans moins de ${draft.hoursBeforeDepartureMax} h`,
        );
    } else if (draft.hoursBeforeDepartureMin) {
        conditions.push(
            `départ dans plus de ${draft.hoursBeforeDepartureMin} h`,
        );
    }

    return conditions.length > 0
        ? conditions.join(' et ')
        : 'tous les contextes';
}

export function PricingRuleBuilder({
    rule,
    lines,
    canCreate,
    canUpdate,
    onSaved,
}: Props) {
    const [draft, setDraft] = useState<Draft>(() => createDraft(rule));

    const editing = rule !== null;
    const canSubmit = editing ? canUpdate : canCreate;
    const form = editing
        ? pricingRules.update.form({ pricingRule: rule.uuid })
        : pricingRules.store.form();
    const selectedLine = lines.find((line) => line.uuid === draft.lineId);
    const lineStops = selectedLine?.stops ?? [];
    const selectedOriginStop = lineStops.find(
        (stop) => stop.uuid === draft.originStopId,
    );
    const selectedDestinationStop = lineStops.find(
        (stop) => stop.uuid === draft.destinationStopId,
    );
    const percentage = Number(draft.percentage) || 0;
    const referenceAmount = 10000;
    const coefficient =
        draft.type === 'increase'
            ? 1 + percentage / 100
            : Math.max(0, 1 - percentage / 100);
    const simulatedAmount =
        Math.round((referenceAmount * coefficient) / 100) * 100;
    const adjustment = simulatedAmount - referenceAmount;

    const statement = useMemo(() => {
        const scope =
            draft.scope === 'network'
                ? 'tout le réseau'
                : draft.scope === 'segment'
                  ? selectedOriginStop && selectedDestinationStop
                      ? `le segment ${selectedOriginStop.name} → ${selectedDestinationStop.name} (${selectedLine?.code ?? '…'})`
                      : 'un segment à définir'
                  : selectedLine
                    ? `la ligne ${selectedLine.code} · ${selectedLine.name}`
                    : 'une ligne à sélectionner';
        const direction =
            draft.scope !== 'network' && draft.direction !== 'both'
                ? draft.direction === 'outbound'
                    ? ', sens aller'
                    : ', sens retour'
                : '';
        const effect = draft.type === 'increase' ? 'majoration' : 'réduction';
        const value = draft.percentage ? `${draft.percentage} %` : '… %';

        return `Sur ${scope}${direction}, appliquer une ${effect} de ${value} lorsque ${formatCondition(draft)}.`;
    }, [draft, selectedLine, selectedOriginStop, selectedDestinationStop]);

    const update = <K extends keyof Draft>(key: K, value: Draft[K]) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const updateScope = (scope: PricingRuleScope) => {
        setDraft((current) => ({
            ...current,
            scope,
            lineId: scope === 'network' ? '' : current.lineId,
            originStopId: scope === 'segment' ? current.originStopId : '',
            destinationStopId:
                scope === 'segment' ? current.destinationStopId : '',
        }));
    };

    const updateLine = (lineId: string) => {
        setDraft((current) => ({
            ...current,
            lineId,
            originStopId: '',
            destinationStopId: '',
        }));
    };

    const canSubmitDraft =
        draft.scope === 'network' ||
        (draft.scope === 'line' && draft.lineId !== '') ||
        (draft.scope === 'segment' &&
            draft.lineId !== '' &&
            draft.originStopId !== '' &&
            draft.destinationStopId !== '');

    return (
        <section className="rounded-[18px] bg-card p-6 shadow-sm ring-1 ring-border sm:p-7">
            <Form
                key={rule?.uuid ?? 'new'}
                {...form}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={onSaved}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="section-kicker text-gold">
                                    {editing
                                        ? 'Version en préparation'
                                        : 'Nouveau scénario'}
                                </p>
                                <h2 className="mt-1 text-[1.35rem] font-extrabold tracking-[-0.03em] text-foreground">
                                    {editing
                                        ? 'Affiner la règle'
                                        : 'Construire une règle'}
                                </h2>
                                <p className="mt-1 max-w-xl text-[0.78rem] leading-relaxed text-muted-foreground">
                                    Définissez le contexte commercial, puis
                                    vérifiez son impact avant de l'activer.
                                </p>
                            </div>
                            <div className="hidden size-11 shrink-0 place-items-center rounded-[10px] bg-gold/15 text-kora sm:grid">
                                {draft.type === 'increase' ? (
                                    <TrendingUp
                                        className="size-5"
                                        strokeWidth={1.8}
                                    />
                                ) : (
                                    <TrendingDown
                                        className="size-5"
                                        strokeWidth={1.8}
                                    />
                                )}
                            </div>
                        </div>

                        <div className="rounded-[10px] bg-kora p-4 text-white shadow-sm">
                            <div className="mb-2 flex items-center gap-2 text-[0.62rem] font-black tracking-[0.16em] text-white/55 uppercase">
                                <Target
                                    className="size-3.5"
                                    strokeWidth={1.8}
                                />
                                Lecture de la règle
                            </div>
                            <p className="text-[0.85rem] leading-relaxed font-semibold text-white">
                                {statement}
                            </p>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <FieldLabel required>Portée</FieldLabel>
                                <div className="grid grid-cols-3 gap-1.5 rounded-[10px] bg-muted/60 p-1.5 ring-1 ring-border">
                                    {[
                                        {
                                            value: 'network',
                                            label: 'Réseau',
                                            icon: Globe2,
                                        },
                                        {
                                            value: 'line',
                                            label: 'Ligne',
                                            icon: Route,
                                        },
                                        {
                                            value: 'segment',
                                            label: 'Segment',
                                            icon: Milestone,
                                        },
                                    ].map(({ value, label, icon: Icon }) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() =>
                                                updateScope(
                                                    value as PricingRuleScope,
                                                )
                                            }
                                            className={`flex h-9 items-center justify-center gap-1.5 rounded-[8px] text-xs font-bold transition-[transform,background-color,color,box-shadow] duration-300 active:scale-[0.98] ${draft.scope === value ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:bg-card/70 hover:text-foreground'}`}
                                        >
                                            <Icon
                                                className="size-3.5"
                                                strokeWidth={1.8}
                                            />
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                <input
                                    type="hidden"
                                    name="scope_type"
                                    value={draft.scope}
                                />
                                <FieldHint>
                                    Réseau = toutes les lignes. Segment = une
                                    paire d'arrêts O/D.
                                </FieldHint>
                                <InputError message={errors.scope_type} />
                            </div>
                            <div className="grid gap-2">
                                <FieldLabel required>Priorité</FieldLabel>
                                <Input
                                    name="priority"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    value={draft.priority}
                                    onChange={(event) =>
                                        update('priority', event.target.value)
                                    }
                                    required
                                    className="h-11 rounded-[10px] border-0 bg-muted/60 ring-1 ring-border"
                                />
                                <FieldHint>
                                    1 est la priorité la plus haute. Une seule
                                    règle gagne.
                                </FieldHint>
                                <InputError message={errors.priority} />
                            </div>
                        </div>

                        {draft.scope !== 'network' && (
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <FieldLabel required>
                                        Ligne ciblée
                                    </FieldLabel>
                                    <select
                                        name="line_id"
                                        value={draft.lineId}
                                        onChange={(event) =>
                                            updateLine(event.target.value)
                                        }
                                        required
                                        className="h-11 w-full rounded-[10px] bg-muted/60 px-3 text-sm text-foreground ring-1 ring-border transition-[box-shadow,background-color] duration-300 outline-none focus:bg-card focus:ring-2 focus:ring-gold/40"
                                    >
                                        <option value="">
                                            Sélectionner une ligne
                                        </option>
                                        {lines.map((line) => (
                                            <option
                                                key={line.uuid}
                                                value={line.uuid}
                                            >
                                                {line.code} · {line.name}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldHint>
                                        La ligne rend la portée commerciale
                                        explicite.
                                    </FieldHint>
                                    <InputError message={errors.line_id} />
                                </div>
                                <div className="grid gap-2">
                                    <FieldLabel>Direction</FieldLabel>
                                    <select
                                        name="direction"
                                        value={draft.direction}
                                        onChange={(event) =>
                                            update(
                                                'direction',
                                                event.target
                                                    .value as PricingRuleDirection,
                                            )
                                        }
                                        className="h-11 w-full rounded-[10px] bg-muted/60 px-3 text-sm text-foreground ring-1 ring-border transition-[box-shadow,background-color] duration-300 outline-none focus:bg-card focus:ring-2 focus:ring-gold/40"
                                    >
                                        <option value="both">
                                            Les deux sens
                                        </option>
                                        <option value="outbound">Aller</option>
                                        <option value="return">Retour</option>
                                    </select>
                                    <FieldHint>
                                        Sens de circulation concerné sur la
                                        ligne.
                                    </FieldHint>
                                    <InputError message={errors.direction} />
                                </div>
                            </div>
                        )}

                        {draft.scope === 'segment' && (
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <FieldLabel required>
                                        Arrêt d'origine
                                    </FieldLabel>
                                    <select
                                        name="origin_stop_id"
                                        value={draft.originStopId}
                                        onChange={(event) =>
                                            update(
                                                'originStopId',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        disabled={!selectedLine}
                                        className="h-11 w-full rounded-[10px] bg-muted/60 px-3 text-sm text-foreground ring-1 ring-border transition-[box-shadow,background-color] duration-300 outline-none focus:bg-card focus:ring-2 focus:ring-gold/40 disabled:opacity-50"
                                    >
                                        <option value="">
                                            {selectedLine
                                                ? 'Sélectionner un arrêt'
                                                : 'Choisir une ligne d\u2019abord'}
                                        </option>
                                        {lineStops.map((stop) => (
                                            <option
                                                key={stop.uuid}
                                                value={stop.uuid}
                                            >
                                                {stop.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.origin_stop_id}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <FieldLabel required>
                                        Arrêt de destination
                                    </FieldLabel>
                                    <select
                                        name="destination_stop_id"
                                        value={draft.destinationStopId}
                                        onChange={(event) =>
                                            update(
                                                'destinationStopId',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        disabled={!selectedLine}
                                        className="h-11 w-full rounded-[10px] bg-muted/60 px-3 text-sm text-foreground ring-1 ring-border transition-[box-shadow,background-color] duration-300 outline-none focus:bg-card focus:ring-2 focus:ring-gold/40 disabled:opacity-50"
                                    >
                                        <option value="">
                                            {selectedLine
                                                ? 'Sélectionner un arrêt'
                                                : 'Choisir une ligne d\u2019abord'}
                                        </option>
                                        {lineStops.map((stop) => (
                                            <option
                                                key={stop.uuid}
                                                value={stop.uuid}
                                            >
                                                {stop.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.destination_stop_id}
                                    />
                                </div>
                            </div>
                        )}

                        <div className="grid gap-5 sm:grid-cols-[1fr_1fr_0.75fr]">
                            <div className="grid gap-2">
                                <FieldLabel required>
                                    Réaction tarifaire
                                </FieldLabel>
                                <div className="grid grid-cols-2 gap-1.5 rounded-[10px] bg-muted/60 p-1.5 ring-1 ring-border">
                                    {[
                                        {
                                            value: 'increase',
                                            label: 'Majorer',
                                            icon: TrendingUp,
                                        },
                                        {
                                            value: 'decrease',
                                            label: 'Réduire',
                                            icon: TrendingDown,
                                        },
                                    ].map(({ value, label, icon: Icon }) => (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() =>
                                                update(
                                                    'type',
                                                    value as Draft['type'],
                                                )
                                            }
                                            className={`flex h-9 items-center justify-center gap-1.5 rounded-[8px] text-xs font-bold transition-[transform,background-color,color,box-shadow] duration-300 active:scale-[0.98] ${draft.type === value ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:bg-card/70 hover:text-foreground'}`}
                                        >
                                            <Icon
                                                className="size-3.5"
                                                strokeWidth={1.8}
                                            />
                                            {label}
                                        </button>
                                    ))}
                                </div>
                                <input
                                    type="hidden"
                                    name="type"
                                    value={draft.type}
                                />
                                <InputError message={errors.type} />
                            </div>
                            <div className="grid gap-2">
                                <FieldLabel required>Variation</FieldLabel>
                                <div className="relative">
                                    <Input
                                        name="percentage"
                                        type="number"
                                        min={0}
                                        max={1000}
                                        step="0.01"
                                        value={draft.percentage}
                                        onChange={(event) =>
                                            update(
                                                'percentage',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        className="h-11 rounded-[10px] border-0 bg-muted/60 pr-10 ring-1 ring-border"
                                    />
                                    <span className="pointer-events-none absolute inset-y-0 right-3 grid place-items-center text-sm font-black text-muted-foreground">
                                        %
                                    </span>
                                </div>
                                <InputError message={errors.percentage} />
                            </div>
                            <div className="grid gap-2">
                                <FieldLabel>Effet</FieldLabel>
                                <div className="flex h-11 items-center gap-2 rounded-[10px] bg-muted/60 px-3 text-xs font-bold text-muted-foreground ring-1 ring-border">
                                    <Clock3
                                        className="size-4 text-gold"
                                        strokeWidth={1.8}
                                    />
                                    Nouvelles recherches
                                </div>
                                <FieldHint>
                                    Billets existants inchangés.
                                </FieldHint>
                            </div>
                        </div>

                        <div
                            data-onboarding="triggers"
                            className="rounded-[10px] bg-kora/[0.04] p-4 ring-1 ring-kora/15 sm:p-5"
                        >
                            <div className="mb-4 flex items-start gap-3">
                                <span className="grid size-8 shrink-0 place-items-center rounded-[10px] bg-gold/15 text-kora">
                                    <Gauge
                                        className="size-4"
                                        strokeWidth={1.8}
                                    />
                                </span>
                                <div>
                                    <h3 className="text-sm font-extrabold text-foreground">
                                        Déclencheurs
                                    </h3>
                                    <p className="mt-0.5 text-[0.7rem] leading-relaxed text-muted-foreground">
                                        Les critères renseignés sont combinés en
                                        AND. Laissez un critère vide pour ne pas
                                        l'évaluer.
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-3">
                                    <FieldLabel>Taux de remplissage</FieldLabel>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            name="conditions[fill_rate_min]"
                                            type="number"
                                            min={0}
                                            max={100}
                                            placeholder="Min %"
                                            value={draft.fillRateMin}
                                            onChange={(event) =>
                                                update(
                                                    'fillRateMin',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-[10px] border-0 bg-card ring-1 ring-border"
                                        />
                                        <Input
                                            name="conditions[fill_rate_max]"
                                            type="number"
                                            min={0}
                                            max={100}
                                            placeholder="Max %"
                                            value={draft.fillRateMax}
                                            onChange={(event) =>
                                                update(
                                                    'fillRateMax',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-[10px] border-0 bg-card ring-1 ring-border"
                                        />
                                    </div>
                                    <InputError
                                        message={
                                            errors['conditions.fill_rate_min']
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors['conditions.fill_rate_max']
                                        }
                                    />
                                </div>
                                <div className="grid gap-3">
                                    <FieldLabel>Délai avant départ</FieldLabel>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            name="conditions[hours_before_departure_min]"
                                            type="number"
                                            min={0}
                                            placeholder="Min h"
                                            value={
                                                draft.hoursBeforeDepartureMin
                                            }
                                            onChange={(event) =>
                                                update(
                                                    'hoursBeforeDepartureMin',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-[10px] border-0 bg-card ring-1 ring-border"
                                        />
                                        <Input
                                            name="conditions[hours_before_departure_max]"
                                            type="number"
                                            min={0}
                                            placeholder="Max h"
                                            value={
                                                draft.hoursBeforeDepartureMax
                                            }
                                            onChange={(event) =>
                                                update(
                                                    'hoursBeforeDepartureMax',
                                                    event.target.value,
                                                )
                                            }
                                            className="h-10 rounded-[10px] border-0 bg-card ring-1 ring-border"
                                        />
                                    </div>
                                    <InputError
                                        message={
                                            errors[
                                                'conditions.hours_before_departure_min'
                                            ]
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors[
                                                'conditions.hours_before_departure_max'
                                            ]
                                        }
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <FieldLabel>Début de validité</FieldLabel>
                                <Input
                                    name="starts_at"
                                    type="datetime-local"
                                    value={draft.startsAt}
                                    onChange={(event) =>
                                        update('startsAt', event.target.value)
                                    }
                                    className="h-10 rounded-[10px] border-0 bg-muted/60 ring-1 ring-border"
                                />
                                <InputError message={errors.starts_at} />
                            </div>
                            <div className="grid gap-2">
                                <FieldLabel>Fin de validité</FieldLabel>
                                <Input
                                    name="ends_at"
                                    type="datetime-local"
                                    value={draft.endsAt}
                                    onChange={(event) =>
                                        update('endsAt', event.target.value)
                                    }
                                    className="h-10 rounded-[10px] border-0 bg-muted/60 ring-1 ring-border"
                                />
                                <InputError message={errors.ends_at} />
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 rounded-[10px] bg-gold/10 p-4 text-[0.72rem] text-foreground sm:flex-row sm:items-start">
                            <Info
                                className="mt-0.5 size-4 shrink-0 text-gold"
                                strokeWidth={1.8}
                            />
                            <p className="leading-relaxed">
                                La sauvegarde crée ou prépare une règle.
                                Vérifiez l'impact dans le panneau voisin avant
                                d'activer la version.
                            </p>
                        </div>

                        <div className="flex flex-col gap-3 border-t border-border/60 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-[0.68rem] text-muted-foreground">
                                {editing
                                    ? 'Les billets déjà validés restent immuables.'
                                    : 'La règle sera enregistrée en brouillon.'}
                            </p>
                            {canSubmit && (
                                <Button
                                    size="kora"
                                    variant="kora"
                                    type="submit"
                                    disabled={processing || !canSubmitDraft}
                                    className="group"
                                >
                                    {processing
                                        ? 'Enregistrement…'
                                        : editing
                                          ? 'Préparer la nouvelle version'
                                          : 'Créer le brouillon'}
                                    <ArrowRight
                                        className="ml-2 size-4 transition-transform duration-300 group-hover:translate-x-1"
                                        strokeWidth={1.8}
                                    />
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>

            <div className="mt-6 rounded-[10px] bg-muted/30 p-4 ring-1 ring-border">
                <div className="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p className="section-kicker text-gold">
                            Repère de simulation
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Base illustrative fixe pour lire la variation.
                        </p>
                    </div>
                    <span className="rounded-full bg-card px-2.5 py-1 text-[0.65rem] font-bold text-muted-foreground ring-1 ring-border">
                        10 000 XOF
                    </span>
                </div>
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <p className="text-[0.68rem] text-muted-foreground">
                            Prix final indicatif
                        </p>
                        <p className="mt-1 text-2xl font-extrabold tracking-[-0.04em] text-foreground">
                            {simulatedAmount.toLocaleString('fr-FR')}{' '}
                            <span className="text-xs font-bold text-muted-foreground">
                                XOF
                            </span>
                        </p>
                    </div>
                    <span
                        className={`rounded-full px-3 py-1.5 text-xs font-black ${adjustment >= 0 ? 'bg-emerald-500/10 text-emerald-700' : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'}`}
                    >
                        {adjustment >= 0 ? '+' : ''}
                        {adjustment.toLocaleString('fr-FR')} XOF
                    </span>
                </div>
            </div>
        </section>
    );
}
