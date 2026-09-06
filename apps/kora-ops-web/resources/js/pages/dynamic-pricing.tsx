import { Form, Head, router } from '@inertiajs/react';
import {
    Archive,
    CheckCircle2,
    ChevronRight,
    CircleDashed,
    History,
    Layers3,
    Plus,
    ShieldCheck,
    Sparkles,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { KoraFilterBar } from '@/components/kora-filter-bar';
import type { FilterConfig } from '@/components/kora-filter-bar';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { PricingRuleBuilder } from '@/components/pricing/pricing-rule-builder';
import type { PricingRuleRecord } from '@/components/pricing/pricing-rule-types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import pricingRules from '@/routes/pricing-rules';
import type { BreadcrumbItem } from '@/types';

type LineOption = { uuid: string; code: string; name: string };

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

export default function DynamicPricingPage({
    rules,
    statuses,
    lines,
    settings,
    permissions,
}: PageProps) {
    const [selected, setSelected] = useState<PricingRuleRecord | null>(
        rules.data[0] ?? null,
    );
    const [showSettings, setShowSettings] = useState(false);

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
                name: 'line',
                label: 'Ligne',
                placeholder: 'Toutes les lignes',
                options: lines.map((line) => ({
                    value: line.uuid,
                    label: `${line.code} · ${line.name}`,
                })),
            },
        ],
        [lines, statuses],
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
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <span className="mb-3 inline-flex items-center gap-2 rounded-full bg-gold/12 px-3 py-1 text-[0.62rem] font-black tracking-[0.18em] text-[var(--kora)] uppercase">
                            <Sparkles className="size-3" strokeWidth={1.6} />
                            Réseau commercial · Bus
                        </span>
                        <h1 className="text-[2rem] font-extrabold tracking-[-0.055em] text-foreground sm:text-[2.35rem]">
                            Tarification dynamique
                        </h1>
                        <p className="mt-2 max-w-2xl text-[0.82rem] leading-relaxed text-muted-foreground">
                            Construisez des scénarios tarifaires lisibles,
                            mesurez leur impact et gardez le contrôle sur chaque
                            nouvelle recherche.
                        </p>
                    </div>
                    {permissions.canCreate && (
                        <Button
                            type="button"
                            onClick={() => setSelected(null)}
                            className="group h-12 justify-between rounded-full bg-gold px-3 pl-5 font-bold text-[var(--kora)] shadow-[0_10px_25px_rgba(246,176,0,0.2)] transition-[transform,background-color,box-shadow] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:-translate-y-0.5 hover:bg-[var(--kora)] hover:text-white active:scale-[0.98]"
                        >
                            <span className="flex items-center gap-2">
                                <Plus className="size-4" strokeWidth={1.6} />
                                Nouvelle règle
                            </span>
                            <span className="ml-4 grid size-8 place-items-center rounded-full bg-black/10 transition-[transform,background-color] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:translate-x-1 group-hover:bg-white/15">
                                <ChevronRight
                                    className="size-4"
                                    strokeWidth={1.6}
                                />
                            </span>
                        </Button>
                    )}
                </header>

                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        {
                            label: 'Règles actives',
                            value: counts.active,
                            icon: ShieldCheck,
                            tone: 'text-emerald-700 bg-emerald-500/10',
                        },
                        {
                            label: 'À vérifier',
                            value: counts.draft,
                            icon: CircleDashed,
                            tone: 'text-[var(--kora)] bg-gold/12',
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
                            className="rounded-[1.1rem] bg-muted/30 p-1 ring-1 ring-black/[0.04]"
                        >
                            <div className="flex items-center gap-3 rounded-[0.85rem] bg-card px-4 py-3.5 shadow-[inset_0_1px_1px_rgba(255,255,255,0.65)]">
                                <span
                                    className={`grid size-9 place-items-center rounded-[0.7rem] ${tone}`}
                                >
                                    <Icon
                                        className="size-4"
                                        strokeWidth={1.6}
                                    />
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
                        </div>
                    ))}
                </div>

                <KoraFilterBar
                    route="/dynamic-pricing"
                    searchPlaceholder="Rechercher une ligne ou une règle…"
                    filters={filters}
                />

                <div className="grid items-start gap-6 lg:grid-cols-12">
                    <aside className="space-y-4 lg:col-span-3">
                        <div className="flex items-center justify-between px-1">
                            <div>
                                <p className="text-[0.64rem] font-black tracking-[0.16em] text-muted-foreground uppercase">
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
                                        className={`group w-full rounded-[1.15rem] p-1 text-left ring-1 transition-[transform,box-shadow,background-color] duration-700 ease-[cubic-bezier(0.32,0.72,0,1)] ${active ? 'bg-gold/20 shadow-[0_14px_32px_rgba(246,176,0,0.12)] ring-gold/35' : 'bg-muted/25 ring-black/[0.04] hover:-translate-y-0.5 hover:bg-muted/45 hover:shadow-[0_12px_28px_rgba(0,29,78,0.07)]'}`}
                                    >
                                        <div className="rounded-[0.9rem] bg-card p-4 shadow-[inset_0_1px_1px_rgba(255,255,255,0.65)]">
                                            <div className="flex items-start justify-between gap-3">
                                                <span
                                                    className={`grid size-8 place-items-center rounded-[0.6rem] ${rule.type === 'increase' ? 'bg-amber-500/10 text-amber-700' : 'bg-sky-500/10 text-sky-700'}`}
                                                >
                                                    <RuleIcon
                                                        className="size-4"
                                                        strokeWidth={1.6}
                                                    />
                                                </span>
                                                <KoraStatusBadge
                                                    label={
                                                        statusLabels[
                                                            rule.status
                                                        ] ?? rule.status
                                                    }
                                                    tone={
                                                        statusTones[
                                                            rule.status
                                                        ] ?? 'gold'
                                                    }
                                                />
                                            </div>
                                            <div className="mt-4">
                                                <p className="text-[0.73rem] font-black tracking-[0.04em] text-muted-foreground uppercase">
                                                    {rule.lineCode ??
                                                        'Ligne à migrer'}
                                                </p>
                                                <p className="mt-1 text-sm font-extrabold text-foreground">
                                                    {typeLabels[rule.type]} de{' '}
                                                    {rule.percentage} %
                                                </p>
                                                <p className="mt-1.5 line-clamp-2 text-[0.68rem] leading-relaxed text-muted-foreground">
                                                    {formatConditions(rule)}
                                                </p>
                                            </div>
                                            <div className="mt-4 flex items-center justify-between border-t border-border/50 pt-3 text-[0.64rem] text-muted-foreground">
                                                <span>
                                                    Priorité {rule.priority}
                                                </span>
                                                <ChevronRight
                                                    className="size-3.5 transition-transform duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] group-hover:translate-x-1"
                                                    strokeWidth={1.6}
                                                />
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                            {rules.data.length === 0 && (
                                <div className="rounded-[1.15rem] bg-muted/25 p-6 text-center text-sm text-muted-foreground ring-1 ring-black/[0.04]">
                                    Aucune règle pour le moment.
                                </div>
                            )}
                        </div>
                    </aside>

                    <div className="lg:col-span-6">
                        <PricingRuleBuilder
                            key={selected?.uuid ?? 'new'}
                            rule={selected}
                            lines={lines}
                            canCreate={permissions.canCreate}
                            canUpdate={permissions.canUpdate}
                            onSaved={() => router.reload()}
                        />
                    </div>

                    <aside className="space-y-4 lg:col-span-3">
                        <div className="rounded-[1.55rem] bg-[var(--kora)] p-1.5 text-white shadow-[0_18px_45px_rgba(0,29,78,0.18)] ring-1 ring-black/[0.08]">
                            <div className="rounded-[1.25rem] bg-white/[0.07] p-5 shadow-[inset_0_1px_1px_rgba(255,255,255,0.14)]">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <span className="text-[0.62rem] font-black tracking-[0.16em] text-white/50 uppercase">
                                            Garde-fou
                                        </span>
                                        <h2 className="mt-1 text-lg font-extrabold tracking-[-0.03em]">
                                            Bornes globales
                                        </h2>
                                    </div>
                                    <Layers3
                                        className="size-5 text-gold"
                                        strokeWidth={1.6}
                                    />
                                </div>
                                <p className="mt-3 text-[0.72rem] leading-relaxed text-white/60">
                                    Le coefficient final reste dans cette
                                    enveloppe, quelle que soit la règle
                                    gagnante.
                                </p>
                                <div className="mt-5 grid grid-cols-2 gap-2">
                                    <div className="rounded-[0.8rem] bg-white/[0.08] p-3">
                                        <p className="text-[0.6rem] text-white/45">
                                            Plancher
                                        </p>
                                        <p className="mt-1 text-lg font-extrabold">
                                            {settings.floorCoefficient ?? '—'}
                                        </p>
                                    </div>
                                    <div className="rounded-[0.8rem] bg-white/[0.08] p-3">
                                        <p className="text-[0.6rem] text-white/45">
                                            Plafond
                                        </p>
                                        <p className="mt-1 text-lg font-extrabold">
                                            {settings.ceilingCoefficient ?? '—'}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowSettings((current) => !current)
                                    }
                                    className="mt-4 text-xs font-bold text-gold transition-[color,transform] duration-500 ease-[cubic-bezier(0.32,0.72,0,1)] hover:translate-x-0.5 hover:text-white"
                                >
                                    {showSettings
                                        ? 'Masquer les réglages'
                                        : 'Gérer les réglages'}{' '}
                                    <span aria-hidden="true">→</span>
                                </button>
                            </div>
                        </div>

                        {showSettings && permissions.canManageSettings && (
                            <div className="rounded-[1.25rem] bg-card p-5 shadow-[0_12px_30px_rgba(0,29,78,0.06)] ring-1 ring-black/[0.05]">
                                <div className="mb-4 flex items-center gap-2">
                                    <Layers3
                                        className="size-4 text-gold"
                                        strokeWidth={1.6}
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
                                            className="h-10 rounded-[0.75rem] border-0 bg-muted ring-1 ring-black/[0.05]"
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
                                            className="h-10 rounded-[0.75rem] border-0 bg-muted ring-1 ring-black/[0.05]"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        className="w-full rounded-full bg-gold font-bold text-[var(--kora)]"
                                    >
                                        Enregistrer
                                    </Button>
                                </Form>
                            </div>
                        )}

                        {selected && (
                            <div className="rounded-[1.25rem] bg-card p-5 shadow-[0_12px_30px_rgba(0,29,78,0.06)] ring-1 ring-black/[0.05]">
                                <div className="flex items-center gap-2">
                                    <ShieldCheck
                                        className="size-4 text-emerald-600"
                                        strokeWidth={1.6}
                                    />
                                    <h3 className="text-sm font-extrabold">
                                        Cycle de vie
                                    </h3>
                                </div>
                                <p className="mt-2 text-[0.72rem] leading-relaxed text-muted-foreground">
                                    {selected.status === 'active'
                                        ? 'Cette version influence les nouvelles demandes de prix.'
                                        : 'Cette version ne modifie aucune vente tant qu’elle n’est pas active.'}
                                </p>
                                <div className="mt-4 grid gap-2">
                                    {selected.status !== 'active' &&
                                        permissions.canUpdate && (
                                            <Button
                                                type="button"
                                                onClick={() =>
                                                    changeStatus(
                                                        selected,
                                                        'active',
                                                    )
                                                }
                                                className="h-10 rounded-full bg-[var(--kora)] text-xs font-bold text-white hover:bg-[var(--digital)]"
                                            >
                                                <CheckCircle2
                                                    className="size-4"
                                                    strokeWidth={1.6}
                                                />
                                                Activer après vérification
                                            </Button>
                                        )}
                                    {selected.status === 'active' &&
                                        permissions.canUpdate && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() =>
                                                    changeStatus(
                                                        selected,
                                                        'draft',
                                                    )
                                                }
                                                className="h-10 rounded-full text-xs font-bold"
                                            >
                                                <CircleDashed
                                                    className="size-4"
                                                    strokeWidth={1.6}
                                                />
                                                Suspendre
                                            </Button>
                                        )}
                                    {selected.status !== 'archived' &&
                                        permissions.canUpdate && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={() =>
                                                    changeStatus(
                                                        selected,
                                                        'archived',
                                                    )
                                                }
                                                className="h-10 rounded-full text-xs font-bold text-muted-foreground"
                                            >
                                                <Archive
                                                    className="size-4"
                                                    strokeWidth={1.6}
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
