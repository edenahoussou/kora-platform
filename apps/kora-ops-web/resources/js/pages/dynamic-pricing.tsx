import { Form, Head, router } from '@inertiajs/react';
import {
    Archive,
    CheckCircle2,
    ChevronRight,
    CircleDashed,
    HelpCircle,
    History,
    Layers3,
    Plus,
    ShieldCheck,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { OnboardingTour } from '@/components/onboarding/onboarding-tour';
import type { OnboardingStep } from '@/components/onboarding/onboarding-tour';
import { PricingRuleBuilder } from '@/components/pricing/pricing-rule-builder';
import type { PricingRuleRecord } from '@/components/pricing/pricing-rule-types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOnboarding } from '@/hooks/use-onboarding';
import pricingRules from '@/routes/pricing-rules';
import type { BreadcrumbItem } from '@/types';

type LineOption = {
    uuid: string;
    code: string;
    name: string;
    stops: { uuid: string; name: string; stopOrder: number }[];
};

type PageProps = {
    rules: {
        data: PricingRuleRecord[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: Record<string, string>;
    scopes: Record<string, string>;
    lines: LineOption[];
    settings: {
        floorCoefficient: number | null;
        ceilingCoefficient: number | null;
        updatedAt: string | null;
    };
    permissions: {
        canCreate: boolean;
        canUpdate: boolean;
        canManageSettings: boolean;
    };
};

const statusLabels: Record<string, string> = {
    active: 'Active',
    draft: 'Brouillon',
    archived: 'Archivée',
};

const statusTones: Record<string, 'navy' | 'gold' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    archived: 'muted',
};

const typeLabels: Record<string, string> = {
    increase: 'Majoration',
    decrease: 'Réduction',
};

const scopeLabels: Record<string, string> = {
    network: 'Réseau',
    line: 'Ligne',
    segment: 'Segment',
};

const directionLabels: Record<string, string> = {
    outbound: 'Aller',
    return: 'Retour',
};

function ruleScopeLabel(rule: PricingRuleRecord): string {
    const direction =
        rule.direction !== 'both'
            ? ` · ${directionLabels[rule.direction] ?? rule.direction}`
            : '';

    if (rule.scope === 'network') {
        return 'Tout le réseau';
    }

    if (
        rule.scope === 'segment' &&
        rule.originStopName &&
        rule.destinationStopName
    ) {
        return `${rule.lineCode ?? ''} · ${rule.originStopName} → ${rule.destinationStopName}${direction}`;
    }

    return `${rule.lineCode ?? rule.lineName ?? 'Portée incomplète'}${direction}`;
}

function formatConditions(rule: PricingRuleRecord): string {
    const parts: string[] = [];
    const {
        fillRateMin,
        fillRateMax,
        hoursBeforeDepartureMin,
        hoursBeforeDepartureMax,
    } = rule.conditions;

    if (fillRateMin !== null || fillRateMax !== null) {
        parts.push(
            fillRateMin !== null && fillRateMax !== null
                ? `${fillRateMin}–${fillRateMax}% de remplissage`
                : fillRateMin !== null
                  ? `≥ ${fillRateMin}% de remplissage`
                  : `≤ ${fillRateMax}% de remplissage`,
        );
    }

    if (hoursBeforeDepartureMin !== null || hoursBeforeDepartureMax !== null) {
        parts.push(
            hoursBeforeDepartureMin !== null && hoursBeforeDepartureMax !== null
                ? `${hoursBeforeDepartureMin}–${hoursBeforeDepartureMax} h avant départ`
                : hoursBeforeDepartureMax !== null
                  ? `< ${hoursBeforeDepartureMax} h avant départ`
                  : `> ${hoursBeforeDepartureMin} h avant départ`,
        );
    }

    return parts.length > 0 ? parts.join(' · ') : 'Tous les contextes';
}

const ONBOARDING_STEPS: OnboardingStep[] = [
    {
        target: '[data-onboarding="stats"]',
        title: "Vue d'ensemble",
        body: "Suivez en un coup d'œil combien de règles sont actives, en brouillon ou archivées. C'est votre tableau de bord tarifaire.",
        side: 'bottom',
    },
    {
        target: '[data-onboarding="library"]',
        title: 'Bibliothèque de règles',
        body: 'Toutes vos règles apparaissent ici. Cliquez sur une règle pour la modifier, ou utilisez le bouton « Nouvelle règle » pour en créer une.',
        side: 'right',
    },
    {
        target: '[data-onboarding="builder"]',
        title: 'Constructeur de règle',
        body: "C'est ici que vous définissez la ligne ciblée, le type de variation (majoration ou réduction), le pourcentage et la priorité. La priorité détermine quelle règle gagne quand plusieurs correspondent.",
        side: 'left',
    },
    {
        target: '[data-onboarding="triggers"]',
        title: 'Déclencheurs',
        body: "Les déclencheurs définissent QUAND la règle s'applique : taux de remplissage du bus et délai avant départ. Les critères sont combinés en ET. Laissez un champ vide pour l'ignorer.",
        side: 'left',
    },
    {
        target: '[data-onboarding="guardrails"]',
        title: 'Garde-fou global',
        body: "Le plancher et le plafond limitent le coefficient final, quelle que soit la règle gagnante. C'est votre filet de sécurité contre les variations extrêmes.",
        side: 'left',
    },
    {
        target: '[data-onboarding="lifecycle"]',
        title: "Cycle de vie d'une règle",
        body: 'Une règle passe par trois états : brouillon, active, archivée. Seules les règles actives influencent les nouvelles recherches de prix. Activez une règle uniquement après avoir vérifié son impact.',
        side: 'left',
    },
];

export default function DynamicPricingPage({
    rules,
    statuses,
    scopes,
    lines,
    settings,
    permissions,
}: PageProps) {
    const [selected, setSelected] = useState<PricingRuleRecord | null>(
        rules.data[0] ?? null,
    );
    const [showSettings, setShowSettings] = useState(false);
    const onboarding = useOnboarding('dynamic-pricing');

    // Auto-start the tour on first visit, then hand control to the user.
    useEffect(() => {
        if (onboarding.shouldAutoStart) {
            const timer = setTimeout(() => onboarding.start(), 400);

            return () => clearTimeout(timer);
        }
    }, [onboarding.shouldAutoStart]); // eslint-disable-line react-hooks/exhaustive-deps

    const filters: FilterConfig[] = useMemo(
        () => [
            {
                name: 'status',
                label: 'Statut',
                placeholder: 'Tous les statuts',
                options: Object.entries(statuses).map(([value, label]) => ({
                    value,
                    label: statusLabels[value] ?? label,
                })),
            },
            {
                name: 'scope',
                label: 'Portée',
                placeholder: 'Toutes les portées',
                options: Object.entries(scopes).map(([value, label]) => ({
                    value,
                    label: scopeLabels[value] ?? label,
                })),
            },
            {
                name: 'line',
                label: 'Ligne',
                placeholder: 'Toutes les lignes',
                options: lines.map((line) => ({
                    value: line.uuid,
                    label: `${line.code} · ${line.name}`,
                })),
            },
        ],
        [lines, statuses, scopes],
    );

    const counts = useMemo(
        () => ({
            active: rules.data.filter((rule) => rule.status === 'active')
                .length,
            draft: rules.data.filter((rule) => rule.status === 'draft').length,
            archived: rules.data.filter((rule) => rule.status === 'archived')
                .length,
        }),
        [rules.data],
    );

    const changeStatus = (rule: PricingRuleRecord, status: string) => {
        router.patch(
            pricingRules.status.url({ pricingRule: rule.uuid }),
            { status },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Tarification dynamique" />
            <div className="space-y-6 pb-10">
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA OPS / Réseau commercial / Tarification
                            dynamique
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Tarification dynamique
                        </h1>
                        <p className="mt-1.5 max-w-3xl text-[0.8125rem] leading-6 text-muted-foreground">
                            Construisez des scénarios tarifaires lisibles,
                            mesurez leur impact et gardez le contrôle sur chaque
                            nouvelle recherche.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            size="kora"
                            variant="kora-secondary"
                            type="button"
                            onClick={onboarding.start}
                        >
                            <HelpCircle className="size-4" strokeWidth={1.8} />
                            Parcours guidé
                        </Button>
                        {permissions.canCreate && (
                            <Button
                                size="kora"
                                variant="kora"
                                type="button"
                                onClick={() => setSelected(null)}
                                className="group"
                            >
                                <Plus className="size-4" strokeWidth={1.8} />
                                Nouvelle règle
                            </Button>
                        )}
                    </div>
                </div>

                <div
                    data-onboarding="stats"
                    className="cash-reveal cash-reveal-delay-1 grid gap-3 sm:grid-cols-3"
                >
                    {[
                        {
                            label: 'Règles actives',
                            value: counts.active,
                            icon: ShieldCheck,
                            tone: 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/10',
                        },
                        {
                            label: 'À vérifier',
                            value: counts.draft,
                            icon: CircleDashed,
                            tone: 'text-kora bg-gold/12',
                        },
                        {
                            label: 'Versions archivées',
                            value: counts.archived,
                            icon: History,
                            tone: 'text-muted-foreground bg-muted',
                        },
                    ].map(({ label, value, icon: Icon, tone }) => (
                        <div
                            key={label}
                            className="flex items-center gap-3 rounded-[18px] bg-card p-4 shadow-sm ring-1 ring-border"
                        >
                            <span
                                className={`grid size-9 place-items-center rounded-[10px] ${tone}`}
                            >
                                <Icon className="size-4" strokeWidth={1.8} />
                            </span>
                            <div>
                                <p className="text-[0.68rem] text-muted-foreground">
                                    {label}
                                </p>
                                <p className="mt-0.5 text-xl font-extrabold tracking-[-0.03em] text-foreground">
                                    {value}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>

                <div className="cash-reveal cash-reveal-delay-2">
                    <KoraFilterBar
                        route={pricingRules.index.url()}
                        appearance="compact"
                        searchPlaceholder="Rechercher une ligne ou une règle…"
                        filters={filters}
                    />
                </div>

                <div className="cash-reveal cash-reveal-delay-3 grid items-start gap-6 lg:grid-cols-12">
                    <aside
                        data-onboarding="library"
                        className="space-y-4 lg:col-span-3"
                    >
                        <div className="flex items-center justify-between px-1">
                            <div>
                                <p className="section-kicker text-gold">
                                    Bibliothèque
                                </p>
                                <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                    Vos règles
                                </h2>
                            </div>
                            <span className="rounded-full bg-muted px-2.5 py-1 text-[0.65rem] font-bold text-muted-foreground">
                                {rules.total}
                            </span>
                        </div>
                        <div className="space-y-3">
                            {rules.data.map((rule) => {
                                const RuleIcon =
                                    rule.type === 'increase'
                                        ? TrendingUp
                                        : TrendingDown;
                                const active = selected?.uuid === rule.uuid;

                                return (
                                    <button
                                        key={rule.uuid}
                                        type="button"
                                        onClick={() => setSelected(rule)}
                                        className={`group w-full rounded-[18px] p-4 text-left ring-1 transition-[transform,box-shadow,background-color] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] ${active ? 'bg-gold/10 shadow-[0_8px_24px_rgba(246,176,0,0.1)] ring-gold/30' : 'bg-card ring-border hover:-translate-y-0.5 hover:shadow-[0_8px_22px_rgba(0,29,78,0.06)]'}`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <span
                                                className={`grid size-8 place-items-center rounded-[10px] ${rule.type === 'increase' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'bg-sky-500/10 text-sky-700 dark:text-sky-400'}`}
                                            >
                                                <RuleIcon
                                                    className="size-4"
                                                    strokeWidth={1.8}
                                                />
                                            </span>
                                            <KoraStatusBadge
                                                label={
                                                    statusLabels[rule.status] ??
                                                    rule.status
                                                }
                                                tone={
                                                    statusTones[rule.status] ??
                                                    'gold'
                                                }
                                            />
                                        </div>
                                        <div className="mt-3">
                                            <p className="text-[0.68rem] font-bold tracking-[0.04em] text-muted-foreground uppercase">
                                                {ruleScopeLabel(rule)}
                                            </p>
                                            <p className="mt-1 text-sm font-extrabold text-foreground">
                                                {typeLabels[rule.type]} de{' '}
                                                {rule.percentage} %
                                            </p>
                                            <p className="mt-1.5 line-clamp-2 text-[0.7rem] leading-relaxed text-muted-foreground">
                                                {formatConditions(rule)}
                                            </p>
                                        </div>
                                        <div className="mt-3 flex items-center justify-between border-t border-border/50 pt-2.5 text-[0.64rem] text-muted-foreground">
                                            <span>
                                                Priorité {rule.priority}
                                            </span>
                                            <ChevronRight
                                                className="size-3.5 transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:translate-x-1"
                                                strokeWidth={1.8}
                                            />
                                        </div>
                                    </button>
                                );
                            })}
                            {rules.data.length === 0 && (
                                <div className="rounded-[18px] bg-muted/30 p-6 text-center text-sm text-muted-foreground ring-1 ring-border">
                                    Aucune règle pour le moment.
                                </div>
                            )}
                        </div>
                    </aside>

                    <div data-onboarding="builder" className="lg:col-span-6">
                        <PricingRuleBuilder
                            key={selected?.uuid ?? 'new'}
                            rule={selected}
                            lines={lines}
                            canCreate={permissions.canCreate}
                            canUpdate={permissions.canUpdate}
                            onSaved={() => router.reload()}
                        />
                    </div>

                    <aside
                        data-onboarding="guardrails"
                        className="space-y-4 lg:col-span-3"
                    >
                        <div className="rounded-[18px] bg-card p-5 shadow-sm ring-1 ring-border">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="section-kicker text-gold">
                                        Garde-fou
                                    </p>
                                    <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em] text-foreground">
                                        Bornes globales
                                    </h2>
                                </div>
                                <Layers3
                                    className="size-5 text-gold"
                                    strokeWidth={1.8}
                                />
                            </div>
                            <p className="mt-3 text-[0.75rem] leading-relaxed text-muted-foreground">
                                Le coefficient final reste dans cette enveloppe,
                                quelle que soit la règle gagnante.
                            </p>
                            <div className="mt-4 grid grid-cols-2 gap-2">
                                <div className="rounded-[10px] bg-muted/50 p-3">
                                    <p className="text-[0.62rem] text-muted-foreground">
                                        Plancher
                                    </p>
                                    <p className="mt-1 text-lg font-extrabold text-foreground">
                                        {settings.floorCoefficient ?? '—'}
                                    </p>
                                </div>
                                <div className="rounded-[10px] bg-muted/50 p-3">
                                    <p className="text-[0.62rem] text-muted-foreground">
                                        Plafond
                                    </p>
                                    <p className="mt-1 text-lg font-extrabold text-foreground">
                                        {settings.ceilingCoefficient ?? '—'}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() =>
                                    setShowSettings((current) => !current)
                                }
                                className="mt-4 inline-flex items-center gap-1 text-xs font-bold text-digital transition-[color,transform] duration-300 hover:translate-x-0.5 hover:text-kora"
                            >
                                {showSettings
                                    ? 'Masquer les réglages'
                                    : 'Gérer les réglages'}{' '}
                                <ChevronRight
                                    className="size-3.5"
                                    strokeWidth={1.8}
                                />
                            </button>
                        </div>

                        {showSettings && permissions.canManageSettings && (
                            <div className="rounded-[18px] bg-card p-5 shadow-sm ring-1 ring-border">
                                <div className="mb-4 flex items-center gap-2">
                                    <Layers3
                                        className="size-4 text-gold"
                                        strokeWidth={1.8}
                                    />
                                    <h3 className="text-sm font-extrabold">
                                        Modifier les bornes
                                    </h3>
                                </div>
                                <Form
                                    {...pricingRules.settings.form()}
                                    options={{ preserveScroll: true }}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="floor-coefficient">
                                            Plancher
                                        </Label>
                                        <Input
                                            id="floor-coefficient"
                                            name="floor_coefficient"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="10"
                                            defaultValue={
                                                settings.floorCoefficient ?? ''
                                            }
                                            className="h-10 rounded-[10px] border-0 bg-muted ring-1 ring-border"
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="ceiling-coefficient">
                                            Plafond
                                        </Label>
                                        <Input
                                            id="ceiling-coefficient"
                                            name="ceiling_coefficient"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="10"
                                            defaultValue={
                                                settings.ceilingCoefficient ??
                                                ''
                                            }
                                            className="h-10 rounded-[10px] border-0 bg-muted ring-1 ring-border"
                                        />
                                    </div>
                                    <Button
                                        size="kora"
                                        variant="kora"
                                        type="submit"
                                        className="w-full"
                                    >
                                        Enregistrer
                                    </Button>
                                </Form>
                            </div>
                        )}

                        {selected && (
                            <div
                                data-onboarding="lifecycle"
                                className="rounded-[18px] bg-card p-5 shadow-sm ring-1 ring-border"
                            >
                                <div className="flex items-center gap-2">
                                    <ShieldCheck
                                        className="size-4 text-emerald-600"
                                        strokeWidth={1.8}
                                    />
                                    <h3 className="text-sm font-extrabold">
                                        Cycle de vie
                                    </h3>
                                </div>
                                <p className="mt-2 text-[0.75rem] leading-relaxed text-muted-foreground">
                                    {selected.status === 'active'
                                        ? 'Cette version influence les nouvelles demandes de prix.'
                                        : "Cette version ne modifie aucune vente tant qu'elle n'est pas active."}
                                </p>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {selected.status !== 'active' &&
                                        permissions.canUpdate && (
                                            <Button
                                                size="kora"
                                                variant="kora"
                                                type="button"
                                                onClick={() =>
                                                    changeStatus(
                                                        selected,
                                                        'active',
                                                    )
                                                }
                                                className="flex-1"
                                            >
                                                <CheckCircle2
                                                    className="size-4 shrink-0"
                                                    strokeWidth={1.8}
                                                />
                                                Activer
                                            </Button>
                                        )}
                                    {selected.status === 'active' &&
                                        permissions.canUpdate && (
                                            <Button
                                                size="kora"
                                                variant="kora-secondary"
                                                type="button"
                                                onClick={() =>
                                                    changeStatus(
                                                        selected,
                                                        'draft',
                                                    )
                                                }
                                                className="flex-1"
                                            >
                                                <CircleDashed
                                                    className="size-4 shrink-0"
                                                    strokeWidth={1.8}
                                                />
                                                Suspendre
                                            </Button>
                                        )}
                                    {selected.status !== 'archived' &&
                                        permissions.canUpdate && (
                                            <Button
                                                size="kora"
                                                variant="kora-secondary"
                                                type="button"
                                                onClick={() =>
                                                    changeStatus(
                                                        selected,
                                                        'archived',
                                                    )
                                                }
                                                className="flex-1"
                                            >
                                                <Archive
                                                    className="size-4 shrink-0"
                                                    strokeWidth={1.8}
                                                />
                                                Archiver
                                            </Button>
                                        )}
                                </div>
                            </div>
                        )}
                    </aside>
                </div>
            </div>

            <OnboardingTour
                steps={ONBOARDING_STEPS}
                isActive={onboarding.isActive}
                onComplete={onboarding.complete}
                onDismiss={onboarding.dismiss}
            />
        </>
    );
}

DynamicPricingPage.layout = {
    breadcrumbs: [
        {
            title: 'Réseau commercial',
            href: '/dynamic-pricing',
        } satisfies BreadcrumbItem,
        {
            title: 'Tarification dynamique',
            href: '/dynamic-pricing',
        } satisfies BreadcrumbItem,
    ],
};
