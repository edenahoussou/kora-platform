import { Head, router, usePage } from '@inertiajs/react';
import {
    Archive,
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    CircleDashed,
    Edit3,
    Eye,
    HelpCircle,
    PauseCircle,
    PlayCircle,
    Plus,
    Search,
    SlidersHorizontal,
    Tag,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { OnboardingTour } from '@/components/onboarding/onboarding-tour';
import type { OnboardingStep } from '@/components/onboarding/onboarding-tour';
import { PromotionDetailDrawer } from '@/components/promotions/promotion-detail-drawer';
import { PromotionFormModal } from '@/components/promotions/promotion-form-modal';
import type {
    PromotionDetail,
    PromotionLineOption,
    PromotionRecord,
} from '@/components/promotions/promotion-types';
import {
    formatBenefit,
    formatPeriod,
} from '@/components/promotions/promotion-types';
import { useOnboarding } from '@/hooks/use-onboarding';
import { cn } from '@/lib/utils';
import promotions from '@/routes/promotions';
import type { BreadcrumbItem } from '@/types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type Props = {
    promotions: Paginated<PromotionRecord>;
    statuses: Record<string, string>;
    scopes: Record<string, string>;
    benefitTypes: Record<string, string>;
    channels: Record<string, string>;
    lines: PromotionLineOption[];
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
    };
    promotionDetail?: PromotionDetail | null;
};

type StatusTone = 'navy' | 'gold' | 'blue' | 'muted';

const displayStatusLabels: Record<string, string> = {
    draft: 'Brouillon',
    active: 'Active',
    inactive: 'Inactive',
    archived: 'Archivée',
    scheduled: 'Planifiée',
    expired: 'Expirée',
};

const displayStatusTones: Record<string, StatusTone> = {
    draft: 'muted',
    active: 'navy',
    inactive: 'gold',
    archived: 'muted',
    scheduled: 'blue',
    expired: 'muted',
};

const ONBOARDING_STEPS: OnboardingStep[] = [
    {
        target: '[data-onboarding="promotions-heading"]',
        title: 'Vos campagnes promotionnelles',
        body: 'Créez des codes promo ciblés : réseau, canal, ligne ou segment. Le code est saisi au moment du devis — jamais rétroactivement.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="promotions-filters"]',
        title: 'Retrouvez une promotion',
        body: 'Recherchez par nom ou code, puis filtrez par statut, périmètre ou ligne.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="promotions-table"]',
        title: 'Pilotez le cycle de vie',
        body: 'Activez, suspendez ou archivez chaque promotion. Les conflits de période et de priorité sont signalés avant enregistrement.',
        side: 'top',
    },
];

function scopeLabel(
    promotion: PromotionRecord,
    scopes: Record<string, string>,
    channels: Record<string, string>,
): string {
    switch (promotion.scopeType) {
        case 'line':
            return promotion.line
                ? `Ligne ${promotion.line.code}`
                : 'Ligne supprimée';
        case 'segment':
            return promotion.line
                ? `${promotion.line.code} · ${promotion.originStopName ?? '?'} → ${promotion.destinationStopName ?? '?'}`
                : 'Segment supprimé';
        case 'channel':
            return channels[promotion.channel ?? ''] ?? 'Canal';
        default:
            return scopes[promotion.scopeType] ?? promotion.scopeType;
    }
}

function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone,
}: {
    icon: React.ComponentType<{
        className?: string;
        strokeWidth?: number;
    }>;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted' | 'green';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
        green: 'bg-emerald-500/10 text-emerald-600',
    };

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', toneStyles[tone])}>
                        <Icon className="size-4" strokeWidth={1.7} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Valeur</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

export default function PromotionsPage({
    promotions: paginatedPromotions,
    statuses,
    scopes,
    benefitTypes,
    channels,
    lines,
    permissions,
    promotionDetail,
}: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingPromotion, setEditingPromotion] =
        useState<PromotionRecord | null>(null);
    const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const onboarding = useOnboarding('promotions');
    const { shouldAutoStart, start } = onboarding;

    const { url: currentUrl } = usePage();
    const urlParams = useMemo(() => {
        const queryString = currentUrl.includes('?')
            ? currentUrl.slice(currentUrl.indexOf('?') + 1)
            : '';

        return new URLSearchParams(queryString);
    }, [currentUrl]);

    const [query, setQuery] = useState(urlParams.get('q') ?? '');
    const [status, setStatus] = useState(urlParams.get('status') ?? 'all');
    const [scope, setScope] = useState(urlParams.get('scope') ?? 'all');
    const [line, setLine] = useState(urlParams.get('line') ?? 'all');

    useEffect(() => {
        if (!shouldAutoStart) {
            return;
        }

        const timer = window.setTimeout(() => start(), 450);

        return () => window.clearTimeout(timer);
    }, [shouldAutoStart, start]);

    const counts = useMemo(() => {
        const data = paginatedPromotions.data;

        return {
            active: data.filter(
                (promotion) => promotion.displayStatus === 'active',
            ).length,
            scheduled: data.filter(
                (promotion) => promotion.displayStatus === 'scheduled',
            ).length,
            draft: data.filter((promotion) => promotion.status === 'draft')
                .length,
            redemptions: data.reduce(
                (total, promotion) => total + promotion.redemptionsCount,
                0,
            ),
        };
    }, [paginatedPromotions.data]);

    const selectedPromotion = useMemo(
        () =>
            paginatedPromotions.data.find(
                (promotion) => promotion.uuid === selectedUuid,
            ) ??
            promotionDetail?.promotion ??
            null,
        [paginatedPromotions.data, selectedUuid, promotionDetail],
    );

    const buildParams = (
        overrides: {
            q?: string;
            status?: string;
            scope?: string;
            line?: string;
            page?: number;
        } = {},
    ): Record<string, string> => {
        const nextQuery = overrides.q ?? query;
        const nextStatus = overrides.status ?? status;
        const nextScope = overrides.scope ?? scope;
        const nextLine = overrides.line ?? line;
        const params: Record<string, string> = {};

        if (nextQuery.trim() !== '') {
            params.q = nextQuery.trim();
        }

        if (nextStatus !== 'all') {
            params.status = nextStatus;
        }

        if (nextScope !== 'all') {
            params.scope = nextScope;
        }

        if (nextLine !== 'all') {
            params.line = nextLine;
        }

        if (overrides.page !== undefined && overrides.page > 1) {
            params.page = String(overrides.page);
        }

        return params;
    };

    const visit = (overrides: Parameters<typeof buildParams>[0] = {}) => {
        router.get(promotions.index.url(), buildParams(overrides), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const applyFilters = () => visit({ page: 1 });

    const resetFilters = () => {
        setQuery('');
        setStatus('all');
        setScope('all');
        setLine('all');
        router.get(
            promotions.index.url(),
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditingPromotion(null);
        setModalOpen(true);
    };

    const openEdit = (promotion: PromotionRecord) => {
        setEditingPromotion(promotion);
        setModalOpen(true);
    };

    const openDetail = (promotion: PromotionRecord) => {
        setSelectedUuid(promotion.uuid);
        setDetailLoading(true);
        router.reload({
            only: ['promotionDetail'],
            data: { promotion: promotion.uuid },
            onFinish: () => setDetailLoading(false),
        });
    };

    const changeStatus = (
        promotion: PromotionRecord,
        nextStatus: 'active' | 'inactive' | 'archived',
    ) => {
        const messages: Record<string, string> = {
            active: `Activer ${promotion.name} ? Le code deviendra utilisable selon sa période.`,
            inactive: `Suspendre ${promotion.name} ? Le code ne sera plus applicable aux nouvelles ventes.`,
            archived: `Archiver ${promotion.name} ? Cette action est définitive — la promotion ne pourra plus être modifiée.`,
        };

        if (!window.confirm(messages[nextStatus])) {
            return;
        }

        router.patch(
            promotions.status.url({ promotion: promotion.uuid }),
            { status: nextStatus },
            { preserveScroll: true },
        );
    };

    const filterSelect = (
        label: string,
        value: string,
        onChange: (value: string) => void,
        options: [string, string][],
    ) => (
        <label className="cash-filter-field relative block">
            <span className="sr-only">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="cash-filter-select h-11 w-full appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-[box-shadow,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
            >
                {options.map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>
                        {optionLabel}
                    </option>
                ))}
            </select>
            <ChevronRight
                className="pointer-events-none absolute top-1/2 right-3 size-3.5 -translate-y-1/2 rotate-90 text-muted-foreground"
                strokeWidth={1.7}
            />
        </label>
    );

    return (
        <>
            <Head title="Promotions" />
            <div className="cash-page">
                <div
                    data-onboarding="promotions-heading"
                    className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Réseau commercial / Promotions
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Promotions
                        </h1>
                        <p className="mt-1.5 max-w-3xl text-[0.8125rem] leading-6 text-muted-foreground">
                            Codes promotionnels appliqués aux nouvelles ventes :
                            campagnes ciblées par canal, ligne ou segment,
                            jamais rétroactives.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            className="btn btn-ghost"
                            onClick={onboarding.reset}
                            aria-label="Revoir le parcours"
                        >
                            <HelpCircle className="size-4" /> Parcours
                        </button>
                        {permissions.canCreate && (
                            <button
                                type="button"
                                data-onboarding="promotions-add"
                                className="btn btn-primary"
                                onClick={openCreate}
                            >
                                <Plus className="size-4" /> Nouvelle promotion
                            </button>
                        )}
                    </div>
                </div>

                <section className="cash-ledger-grid companies-ledger-grid cash-reveal cash-reveal-delay-1">
                    <KpiCard
                        icon={CheckCircle2}
                        label="Actives"
                        hint="applicables aux ventes"
                        value={counts.active}
                        tone="green"
                    />
                    <KpiCard
                        icon={CalendarClock}
                        label="Planifiées"
                        hint="période future"
                        value={counts.scheduled}
                        tone="blue"
                    />
                    <KpiCard
                        icon={CircleDashed}
                        label="Brouillons"
                        hint="à finaliser"
                        value={counts.draft}
                        tone="gold"
                    />
                    <KpiCard
                        icon={Tag}
                        label="Utilisations"
                        hint="ventes avec remise (page)"
                        value={counts.redemptions}
                        tone="navy"
                    />
                </section>

                <div className="cash-reveal cash-reveal-delay-2 cash-section-intro">
                    <div>
                        <span className="section-kicker">Catalogue</span>
                        <h2>Liste des promotions</h2>
                    </div>
                </div>

                <div
                    data-onboarding="promotions-filters"
                    className="cash-filterbar cash-reveal cash-reveal-delay-2"
                >
                    <Search className="cash-filter-icon size-4" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                applyFilters();
                            }
                        }}
                        aria-label="Rechercher une promotion"
                        placeholder="Rechercher par nom ou code…"
                        className="cash-filter-search"
                    />
                    {filterSelect(
                        'Statut',
                        status,
                        (value) => {
                            setStatus(value);
                            visit({ status: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les statuts'],
                            ...Object.entries(statuses).map(
                                ([value, label]) =>
                                    [value, label] as [string, string],
                            ),
                        ],
                    )}
                    {filterSelect(
                        'Périmètre',
                        scope,
                        (value) => {
                            setScope(value);
                            visit({ scope: value, page: 1 });
                        },
                        [
                            ['all', 'Tous les périmètres'],
                            ...Object.entries(scopes).map(
                                ([value, label]) =>
                                    [value, label] as [string, string],
                            ),
                        ],
                    )}
                    {filterSelect(
                        'Ligne',
                        line,
                        (value) => {
                            setLine(value);
                            visit({ line: value, page: 1 });
                        },
                        [
                            ['all', 'Toutes les lignes'],
                            ...lines.map(
                                (lineOption) =>
                                    [
                                        lineOption.uuid,
                                        `${lineOption.code} · ${lineOption.name}`,
                                    ] as [string, string],
                            ),
                        ],
                    )}
                    <button
                        type="button"
                        className="btn btn-navy cash-filter-submit"
                        onClick={applyFilters}
                    >
                        <SlidersHorizontal className="size-4" /> Filtrer
                    </button>
                    <button
                        type="button"
                        className="btn btn-ghost cash-filter-reset"
                        onClick={resetFilters}
                    >
                        Réinitialiser
                    </button>
                </div>

                <section
                    data-onboarding="promotions-table"
                    className="cash-table-card cash-reveal cash-reveal-delay-3"
                >
                    {paginatedPromotions.data.length === 0 ? (
                        <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                            Aucune promotion trouvée. Ajustez votre recherche ou
                            créez la première campagne promotionnelle.
                        </p>
                    ) : (
                        <>
                            <div className="cash-table-scroll overflow-x-auto">
                                <table className="w-full min-w-[1100px] text-left">
                                    <thead className="bg-muted/45 text-[0.625rem] font-black tracking-[0.14em] text-muted-foreground uppercase">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Promotion
                                            </th>
                                            <th className="px-4 py-3">Code</th>
                                            <th className="px-4 py-3">
                                                Périmètre
                                            </th>
                                            <th className="px-4 py-3">
                                                Avantage
                                            </th>
                                            <th className="px-4 py-3">
                                                Période
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                Priorité
                                            </th>
                                            <th className="px-4 py-3 text-center">
                                                Utilisations
                                            </th>
                                            <th className="px-4 py-3">
                                                Statut
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {paginatedPromotions.data.map(
                                            (promotion) => (
                                                <tr
                                                    key={promotion.uuid}
                                                    className="group cursor-pointer border-b border-black/[0.045] transition-[background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:bg-muted/30"
                                                    onClick={() =>
                                                        openDetail(promotion)
                                                    }
                                                >
                                                    <td className="px-4 py-3.5">
                                                        <p className="text-xs font-extrabold text-foreground">
                                                            {promotion.name}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <span className="rounded-[7px] bg-muted/60 px-2 py-1 font-mono text-[0.65rem] font-black tracking-[0.06em] text-foreground">
                                                            {promotion.code}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3.5 text-xs text-muted-foreground">
                                                        {scopeLabel(
                                                            promotion,
                                                            scopes,
                                                            channels,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-xs font-bold text-foreground">
                                                        {formatBenefit(
                                                            promotion,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-[0.68rem] whitespace-nowrap text-muted-foreground">
                                                        {formatPeriod(
                                                            promotion,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-center text-xs font-bold text-foreground">
                                                        {promotion.priority}
                                                    </td>
                                                    <td className="px-4 py-3.5 text-center text-xs font-bold text-foreground">
                                                        {promotion.usedCount}
                                                        {promotion.maxUses !==
                                                            null &&
                                                            `/${promotion.maxUses}`}
                                                    </td>
                                                    <td className="px-4 py-3.5">
                                                        <KoraStatusBadge
                                                            label={
                                                                displayStatusLabels[
                                                                    promotion
                                                                        .displayStatus
                                                                ] ??
                                                                promotion.displayStatus
                                                            }
                                                            tone={
                                                                displayStatusTones[
                                                                    promotion
                                                                        .displayStatus
                                                                ] ?? 'muted'
                                                            }
                                                        />
                                                    </td>
                                                    <td
                                                        className="px-4 py-3.5"
                                                        onClick={(event) =>
                                                            event.stopPropagation()
                                                        }
                                                    >
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            <button
                                                                type="button"
                                                                className="grid size-8 place-items-center rounded-[9px] bg-muted/60 text-muted-foreground transition-colors duration-300 hover:bg-muted hover:text-foreground"
                                                                onClick={() =>
                                                                    openDetail(
                                                                        promotion,
                                                                    )
                                                                }
                                                                aria-label={`Voir ${promotion.name}`}
                                                                title="Détail"
                                                            >
                                                                <Eye className="size-3.5" />
                                                            </button>
                                                            {permissions.canUpdate &&
                                                                promotion.status !==
                                                                    'archived' && (
                                                                    <button
                                                                        type="button"
                                                                        className="grid size-8 place-items-center rounded-[9px] bg-muted/60 text-muted-foreground transition-colors duration-300 hover:bg-muted hover:text-foreground"
                                                                        onClick={() =>
                                                                            openEdit(
                                                                                promotion,
                                                                            )
                                                                        }
                                                                        aria-label={`Modifier ${promotion.name}`}
                                                                        title="Modifier"
                                                                    >
                                                                        <Edit3 className="size-3.5" />
                                                                    </button>
                                                                )}
                                                            {permissions.canUpdate &&
                                                                (promotion.status ===
                                                                    'draft' ||
                                                                    promotion.status ===
                                                                        'inactive') && (
                                                                    <button
                                                                        type="button"
                                                                        className="grid size-8 place-items-center rounded-[9px] bg-emerald-500/10 text-emerald-600 transition-colors duration-300 hover:bg-emerald-500/20"
                                                                        onClick={() =>
                                                                            changeStatus(
                                                                                promotion,
                                                                                'active',
                                                                            )
                                                                        }
                                                                        aria-label={`Activer ${promotion.name}`}
                                                                        title="Activer"
                                                                    >
                                                                        <PlayCircle className="size-3.5" />
                                                                    </button>
                                                                )}
                                                            {permissions.canUpdate &&
                                                                promotion.status ===
                                                                    'active' && (
                                                                    <button
                                                                        type="button"
                                                                        className="grid size-8 place-items-center rounded-[9px] bg-gold/12 text-gold transition-colors duration-300 hover:bg-gold/20"
                                                                        onClick={() =>
                                                                            changeStatus(
                                                                                promotion,
                                                                                'inactive',
                                                                            )
                                                                        }
                                                                        aria-label={`Suspendre ${promotion.name}`}
                                                                        title="Suspendre"
                                                                    >
                                                                        <PauseCircle className="size-3.5" />
                                                                    </button>
                                                                )}
                                                            {permissions.canUpdate &&
                                                                promotion.status !==
                                                                    'archived' && (
                                                                    <button
                                                                        type="button"
                                                                        className="grid size-8 place-items-center rounded-[9px] bg-rose-500/10 text-rose-600 transition-colors duration-300 hover:bg-rose-500/20"
                                                                        onClick={() =>
                                                                            changeStatus(
                                                                                promotion,
                                                                                'archived',
                                                                            )
                                                                        }
                                                                        aria-label={`Archiver ${promotion.name}`}
                                                                        title="Archiver"
                                                                    >
                                                                        <Archive className="size-3.5" />
                                                                    </button>
                                                                )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {paginatedPromotions.total > 0 && (
                                <div className="flex items-center justify-between border-t border-black/[0.045] px-4 py-3 text-xs text-muted-foreground">
                                    <span>
                                        {paginatedPromotions.from}–
                                        {paginatedPromotions.to} sur{' '}
                                        {paginatedPromotions.total}
                                    </span>
                                    <div className="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                            onClick={() =>
                                                visit({
                                                    page:
                                                        paginatedPromotions.current_page -
                                                        1,
                                                })
                                            }
                                            disabled={
                                                paginatedPromotions.current_page <=
                                                1
                                            }
                                            aria-label="Page précédente"
                                        >
                                            <ChevronRight
                                                className="size-3.5 rotate-180"
                                                strokeWidth={1.7}
                                            />
                                        </button>
                                        <span className="px-2 font-bold text-foreground">
                                            {paginatedPromotions.current_page} /{' '}
                                            {paginatedPromotions.last_page}
                                        </span>
                                        <button
                                            type="button"
                                            className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-[transform,opacity,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:translate-x-0.5 hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                            onClick={() =>
                                                visit({
                                                    page:
                                                        paginatedPromotions.current_page +
                                                        1,
                                                })
                                            }
                                            disabled={
                                                paginatedPromotions.current_page >=
                                                paginatedPromotions.last_page
                                            }
                                            aria-label="Page suivante"
                                        >
                                            <ChevronRight
                                                className="size-3.5"
                                                strokeWidth={1.7}
                                            />
                                        </button>
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </section>
            </div>

            <PromotionFormModal
                open={modalOpen}
                promotion={editingPromotion}
                lines={lines}
                scopes={scopes}
                benefitTypes={benefitTypes}
                channels={channels}
                onOpenChange={setModalOpen}
            />

            <PromotionDetailDrawer
                open={selectedUuid !== null}
                promotion={selectedPromotion}
                detail={promotionDetail ?? null}
                loading={detailLoading}
                statusLabels={displayStatusLabels}
                statusTones={displayStatusTones}
                scopeLabels={scopes}
                channelLabels={channels}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedUuid(null);
                    }
                }}
            />

            <OnboardingTour
                steps={ONBOARDING_STEPS}
                isActive={onboarding.isActive}
                onComplete={onboarding.complete}
                onDismiss={onboarding.dismiss}
            />
        </>
    );
}

PromotionsPage.layout = {
    breadcrumbs: [
        { title: 'Promotions', href: '/promotions' } satisfies BreadcrumbItem,
    ],
};
