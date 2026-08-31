import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    FilePlus2,
    Filter,
    Layers,
    ListChecks,
    Plus,
    RotateCcw,
    Settings2,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import { ItemPanel } from '@/components/programming/item-panel';
import { PlanningCalendar } from '@/components/programming/planning-calendar';
import { ProgrammePanel } from '@/components/programming/programme-panel';
import {
    ProgrammingButton as Button,
    ProgrammingInput as Input,
    Confirm,
    dateLabel,
    Empty,
    Errors,
    PrimaryButton,
    SearchInput,
    SelectField,
    shiftDate,
    StateBadge,
    states,
    stateTheme,
    Surface,
    today,
    useProgrammingRequest,
} from '@/components/programming/shared';
import type {
    PlanningItem,
    Programme,
    References,
    Run,
    State,
    Template,
} from '@/components/programming/types';
import { cn } from '@/lib/utils';
import departures from '@/routes/departures';
import programming from '@/routes/programming';
import itemRoutes from '@/routes/programming/items';
import programmeRoutes from '@/routes/programming/programmes';
import templateRoutes from '@/routes/programming/templates';

type Props = {
    items: PlanningItem[];
    references: References;
    templates: Template[];
    programmes: Programme[];
    runs: Run[];
    from: string;
    until: string;
    filters: Record<string, string>;
    counts: Record<string, number>;
    canCancel: boolean;
};

export default function ProgrammingPage({
    items,
    references: refs,
    templates,
    programmes,
    runs,
    from,
    until,
    filters,
    counts,
    canCancel,
}: Props) {
    const [selected, setSelected] = useState<string[]>([]);
    const [detail, setDetail] = useState<PlanningItem | null>(null);
    const [programme, setProgramme] = useState<Programme | null>(null);
    const [action, setAction] = useState<
        'publish' | 'assign' | 'exclude' | null
    >(null);
    const [bus, setBus] = useState('');
    const [search, setSearch] = useState('');
    const [archive, setArchive] = useState<Template | null>(null);
    const [result, setResult] = useState('');
    const [loading, setLoading] = useState(false);
    const [showGuide, setShowGuide] = useState(
        templates.length === 0 && programmes.length === 0,
    );
    const api = useProgrammingRequest();
    usePoll(15000, { only: ['runs', 'programmes', 'items', 'counts'] });

    const tab = filters.tab ?? 'planning';

    function periodDate(direction: number) {
        if (filters.view === 'month') {
            const date = new Date(`${filters.date.slice(0, 7)}-01T12:00:00Z`);
            date.setUTCMonth(date.getUTCMonth() + direction);
            return date.toISOString().slice(0, 10);
        }

        return shiftDate(
            filters.date,
            direction * (filters.view === 'list' ? 1 : 7),
        );
    }

    function navigate(patch: Record<string, string>) {
        setSelected([]);
        router.get(
            programming.index.url(),
            { ...filters, ...patch },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    }

    async function open(item: PlanningItem) {
        if (item.manual && item.departureUuid) {
            router.visit(departures.show.url(item.departureUuid));
            return;
        }

        const loaded = await api.send<PlanningItem>(
            itemRoutes.show.url(item.uuid),
            undefined,
            'get',
        );

        if (loaded) {
            setDetail(loaded);
        }
    }

    async function act() {
        const selection = items.filter((i) => selected.includes(i.uuid));
        const response = await api.send<{
            processed: number;
            queued?: number;
            errors: Record<string, string>;
        }>(programming.actions.url(), {
            operation: action,
            items: selection.map((i) => ({
                uuid: i.uuid,
                revision: i.revision,
            })),
            changes: action === 'assign' ? { bus_uuid: bus || null } : {},
        });

        if (response) {
            setAction(null);
            setResult(
                response.queued
                    ? `${response.queued} départs en cours de traitement. Le planning se met à jour automatiquement.`
                    : `${response.processed} départ(s) traité(s) avec succès. ${Object.keys(response.errors).length} élément(s) à vérifier.`,
            );
            api.setErrors(response.errors);
            setSelected([]);
            router.reload();
        }
    }

    const selection = items.filter((i) => selected.includes(i.uuid));
    const ready = selection.filter((i) => i.state === 'ready').length;
    const matchingTemplates = templates.filter((t) =>
        t.name.toLocaleLowerCase().includes(search.toLocaleLowerCase()),
    );
    const publishedCount = counts.published ?? 0;
    const readyCount = counts.ready ?? 0;
    const blockedCount = counts.blocked ?? 0;

    const flow = [
        {
            icon: Layers,
            title: '1. Modèles de départs',
            text: 'Configurez une semaine type (liaisons, arrêts, horaires réguliers) une seule fois.',
            href: templateRoutes.create(),
            action: 'Créer un modèle',
        },
        {
            icon: CalendarClock,
            title: '2. Préparer une période',
            text: 'Générez les départs sur une plage de dates (ex: 1 mois) avec vos exceptions.',
            href: programmeRoutes.create(),
            action: 'Nouveau programme',
        },
        {
            icon: ListChecks,
            title: '3. Contrôler & Publier',
            text: 'Affectez les bus et équipages, résolvez les alertes et mettez en vente.',
            href: programming.index({ query: { tab: 'planning' } }),
            action: 'Voir le planning',
        },
    ];

    const hasActiveFilters = Object.entries(filters).some(
        ([k, v]) =>
            [
                'line',
                'agency',
                'bus',
                'person',
                'programme',
                'direction',
                'exceptions',
            ].includes(k) && v,
    );

    return (
        <>
            <Head title="Programmation des départs" />
            <div className="w-full grid gap-6 pb-24">
                {/* HEADER */}
                <header className="flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <div className="mb-2 text-xs font-semibold text-muted-foreground">
                            KORA OPS / Exploitation / Programmation
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.5rem)] font-extrabold tracking-[-0.04em] text-foreground">
                            Programmation des départs
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Générez vos départs réguliers depuis des modèles types, affectez vos bus et publiez vos plannings pour la vente.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <Button
                            variant="outline"
                            asChild
                            className="gap-2"
                        >
                            <Link href={departures.index()}>
                                <FilePlus2 size={15} />
                                Départ ponctuel
                            </Link>
                        </Button>
                        <PrimaryButton asChild className="gap-2 shadow-sm">
                            <Link
                                href={
                                    tab === 'templates'
                                        ? templateRoutes.create()
                                        : programmeRoutes.create()
                                }
                            >
                                <Plus size={15} />
                                {tab === 'templates'
                                    ? 'Créer un modèle'
                                    : 'Préparer une période'}
                            </Link>
                        </PrimaryButton>
                    </div>
                </header>

                {/* FLOW GUIDANCE CARDS */}
                <section
                    className="grid gap-4 md:grid-cols-3"
                    aria-label="Parcours de programmation"
                >
                    {flow.map(({ icon: Icon, title, text, href, action: actionLabel }) => (
                        <Link
                            key={title}
                            href={href}
                            className="group flex flex-col justify-between rounded-2xl border border-border bg-card p-5 transition-all duration-200 hover:-translate-y-0.5 hover:border-gold hover:shadow-xs"
                        >
                            <div>
                                <span className="grid size-10 place-items-center rounded-xl bg-gold/15 text-kora transition-transform group-hover:scale-105 dark:text-gold">
                                    <Icon size={20} strokeWidth={2} />
                                </span>
                                <h2 className="mt-4 text-sm font-extrabold tracking-[-0.02em] text-foreground">
                                    {title}
                                </h2>
                                <p className="mt-1.5 text-xs leading-relaxed text-muted-foreground">
                                    {text}
                                </p>
                            </div>
                            <span className="mt-4 inline-flex items-center gap-1 text-xs font-extrabold text-kora dark:text-gold">
                                {actionLabel}{' '}
                                <ChevronRight
                                    size={14}
                                    className="transition-transform group-hover:translate-x-1"
                                />
                            </span>
                        </Link>
                    ))}
                </section>

                {/* OVERVIEW STATS */}
                <section
                    className="grid grid-flow-dense gap-4 md:grid-cols-12"
                    aria-label="État de la programmation"
                >
                    <div className="rounded-2xl border border-border bg-card p-5 md:col-span-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <span className="text-[0.6875rem] font-bold tracking-wider text-gold uppercase">
                                    Suivi opérationnel
                                </span>
                                <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                    Départs à surveiller et à publier
                                </h2>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowGuide((value) => !value)}
                                title="Afficher ou masquer les compteurs"
                                aria-expanded={showGuide}
                                className="grid size-8 shrink-0 place-items-center rounded-lg border border-border text-muted-foreground transition-colors hover:border-gold hover:text-foreground"
                            >
                                <CircleHelp size={16} />
                            </button>
                        </div>
                        {showGuide && (
                            <div className="mt-4 grid gap-3 border-t border-border pt-4 sm:grid-cols-3">
                                <div className="rounded-xl bg-gold/10 p-3.5 border border-gold/20">
                                    <span className="text-2xl font-black tabular-nums text-foreground">
                                        {readyCount}
                                    </span>
                                    <p className="mt-0.5 text-xs font-semibold text-muted-foreground">
                                        Prêts à publier (Complets)
                                    </p>
                                </div>
                                <div className="rounded-xl bg-destructive/10 p-3.5 border border-destructive/20">
                                    <span className="text-2xl font-black tabular-nums text-destructive">
                                        {blockedCount}
                                    </span>
                                    <p className="mt-0.5 text-xs font-semibold text-muted-foreground">
                                        À corriger (Alertes)
                                    </p>
                                </div>
                                <div className="rounded-xl bg-emerald-500/10 p-3.5 border border-emerald-500/20">
                                    <span className="text-2xl font-black tabular-nums text-emerald-600 dark:text-emerald-400">
                                        {publishedCount}
                                    </span>
                                    <p className="mt-0.5 text-xs font-semibold text-muted-foreground">
                                        Déjà publiés (En vente)
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="rounded-2xl border border-gold/30 bg-gold/[0.08] p-5 md:col-span-4 flex flex-col justify-between">
                        <div>
                            <div className="flex items-center gap-2 text-kora dark:text-gold font-extrabold text-xs">
                                <Settings2 size={16} />
                                Guide rapide
                            </div>
                            <p className="mt-2 text-xs leading-relaxed text-foreground/90">
                                Commencez par créer un <strong>modèle</strong> si vos départs ont des horaires fixes.
                                Appliquez-le ensuite pour générer vos <strong>programmes</strong>.
                            </p>
                        </div>
                        <Link
                            href={templateRoutes.create()}
                            className="mt-4 inline-flex items-center gap-1.5 text-xs font-extrabold text-kora dark:text-gold"
                        >
                            <BookOpen size={14} /> Créer votre premier modèle de départs
                        </Link>
                    </div>
                </section>

                {/* TABS NAVIGATION */}
                <nav
                    className="flex gap-2 border-b border-border pb-1"
                    aria-label="Onglets de programmation"
                >
                    {[
                        ['planning', 'Grille du Planning'],
                        ['templates', 'Modèles de départs'],
                        ['programmes', 'Programmes de périodes'],
                    ].map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => navigate({ tab: key })}
                            aria-current={tab === key ? 'page' : undefined}
                            className={`rounded-lg px-4 py-2.5 text-xs font-extrabold transition-colors ${tab === key
                                ? 'bg-kora text-white shadow-xs dark:bg-gold dark:text-kora'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                }`}
                        >
                            {label}
                        </button>
                    ))}
                </nav>

                <Errors errors={api.errors} />

                {result && (
                    <div
                        role="status"
                        className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-xs font-semibold text-emerald-800 dark:text-emerald-300"
                    >
                        {result}
                    </div>
                )}

                {/* PLANNING TAB */}
                {tab === 'planning' && (
                    <>
                        {/* CALENDAR CONTROLS */}
                        <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-card p-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Période précédente"
                                    onClick={() =>
                                        navigate({
                                            date: periodDate(-1),
                                        })
                                    }
                                >
                                    <ChevronLeft size={16} />
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => navigate({ date: today() })}
                                >
                                    Aujourd’hui
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label="Période suivante"
                                    onClick={() =>
                                        navigate({
                                            date: periodDate(1),
                                        })
                                    }
                                >
                                    <ChevronRight size={16} />
                                </Button>
                                <Input
                                    id="planning-date"
                                    type="date"
                                    value={filters.date}
                                    className="w-auto h-10"
                                    onChange={(e) => {
                                        if (e.target.value) {
                                            navigate({ date: e.target.value });
                                        }
                                    }}
                                />
                                <span className="ml-2 text-xs font-extrabold text-foreground">
                                    {dateLabel(from)} — {dateLabel(until)}
                                </span>
                            </div>

                            <div className="flex rounded-[10px] border border-border bg-muted/40 p-1">
                                {[
                                    ['week', 'Semaine'],
                                    ['month', 'Mois'],
                                    ['list', 'Liste'],
                                ].map(([key, label]) => (
                                    <button
                                        key={key}
                                        aria-pressed={filters.view === key}
                                        onClick={() => navigate({ view: key })}
                                        className={`rounded-lg px-3.5 py-1.5 text-xs font-extrabold transition-all ${filters.view === key
                                            ? 'bg-card text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* FILTER ACCORDION / BAR */}
                        <details className="group rounded-2xl border border-border bg-card p-4 transition-all">
                            <summary className="flex cursor-pointer items-center justify-between text-xs font-extrabold text-foreground">
                                <span className="flex items-center gap-2">
                                    <Filter size={15} className="text-kora dark:text-gold" />
                                    Filtres avancés du planning
                                    {hasActiveFilters && (
                                        <span className="rounded-full bg-gold/20 px-2 py-0.5 text-[0.6875rem] text-kora dark:text-gold font-extrabold">
                                            Filtres actifs
                                        </span>
                                    )}
                                </span>
                                <span className="text-xs text-muted-foreground font-normal">
                                    {hasActiveFilters ? 'Modifier' : 'Ouvrir'}
                                </span>
                            </summary>

                            <div className="mt-4 grid gap-4 border-t border-border pt-4 sm:grid-cols-2 xl:grid-cols-4">
                                <SelectField
                                    label="Liaison"
                                    value={filters.line}
                                    empty="Toutes les liaisons"
                                    options={refs.lines}
                                    onChange={(v) => navigate({ line: v })}
                                />
                                <SelectField
                                    label="Agence de départ"
                                    value={filters.agency}
                                    empty="Toutes les agences"
                                    options={refs.agencies}
                                    onChange={(v) => navigate({ agency: v })}
                                />
                                <SelectField
                                    label="Bus assigné"
                                    value={filters.bus}
                                    empty="Tous les bus"
                                    options={refs.buses}
                                    onChange={(v) => navigate({ bus: v })}
                                />
                                <SelectField
                                    label="Équipage / Personnel"
                                    value={filters.person}
                                    empty="Toutes les personnes"
                                    options={refs.people}
                                    onChange={(v) => navigate({ person: v })}
                                />
                                <SelectField
                                    label="Programme"
                                    value={filters.programme}
                                    empty="Tous les programmes"
                                    options={programmes.map((p) => ({
                                        uuid: p.uuid,
                                        label: p.name,
                                    }))}
                                    onChange={(v) => navigate({ programme: v })}
                                />
                                <SelectField
                                    label="Sens de trajet"
                                    value={filters.direction}
                                    empty="Tous les sens"
                                    options={[
                                        { uuid: 'outbound', label: 'Aller uniquement' },
                                        { uuid: 'return', label: 'Retour uniquement' },
                                    ]}
                                    onChange={(v) => navigate({ direction: v })}
                                />
                                <label className="flex items-center gap-2.5 text-xs font-bold text-foreground cursor-pointer pt-6">
                                    <input
                                        type="checkbox"
                                        className="size-4 rounded accent-gold"
                                        checked={filters.exceptions === '1'}
                                        onChange={(e) =>
                                            navigate({
                                                exceptions: e.target.checked ? '1' : '',
                                            })
                                        }
                                    />
                                    Exceptions manuelles uniquement
                                </label>
                                <div className="flex items-end">
                                    <Button
                                        variant="ghost"
                                        onClick={() =>
                                            navigate({
                                                line: '',
                                                agency: '',
                                                bus: '',
                                                person: '',
                                                programme: '',
                                                direction: '',
                                                state: '',
                                                exceptions: '',
                                            })
                                        }
                                        className="gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        <RotateCcw size={14} />
                                        Réinitialiser les filtres
                                    </Button>
                                </div>
                            </div>
                        </details>

                        {/* QUICK STATE CHIPS */}
                        <div className="flex flex-wrap items-center gap-2">
                            {[
                                ['', 'Tout afficher', counts.all, null],
                                ...Object.entries(states).map(
                                    ([state, label]) => [
                                        state,
                                        label,
                                        counts[state] ?? 0,
                                        state,
                                    ],
                                ),
                            ].map(([key, label, count, stateKey]) => {
                                const active = (filters.state ?? '') === key;
                                const theme =
                                    stateKey != null
                                        ? stateTheme[stateKey as State]
                                        : null;

                                return (
                                    <button
                                        key={String(key)}
                                        aria-pressed={active}
                                        onClick={() =>
                                            navigate({ state: String(key) })
                                        }
                                        className={cn(
                                            'inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-extrabold transition-all',
                                            active
                                                ? (theme?.chipActive ??
                                                    'border-gold bg-gold/15 text-foreground ring-1 ring-gold/40')
                                                : (theme?.chip ??
                                                    'border-border bg-card text-muted-foreground hover:border-gold/60 hover:text-foreground'),
                                        )}
                                    >
                                        {theme && (
                                            <span
                                                className={cn(
                                                    'size-2 rounded-full',
                                                    active
                                                        ? theme.dot
                                                        : 'bg-muted-foreground/40',
                                                )}
                                                aria-hidden
                                            />
                                        )}
                                        {label}{' '}
                                        <span className="tabular-nums font-mono opacity-80">
                                            ({count})
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {/* PLANNING SURFACE */}
                        <Surface>
                            <div
                                aria-busy={loading}
                                className={loading ? 'pointer-events-none opacity-60' : ''}
                            >
                                {loading && (
                                    <p role="status" className="mb-3 text-xs font-semibold text-muted-foreground">
                                        Mise à jour du planning…
                                    </p>
                                )}
                                <PlanningCalendar
                                    items={items}
                                    refs={refs}
                                    from={from}
                                    until={until}
                                    view={filters.view}
                                    selected={selected}
                                    onSelect={(uuid) =>
                                        setSelected(
                                            selected.includes(uuid)
                                                ? selected.filter((v) => v !== uuid)
                                                : [...selected, uuid],
                                        )
                                    }
                                    onOpen={open}
                                    onDay={(date) =>
                                        navigate({ date, view: 'list' })
                                    }
                                />
                            </div>
                        </Surface>
                    </>
                )}

                {runs.some((r) => ['queued', 'running'].includes(r.status)) && (
                    <div
                        role="status"
                        className="rounded-xl border border-digital/30 bg-digital/10 p-4 text-xs font-semibold text-digital"
                    >
                        Traitement en arrière-plan en cours :{' '}
                        {runs
                            .filter((r) => ['queued', 'running'].includes(r.status))
                            .map((r) => `${r.processed} départs`)
                            .join(' · ')}
                    </div>
                )}

                {/* TEMPLATES TAB */}
                {tab === 'templates' && (
                    <div className="grid gap-5">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="w-full sm:max-w-md">
                                <SearchInput
                                    value={search}
                                    onChange={setSearch}
                                    placeholder="Rechercher un modèle de départs…"
                                />
                            </div>
                            <PrimaryButton asChild className="gap-2 shadow-xs">
                                <Link href={templateRoutes.create()}>
                                    <Plus size={15} />
                                    Nouveau modèle
                                </Link>
                            </PrimaryButton>
                        </div>

                        <Surface>
                            {matchingTemplates.length ? (
                                <div className="grid gap-4">
                                    {matchingTemplates.map((t) => {
                                        const slots =
                                            t.versions[0]?.slots ??
                                            t.draft?.slots ??
                                            [];

                                        return (
                                            <article
                                                key={t.uuid}
                                                className="flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-border bg-card p-5 transition-all hover:border-gold/60"
                                            >
                                                <div className="flex items-start gap-4">
                                                    <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-gold/15 text-kora dark:text-gold">
                                                        <Layers size={20} strokeWidth={2} />
                                                    </span>
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <h2 className="text-sm font-extrabold text-foreground">
                                                                {t.name}
                                                            </h2>
                                                            <span
                                                                className={`rounded-md px-2 py-0.5 text-[0.6875rem] font-bold ${t.status === 'available'
                                                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                                    : t.status === 'draft'
                                                                        ? 'bg-gold/20 text-kora dark:text-gold'
                                                                        : 'bg-muted text-muted-foreground'
                                                                    }`}
                                                            >
                                                                {t.status === 'available'
                                                                    ? `Version ${t.versions[0]?.number}`
                                                                    : t.status === 'draft'
                                                                        ? 'Brouillon'
                                                                        : 'Archivé'}
                                                            </span>
                                                        </div>

                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {slots.length} créneau(x) d'horaires · {t.programmeCount} programme(s) généré(s)
                                                        </p>

                                                        <p className="mt-2 text-xs font-semibold text-foreground">
                                                            {[
                                                                ...new Set(
                                                                    slots.map(
                                                                        (s) =>
                                                                            refs.lines.find(
                                                                                (l) =>
                                                                                    l.uuid ===
                                                                                    s.line_uuid,
                                                                            )?.label,
                                                                    ),
                                                                ),
                                                            ]
                                                                .filter(Boolean)
                                                                .join('  ·  ')}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Button variant="outline" asChild size="sm">
                                                        <Link href={templateRoutes.edit(t.uuid)}>
                                                            Modifier
                                                        </Link>
                                                    </Button>
                                                    <Button variant="ghost" asChild size="sm">
                                                        <Link
                                                            href={templateRoutes.create({
                                                                query: { source: t.uuid },
                                                            })}
                                                        >
                                                            Dupliquer
                                                        </Link>
                                                    </Button>
                                                    {t.status === 'available' && (
                                                        <PrimaryButton asChild size="sm" className="gap-1.5">
                                                            <Link
                                                                href={programmeRoutes.create({
                                                                    query: { template: t.uuid },
                                                                })}
                                                            >
                                                                <Sparkles size={14} />
                                                                Préparer une période
                                                            </Link>
                                                        </PrimaryButton>
                                                    )}
                                                    {t.status !== 'archived' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setArchive(t)}
                                                            className="text-muted-foreground hover:text-destructive"
                                                        >
                                                            Archiver
                                                        </Button>
                                                    )}
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            ) : (
                                <Empty title="Aucun modèle de départs">
                                    Créez votre première semaine type réutilisable pour automatiser la création de vos départs.
                                    <div className="pt-3">
                                        <PrimaryButton asChild>
                                            <Link href={templateRoutes.create()}>
                                                <Plus size={15} />
                                                Créer un modèle
                                            </Link>
                                        </PrimaryButton>
                                    </div>
                                </Empty>
                            )}
                        </Surface>
                    </div>
                )}

                {/* PROGRAMMES TAB */}
                {tab === 'programmes' && (
                    <div className="grid gap-5">
                        <div className="flex justify-end">
                            <PrimaryButton asChild className="gap-2 shadow-xs">
                                <Link href={programmeRoutes.create()}>
                                    <Plus size={15} />
                                    Nouveau programme de période
                                </Link>
                            </PrimaryButton>
                        </div>

                        <Surface>
                            {programmes.length ? (
                                <div className="grid gap-3">
                                    {programmes.map((p) => (
                                        <button
                                            key={p.uuid}
                                            onClick={() => setProgramme(p)}
                                            className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5 text-left transition-all hover:border-gold/60 hover:bg-muted/30"
                                        >
                                            <div>
                                                <h2 className="text-sm font-extrabold text-foreground">
                                                    {p.name}
                                                </h2>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Modèle : <strong className="text-foreground">{p.templateName}</strong> · Version {p.version}
                                                </p>
                                                <p className="mt-2 text-xs font-semibold text-kora dark:text-gold">
                                                    Du {dateLabel(p.startsOn)} au {dateLabel(p.endsOn)}
                                                </p>
                                            </div>

                                            <div className="text-right">
                                                <span
                                                    className={`rounded-md px-2 py-0.5 text-xs font-extrabold ${p.automatic
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                        : 'bg-muted text-muted-foreground'
                                                        }`}
                                                >
                                                    {p.automatic
                                                        ? `Auto · ${p.horizonDays} jours`
                                                        : 'Génération manuelle'}
                                                </span>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {p.prepared} en préparation ·{' '}
                                                    <strong className="text-emerald-600">
                                                        {p.published} publiés
                                                    </strong>
                                                </p>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <Empty title="Aucun programme de période">
                                    Appliquez un modèle sur un mois ou une période de votre choix pour alimenter le planning.
                                    <div className="pt-3">
                                        <PrimaryButton asChild>
                                            <Link href={programmeRoutes.create()}>
                                                <Plus size={15} />
                                                Préparer une période
                                            </Link>
                                        </PrimaryButton>
                                    </div>
                                </Empty>
                            )}
                        </Surface>
                    </div>
                )}

                {/* STICKY BULK ACTION BAR */}
                {selected.length > 0 && (
                    <div className="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-kora p-4 text-white shadow-xl ring-1 ring-white/10">
                        <div className="flex items-center gap-2">
                            <span className="grid size-6 place-items-center rounded-full bg-gold text-xs font-black text-kora">
                                {selected.length}
                            </span>
                            <span className="text-xs font-extrabold">
                                départ(s) sélectionné(s)
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setAction('assign')}
                                className="border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                            >
                                Affecter un bus
                            </Button>
                            <Button
                                variant="default"
                                onClick={() => setAction('publish')}
                            >
                                Publier pour la vente
                            </Button>
                            <Button
                                variant="outline"
                                disabled={!canCancel}
                                onClick={() => setAction('exclude')}
                                className="border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                            >
                                Exclure
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() => setSelected([])}
                                className="text-white/70 hover:bg-white/10 hover:text-white"
                            >
                                Désélectionner
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            {detail && (
                <ItemPanel
                    key={detail.uuid + detail.revision}
                    item={detail}
                    canCancel={canCancel}
                    refs={refs}
                    onClose={() => setDetail(null)}
                />
            )}

            {programme && (
                <ProgrammePanel
                    key={programme.uuid}
                    programme={
                        programmes.find((p) => p.uuid === programme.uuid) ??
                        programme
                    }
                    templates={templates}
                    runs={runs}
                    onClose={() => setProgramme(null)}
                />
            )}

            <Confirm
                open={action !== null}
                title={
                    action === 'publish'
                        ? 'Publication des départs sélectionnés'
                        : action === 'assign'
                            ? 'Affectation d’un bus à la sélection'
                            : 'Exclusion de dates'
                }
                busy={api.busy}
                onClose={() => setAction(null)}
                action={
                    <PrimaryButton
                        disabled={
                            api.busy ||
                            (action === 'publish' && ready === 0) ||
                            (action === 'assign' && !bus)
                        }
                        onClick={act}
                    >
                        {api.busy
                            ? 'Traitement en cours…'
                            : action === 'publish'
                                ? `Publier les ${ready} départ(s) prêt(s)`
                                : 'Confirmer'}
                    </PrimaryButton>
                }
            >
                <p className="text-xs text-muted-foreground leading-relaxed">
                    {selection.length} départs sélectionnés.{' '}
                    {action === 'publish'
                        ? 'Les départs sans blocage seront immédiatement ouverts à la réservation.'
                        : 'Confirmez l’opération pour mettre à jour les départs.'}
                </p>

                {action === 'assign' && (
                    <div className="pt-2">
                        <SelectField
                            label="Bus à affecter"
                            value={bus}
                            options={refs.buses
                                .filter((b) => b.status === 'active')
                                .map((b) => ({
                                    ...b,
                                    label: `${b.label} · ${b.capacity} places`,
                                }))}
                            onChange={setBus}
                        />
                    </div>
                )}

                <div className="max-h-60 overflow-y-auto space-y-1.5 pt-2">
                    {selection.map((i) => (
                        <div
                            key={i.uuid}
                            className="flex items-center justify-between border-b border-border/50 py-2 text-xs"
                        >
                            <span>
                                {dateLabel(i.date)} à <strong>{i.snapshot.time}</strong>
                            </span>
                            <StateBadge state={i.state} />
                        </div>
                    ))}
                </div>
            </Confirm>

            <Confirm
                open={archive !== null}
                title="Archiver ce modèle de départs ?"
                onClose={() => setArchive(null)}
                action={
                    <Button
                        variant="destructive"
                        onClick={() => {
                            if (archive) {
                                router.patch(
                                    templateRoutes.archive.url(archive.uuid),
                                    { operation: 'archive' },
                                    { onSuccess: () => setArchive(null) },
                                );
                            }
                        }}
                    >
                        Archiver le modèle
                    </Button>
                }
            >
                <p className="text-xs text-muted-foreground leading-relaxed">
                    Les programmes existants continueront de fonctionner. Ce modèle ne sera plus proposé pour de nouvelles périodes de planification.
                </p>
            </Confirm>
        </>
    );
}
