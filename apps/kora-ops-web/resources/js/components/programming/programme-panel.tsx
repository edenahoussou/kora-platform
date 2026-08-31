import { router } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, Clock, Eye, Info, Sparkles } from 'lucide-react';
import { useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import programmeRoutes from '@/routes/programming/programmes';
import {
    ProgrammingButton as Button,
    ProgrammingInput as Input,
    dateLabel,
    Errors,
    Field,
    PrimaryButton,
    SelectField,
    shiftDate,
    today,
    useProgrammingRequest,
} from './shared';
import type { ChangePreview, Preview, Programme, Run, Template } from './types';

export function ProgrammePanel({
    programme: p,
    templates,
    runs,
    onClose,
}: {
    programme: Programme;
    templates: Template[];
    runs: Run[];
    onClose: () => void;
}) {
    const api = useProgrammingRequest();
    const [horizon, setHorizon] = useState(p.horizonDays);
    const [from, setFrom] = useState(
        today() < p.startsOn ? p.startsOn : today(),
    );
    const [until, setUntil] = useState(
        shiftDate(from, horizon - 1) > p.endsOn
            ? p.endsOn
            : shiftDate(from, horizon - 1),
    );
    const [preview, setPreview] = useState<Preview | null>(null);
    const [autoPreview, setAutoPreview] = useState<Preview | null>(null);
    const [revision, setRevision] = useState<ChangePreview | null>(null);
    const [version, setVersion] = useState('');
    const [tab, setTab] = useState('summary');
    const [message, setMessage] = useState('');
    const path = programmeRoutes.actions.url(p.uuid);

    async function automate() {
        const result = await api.send(path, {
            operation: 'automatic',
            automatic: !p.automatic,
            horizon_days: horizon,
            revision: p.revision,
            token: autoPreview?.token,
        });

        if (result) {
            router.reload();
            onClose();
        }
    }

    async function generate() {
        const result = await api.send(path, {
            operation: 'generate',
            from,
            until,
            token: preview?.token,
        });

        if (result) {
            setMessage(
                'Le planning a été complété avec succès. Les nouveaux départs sont prêts en préparation.',
            );
            setPreview(null);
            router.reload();
        }
    }

    async function compare(apply = false) {
        const result = await api.send<ChangePreview>(path, {
            operation: 'revision',
            from,
            version_uuid: version,
            apply,
            token: revision?.token,
        });

        if (result) {
            setRevision(result);

            if (apply) {
                router.reload();
                setMessage(
                    'Nouvelle version appliquée au programme. L’automatisation a été mise en pause pour vérification.',
                );
            }
        }
    }

    return (
        <Sheet
            open
            onOpenChange={(open) => {
                if (!open && !api.busy) {
                    onClose();
                }
            }}
        >
            <SheetContent className="w-full overflow-y-auto px-6 pb-8 sm:max-w-[620px]">
                <SheetHeader className="px-0 pb-4 border-b border-border">
                    <SheetTitle className="text-xl font-extrabold tracking-[-0.03em] text-foreground">
                        {p.name}
                    </SheetTitle>
                    <SheetDescription className="text-xs text-muted-foreground mt-1">
                        Modèle : <strong className="text-foreground">{p.templateName}</strong> · Version {p.version} ·{' '}
                        {dateLabel(p.startsOn)} au {dateLabel(p.endsOn)}
                    </SheetDescription>
                </SheetHeader>

                <div className="mt-4 flex gap-1 rounded-xl border border-border bg-muted/40 p-1">
                    {[
                        ['summary', 'Résumé & Automatisation'],
                        ['exceptions', 'Exceptions'],
                        ['history', 'Historique des traitements'],
                    ].map(([value, label]) => (
                        <button
                            key={value}
                            onClick={() => setTab(value)}
                            className={`flex-1 rounded-lg py-2 text-xs font-extrabold transition-colors ${tab === value
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <div className="mt-5 grid gap-5">
                    <Errors errors={api.errors} />

                    {message && (
                        <div
                            role="status"
                            className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                        >
                            {message}
                        </div>
                    )}

                    {tab === 'summary' && (
                        <div className="grid gap-5">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-xl border border-border bg-card p-4">
                                    <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                        En préparation
                                    </span>
                                    <p className="mt-1 text-2xl font-extrabold tabular-nums text-foreground">
                                        {p.prepared}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-border bg-card p-4">
                                    <span className="text-[0.6875rem] font-bold text-muted-foreground uppercase">
                                        Publiés (En vente)
                                    </span>
                                    <p className="mt-1 text-2xl font-extrabold tabular-nums text-emerald-600 dark:text-emerald-400">
                                        {p.published}
                                    </p>
                                </div>
                            </div>

                            {/* AUTOMATISATION */}
                            <section className="grid gap-4 rounded-2xl border border-border bg-card p-5">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="text-xs font-extrabold text-foreground">
                                        Horizon glissant et génération automatique
                                    </h3>
                                    <span
                                        className={`rounded-md px-2 py-0.5 text-[0.6875rem] font-bold ${p.automatic
                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                : 'bg-muted text-muted-foreground'
                                            }`}
                                    >
                                        {p.automatic ? 'Actif' : 'En pause'}
                                    </span>
                                </div>

                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    Le programme maintient automatiquement un stock de départs d'avance sur l'horizon configuré.
                                    Les départs complets avec bus assigné sont publiés directement ; les autres restent en préparation.
                                </p>

                                <Field
                                    label="Horizon glissant (jours)"
                                    hint="Nombre de jours à maintenir ouverts à l'avance."
                                >
                                    <Input
                                        type="number"
                                        min={7}
                                        max={90}
                                        value={horizon}
                                        onChange={(e) => {
                                            setHorizon(Number(e.target.value));
                                            setAutoPreview(null);
                                        }}
                                        className="h-10"
                                    />
                                </Field>

                                {!p.automatic && (
                                    <Button
                                        variant="outline"
                                        disabled={api.busy}
                                        onClick={async () => {
                                            const autoFrom =
                                                today() < p.startsOn ? p.startsOn : today();
                                            const autoUntil =
                                                shiftDate(today(), horizon - 1) > p.endsOn
                                                    ? p.endsOn
                                                    : shiftDate(today(), horizon - 1);
                                            setAutoPreview(
                                                await api.send<Preview>(path, {
                                                    operation: 'preview',
                                                    from: autoFrom,
                                                    until: autoUntil,
                                                }),
                                            );
                                        }}
                                    >
                                        Vérifier l’horizon avant d'activer
                                    </Button>
                                )}

                                {autoPreview && (
                                    <div className="rounded-xl border border-border bg-muted/40 p-3.5 text-xs text-foreground">
                                        <strong>{autoPreview.counts.create}</strong> départs calculés ·{' '}
                                        <strong className="text-emerald-600">
                                            {autoPreview.rows.filter((r) => r.state === 'ready').length} prêts à publier
                                        </strong>
                                    </div>
                                )}

                                <PrimaryButton
                                    disabled={api.busy || (!p.automatic && autoPreview === null)}
                                    onClick={automate}
                                >
                                    {p.automatic
                                        ? 'Mettre l’automatisation en pause'
                                        : 'Activer la génération automatique'}
                                </PrimaryButton>
                            </section>

                            {/* COMPLÉTER MANUELLEMENT */}
                            <section className="grid gap-4 rounded-2xl border border-border bg-card p-5">
                                <h3 className="text-xs font-extrabold text-foreground">
                                    Générer une tranche de dates manuellement
                                </h3>

                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Du">
                                        <Input
                                            type="date"
                                            value={from}
                                            min={p.startsOn}
                                            max={p.endsOn}
                                            onChange={(e) => {
                                                setFrom(e.target.value);
                                                setPreview(null);
                                            }}
                                        />
                                    </Field>
                                    <Field label="Au">
                                        <Input
                                            type="date"
                                            value={until}
                                            min={from}
                                            max={p.endsOn}
                                            onChange={(e) => {
                                                setUntil(e.target.value);
                                                setPreview(null);
                                            }}
                                        />
                                    </Field>
                                </div>

                                {!preview && (
                                    <Button
                                        variant="outline"
                                        disabled={api.busy}
                                        onClick={async () => {
                                            setPreview(
                                                await api.send<Preview>(path, {
                                                    operation: 'preview',
                                                    from,
                                                    until,
                                                }),
                                            );
                                        }}
                                    >
                                        Calculer les départs sur cette plage
                                    </Button>
                                )}

                                {preview && (
                                    <div className="grid gap-3">
                                        <div className="rounded-xl border border-border bg-muted/40 p-3 text-xs">
                                            <strong>{preview.counts.create}</strong> départs à ajouter au planning.
                                        </div>
                                        <PrimaryButton
                                            disabled={api.busy}
                                            onClick={generate}
                                        >
                                            Ajouter ces départs au planning
                                        </PrimaryButton>
                                    </div>
                                )}
                            </section>
                        </div>
                    )}

                    {tab === 'exceptions' && (
                        <div className="grid gap-3">
                            {p.exceptions.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-border bg-muted/20 p-6 text-center text-xs text-muted-foreground">
                                    Aucune exception enregistrée pour ce programme.
                                </div>
                            ) : (
                                p.exceptions.map((exc, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center justify-between rounded-xl border border-border bg-card p-3.5 text-xs"
                                    >
                                        <div>
                                            <strong className="text-foreground">{dateLabel(exc.date)}</strong>
                                            <span className="ml-2 text-muted-foreground">
                                                {exc.action === 'exclude'
                                                    ? 'Départ annulé'
                                                    : exc.action === 'add'
                                                        ? `Ajout exceptionnel (${exc.time || 'standard'})`
                                                        : `Modification (${exc.time || 'standard'})`}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    )}

                    {tab === 'history' && (
                        <div className="grid gap-3">
                            {runs.filter((r) => r.programmeUuid === p.uuid).length === 0 ? (
                                <div className="rounded-xl border border-dashed border-border bg-muted/20 p-6 text-center text-xs text-muted-foreground">
                                    Aucun traitement récent archivé.
                                </div>
                            ) : (
                                runs
                                    .filter((r) => r.programmeUuid === p.uuid)
                                    .map((run) => (
                                        <div
                                            key={run.uuid}
                                            className="rounded-xl border border-border bg-card p-4 text-xs"
                                        >
                                            <div className="flex items-center justify-between">
                                                <strong className="text-foreground uppercase tracking-wider text-[0.6875rem]">
                                                    {run.operation}
                                                </strong>
                                                <span className="text-muted-foreground">
                                                    {new Date(run.at).toLocaleString('fr-FR')}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-muted-foreground">
                                                {run.processed} départ(s) traité(s) · Statut : {run.status}
                                            </p>
                                        </div>
                                    ))
                            )}
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
