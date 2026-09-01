import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BusFront,
    CalendarDays,
    CalendarRange,
    CheckCircle2,
    Clock3,
    FileText,
    Info,
    Layers,
    Plus,
    SlidersHorizontal,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import {
    ProgrammingButton as Button,
    ProgrammingInput as Input,
    dateLabel,
    Errors,
    Field,
    PrimaryButton,
    SelectField,
    shiftDate,
    SlotSummary,
    StateBadge,
    Surface,
    today,
    useProgrammingRequest,
    useUnsavedChanges,
} from '@/components/programming/shared';
import type {
    Exception,
    Preview,
    References,
    Template,
} from '@/components/programming/types';
import { cn } from '@/lib/utils';
import programming from '@/routes/programming';
import programmes from '@/routes/programming/programmes';

export default function ProgrammeEditor({
    templates,
    selectedTemplate,
    references: refs,
}: {
    templates: Template[];
    selectedTemplate?: string;
    references: References;
}) {
    const [chosen, setChosen] = useState(
        selectedTemplate ?? templates[0]?.uuid ?? '',
    );
    const template = templates.find((t) => t.uuid === chosen);
    const form = useForm({
        name: '',
        version_uuid: template?.versions[0]?.uuid ?? '',
        starts_on: today(),
        ends_on: shiftDate(today(), 29),
        horizon_days: 30,
        exceptions: [] as Exception[],
    });

    const [preview, setPreview] = useState<Preview | null>(null);
    const [previewData, setPreviewData] = useState('');
    const api = useProgrammingRequest();
    useUnsavedChanges(form.isDirty && !form.processing);

    const version = template?.versions.find(
        (v) => v.uuid === form.data.version_uuid,
    );

    const updateException = (i: number, patch: Partial<Exception>) =>
        form.setData(
            'exceptions',
            form.data.exceptions.map((e, n) =>
                n === i ? { ...e, ...patch } : e,
            ),
        );

    const previewCurrent =
        preview !== null && previewData === JSON.stringify(form.data);

    async function examine() {
        const result = await api.send<Preview>(
            programmes.preview.url(),
            form.data,
        );

        if (result) {
            setPreview(result);
            setPreviewData(JSON.stringify(form.data));
        }
    }

    const setPeriodDuration = (daysCount: number) => {
        form.setData('ends_on', shiftDate(form.data.starts_on, daysCount - 1));
    };

    return (
        <>
            <Head title="Préparer une période de départs" />
            <div className="w-full grid gap-6 pb-24">
                <header className="grid gap-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Link
                            href={programming.index()}
                            className="inline-flex items-center gap-2 rounded-[10px] border border-border bg-card px-3.5 py-2 text-xs font-bold text-foreground transition-colors hover:border-gold hover:text-foreground"
                        >
                            <ArrowLeft size={15} strokeWidth={2} />
                            Retour au planning
                        </Link>
                        <span className="text-xs font-semibold text-muted-foreground">
                            Exploitation / Programmation / Nouveau programme
                        </span>
                    </div>

                    <div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.4rem)] font-extrabold tracking-[-0.04em] text-foreground">
                            Préparer une période de départs
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Appliquez une semaine type sur un calendrier précis et personnalisez les exceptions (fêtes, renforts).
                        </p>
                    </div>

                    <div className="grid gap-3 rounded-2xl border border-border bg-card p-5 sm:grid-cols-[auto_1fr] sm:items-center">
                        <span className="grid size-11 place-items-center rounded-xl bg-gold/15 text-kora dark:text-gold">
                            <Info size={20} strokeWidth={2} />
                        </span>
                        <div>
                            <p className="text-sm font-extrabold text-foreground">
                                Comment fonctionne la préparation de période ?
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                Les départs sont générés dans votre planning en état de préparation. Rien n'est mis en vente immédiatement :
                                vous pourrez vérifier les affectations de bus et publier vos trajets en toute sécurité depuis la vue planning.
                            </p>
                        </div>
                    </div>
                </header>

                <Errors errors={{ ...form.errors, ...api.errors }} />

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="grid gap-6">
                        {/* 1. MODÈLE ET PÉRIODE */}
                        <Surface>
                            <div className="grid gap-5">
                                <div className="border-b border-border pb-4">
                                    <span className="text-[0.6875rem] font-extrabold tracking-wider text-gold uppercase">
                                        Étape 1
                                    </span>
                                    <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                        1. Modèle réutilisable et période
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Sélectionnez la semaine type à instancier et la plage de dates.
                                    </p>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <SelectField
                                        label="Modèle réutilisable"
                                        icon={Layers}
                                        required
                                        hint="Le modèle qui fournit la structure des départs."
                                        value={chosen}
                                        options={templates.map((t) => ({
                                            uuid: t.uuid,
                                            label: t.name,
                                        }))}
                                        onChange={(v) => {
                                            setChosen(v);
                                            form.setData(
                                                'version_uuid',
                                                templates.find((t) => t.uuid === v)
                                                    ?.versions[0]?.uuid ?? '',
                                            );
                                        }}
                                    />
                                    <SelectField
                                        label="Version approuvée"
                                        icon={FileText}
                                        required
                                        hint="Version du modèle à appliquer."
                                        value={form.data.version_uuid}
                                        options={
                                            template?.versions.map((v) => ({
                                                uuid: v.uuid,
                                                label: `Version ${v.number}`,
                                            })) ?? []
                                        }
                                        onChange={(v) =>
                                            form.setData('version_uuid', v)
                                        }
                                    />
                                </div>

                                <Field
                                    label="Nom de la période / du programme"
                                    icon={FileText}
                                    required
                                    hint="Permet d’identifier clairement ce bloc de départs sur le planning."
                                >
                                    <Input
                                        className="h-11 font-semibold"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        placeholder="Ex: Programme de Septembre — Quinzaine 1"
                                    />
                                </Field>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="Date de début"
                                        icon={CalendarDays}
                                        required
                                        hint="Premier jour d'application de la semaine type."
                                    >
                                        <Input
                                            type="date"
                                            min={today()}
                                            value={form.data.starts_on}
                                            onChange={(e) =>
                                                form.setData('starts_on', e.target.value)
                                            }
                                            className="h-11"
                                        />
                                    </Field>
                                    <Field
                                        label="Date de fin"
                                        icon={CalendarDays}
                                        required
                                        hint="Dernier jour inclus dans cette période (90 jours max)."
                                    >
                                        <Input
                                            type="date"
                                            min={form.data.starts_on}
                                            value={form.data.ends_on}
                                            onChange={(e) =>
                                                form.setData('ends_on', e.target.value)
                                            }
                                            className="h-11"
                                        />
                                    </Field>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 pt-1">
                                    <span className="text-xs font-bold text-muted-foreground">
                                        Raccourcis de durée :
                                    </span>
                                    {[
                                        ['7 jours', 7],
                                        ['15 jours', 15],
                                        ['30 jours (1 mois)', 30],
                                        ['60 jours (2 mois)', 60],
                                        ['90 jours (3 mois)', 90],
                                    ].map(([label, daysCount]) => (
                                        <button
                                            key={String(label)}
                                            type="button"
                                            onClick={() => setPeriodDuration(Number(daysCount))}
                                            className="rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-semibold text-foreground transition-colors hover:border-gold hover:bg-gold/10"
                                        >
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </Surface>

                        {/* 2. EXCEPTIONS */}
                        <Surface>
                            <div className="grid gap-5">
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                                    <div>
                                        <span className="text-[0.6875rem] font-extrabold tracking-wider text-gold uppercase">
                                            Étape 2
                                        </span>
                                        <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                            2. Exceptions de calendrier (Optionnel)
                                        </h2>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Ajustez ponctuellement certaines dates (jours fériés, horaires modifiés, bus de renfort).
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={!version}
                                        onClick={() =>
                                            form.setData('exceptions', [
                                                ...form.data.exceptions,
                                                {
                                                    date: form.data.starts_on,
                                                    slot_key:
                                                        version?.slots[0]?.key ?? '',
                                                    action: 'exclude',
                                                },
                                            ])
                                        }
                                        className="gap-1.5 border-gold/40 hover:border-gold hover:bg-gold/10"
                                    >
                                        <Plus size={14} className="text-gold" />
                                        Ajouter une exception
                                    </Button>
                                </div>

                                {form.data.exceptions.length === 0 ? (
                                    <div className="rounded-xl border border-dashed border-border bg-muted/20 p-5 text-center text-xs text-muted-foreground">
                                        Aucune exception définie. La semaine type sera appliquée de manière identique sur toute la période.
                                    </div>
                                ) : (
                                    <div className="grid gap-3">
                                        {form.data.exceptions.map((exception, i) => (
                                            <div
                                                key={i}
                                                className="grid gap-3 rounded-xl border border-border bg-background p-4"
                                            >
                                                <div className="flex items-center justify-between gap-3 border-b border-border pb-2">
                                                    <span className="text-xs font-extrabold text-foreground">
                                                        Exception n°{i + 1}
                                                    </span>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            form.setData(
                                                                'exceptions',
                                                                form.data.exceptions.filter(
                                                                    (_, n) => n !== i,
                                                                ),
                                                            )
                                                        }
                                                        className="h-7 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    >
                                                        <Trash2 size={13} className="mr-1" />
                                                        Supprimer
                                                    </Button>
                                                </div>

                                                <div className="grid gap-3 sm:grid-cols-3">
                                                    <Field
                                                        label="Date concernée"
                                                        icon={CalendarDays}
                                                        required
                                                    >
                                                        <Input
                                                            type="date"
                                                            value={exception.date}
                                                            min={form.data.starts_on}
                                                            max={form.data.ends_on}
                                                            onChange={(e) =>
                                                                updateException(i, {
                                                                    date: e.target.value,
                                                                })
                                                            }
                                                        />
                                                    </Field>

                                                    <SelectField
                                                        label="Départ de la semaine type"
                                                        icon={Clock3}
                                                        required
                                                        value={exception.slot_key}
                                                        options={
                                                            version?.slots.map((s) => ({
                                                                uuid: s.key,
                                                                label: `${s.direction === 'outbound' ? 'Aller' : 'Retour'} · ${s.time}`,
                                                            })) ?? []
                                                        }
                                                        onChange={(v) =>
                                                            updateException(i, {
                                                                slot_key: v,
                                                            })
                                                        }
                                                    />

                                                    <SelectField
                                                        label="Action"
                                                        icon={SlidersHorizontal}
                                                        required
                                                        value={exception.action}
                                                        options={[
                                                            {
                                                                uuid: 'exclude',
                                                                label: 'Ne pas faire circuler ce jour',
                                                            },
                                                            {
                                                                uuid: 'change',
                                                                label: 'Modifier horaire / bus',
                                                            },
                                                            {
                                                                uuid: 'add',
                                                                label: 'Ajouter un départ spécial',
                                                            },
                                                        ]}
                                                        onChange={(v) =>
                                                            updateException(i, {
                                                                action: v as Exception['action'],
                                                            })
                                                        }
                                                    />
                                                </div>

                                                {exception.action !== 'exclude' && (
                                                    <div className="grid gap-3 border-t border-border pt-3 sm:grid-cols-2">
                                                        <Field
                                                            label="Heure exceptionnelle (optionnel)"
                                                            icon={Clock3}
                                                            hint="Laisser vide pour conserver l'heure standard."
                                                        >
                                                            <Input
                                                                type="time"
                                                                value={exception.time ?? ''}
                                                                onChange={(e) =>
                                                                    updateException(i, {
                                                                        time: e.target.value || undefined,
                                                                    })
                                                                }
                                                                className="font-mono"
                                                            />
                                                        </Field>

                                                        <SelectField
                                                            label="Bus de remplacement (optionnel)"
                                                            icon={BusFront}
                                                            hint="Laisser vide pour conserver le bus habituel."
                                                            value={exception.bus_uuid}
                                                            empty="Conserver le bus habituel"
                                                            options={refs.buses.filter(
                                                                (b) => b.status === 'active',
                                                            )}
                                                            onChange={(v) => {
                                                                const exceptions = [...form.data.exceptions];

                                                                if (v) {
                                                                    exceptions[i] = { ...exception, bus_uuid: v };
                                                                } else {
                                                                    // eslint-disable-next-line @typescript-eslint/no-unused-vars
                                                                    const { bus_uuid: _busUuid, ...rest } = exception;
                                                                    exceptions[i] = rest;
                                                                }

                                                                form.setData('exceptions', exceptions);
                                                            }}
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </Surface>

                        {/* 3. PRÉVISUALISATION ET CALCUL */}
                        <Surface>
                            <div className="grid gap-5">
                                <div className="border-b border-border pb-4">
                                    <span className="text-[0.6875rem] font-extrabold tracking-wider text-gold uppercase">
                                        Étape 3
                                    </span>
                                    <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                        3. Calcul et prévisualisation des départs
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Lancez le calcul pour voir les départs exacts qui seront instanciés sur le planning.
                                    </p>
                                </div>

                                <PrimaryButton
                                    className="w-fit gap-2 shadow-sm"
                                    disabled={api.busy || !version}
                                    onClick={examine}
                                >
                                    <CalendarRange size={16} />
                                    {api.busy ? 'Calcul des départs en cours…' : 'Calculer la prévisualisation'}
                                </PrimaryButton>

                                {preview && (
                                    <div className="grid gap-4">
                                        <div className="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-muted/40 p-4">
                                            <div className="flex items-center gap-2">
                                                <span className="size-2.5 rounded-full bg-emerald-500" />
                                                <span className="text-xs font-extrabold text-foreground">
                                                    {preview.counts.create} départ(s) calculé(s)
                                                </span>
                                            </div>
                                            {preview.counts.excluded > 0 && (
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <span className="size-2.5 rounded-full bg-slate-400" />
                                                    <span className="text-xs font-semibold">
                                                        {preview.counts.excluded} exception(s) exclue(s)
                                                    </span>
                                                </div>
                                            )}
                                            {preview.rows.filter((r) => r.state === 'blocked').length > 0 && (
                                                <div className="flex items-center gap-2 rounded-lg bg-destructive/10 px-3 py-1.5">
                                                    <AlertTriangle size={14} className="text-destructive" />
                                                    <span className="text-xs font-extrabold text-destructive">
                                                        {preview.rows.filter((r) => r.state === 'blocked').length} départ(s) bloqué(s) — ressource(s) en conflit
                                                    </span>
                                                </div>
                                            )}
                                        </div>

                                        {!previewCurrent && (
                                            <div className="rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs font-semibold text-destructive">
                                                Les paramètres de dates ou d'exceptions ont été modifiés. Veuillez recalculer la prévisualisation.
                                            </div>
                                        )}

                                        <div className="max-h-96 space-y-2 overflow-y-auto rounded-xl border border-border bg-card p-3">
                                            {preview.rows.map((row) => {
                                                const isBlocked = row.state === 'blocked';

                                                return (
                                                    <div
                                                        key={row.key}
                                                        className={cn(
                                                            'flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-xs transition-colors',
                                                            isBlocked
                                                                ? 'border-destructive/40 bg-destructive/[0.06] shadow-[inset_0_0_0_1px_rgba(220,38,38,0.08)]'
                                                                : 'border-border/60 bg-background',
                                                        )}
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            {isBlocked && (
                                                                <AlertTriangle
                                                                    size={14}
                                                                    className="shrink-0 text-destructive"
                                                                    aria-label="Départ bloqué"
                                                                />
                                                            )}
                                                            <strong className={cn('font-semibold', isBlocked ? 'text-destructive' : 'text-foreground')}>
                                                                {dateLabel(row.date)}
                                                            </strong>
                                                            <span className={cn('font-mono font-bold', isBlocked ? 'text-destructive' : 'text-foreground')}>
                                                                {row.time}
                                                            </span>
                                                            <span className={cn(
                                                                'rounded-md px-2 py-0.5 text-[0.6875rem] font-bold',
                                                                isBlocked
                                                                    ? 'bg-destructive/15 text-destructive'
                                                                    : 'bg-muted text-muted-foreground',
                                                            )}>
                                                                {row.direction === 'outbound' ? 'Aller' : 'Retour'}
                                                            </span>
                                                        </div>

                                                        <StateBadge state={row.state} />

                                                        {row.blockers.length > 0 && (
                                                            <div className="w-full rounded-md border border-destructive/20 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                                                <span className="font-semibold">
                                                                    {row.blockers.join(' · ')}
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </Surface>
                    </div>

                    {/* PANNEAU LATÉRAL RECAPITULATIF */}
                    <aside className="sticky top-6">
                        <Surface>
                            <span className="text-[0.6875rem] font-extrabold tracking-wider text-gold uppercase">
                                Modèle sélectionné
                            </span>
                            <h3 className="mt-1.5 text-base font-extrabold text-foreground">
                                {template?.name ?? 'Sélectionnez un modèle'}
                            </h3>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {template ? `Version ${version?.number || 1}` : ''}
                            </p>

                            <div className="mt-4 grid gap-2.5">
                                {version?.slots.map((s) => (
                                    <SlotSummary key={s.key} slot={s} refs={refs} />
                                ))}
                            </div>

                            <div className="mt-5 rounded-xl border border-border bg-muted/40 p-3.5 text-xs text-muted-foreground">
                                Une fois ajoutés au planning, ces départs apparaîtront dans la grille opérationnelle pour l'affectation finale des bus et la publication pour la vente.
                            </div>
                        </Surface>
                    </aside>
                </div>

                <footer className="sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card/95 p-4 shadow-[0_8px_30px_rgba(0,29,78,0.12)] backdrop-blur">
                    <Button variant="outline" asChild>
                        <Link href={programming.index()}>
                            <ArrowLeft size={15} strokeWidth={2} />
                            Retour au planning
                        </Link>
                    </Button>

                    <PrimaryButton
                        disabled={!previewCurrent || form.processing || api.busy}
                        onClick={() => form.post(programmes.store.url())}
                        className="gap-2"
                    >
                        {form.processing ? (
                            'Génération en cours…'
                        ) : (
                            <>
                                <CheckCircle2 size={16} />
                                Ajouter ces départs au planning
                            </>
                        )}
                    </PrimaryButton>
                </footer>
            </div>
        </>
    );
}
