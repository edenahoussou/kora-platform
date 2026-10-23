import { Head, router } from '@inertiajs/react';
import {
    Archive,
    Armchair,
    BusFront,
    CircleHelp,
    Copy,
    Edit3,
    Grid3X3,
    Layers,
    Lock,
    MoreHorizontal,
    Plus,
    RotateCcw,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { OnboardingTour } from '@/components/onboarding/onboarding-tour';
import type { OnboardingStep } from '@/components/onboarding/onboarding-tour';
import { seatStats } from '@/components/seat-map/layout-utils';
import { SeatMap } from '@/components/seat-map/seat-map';
import type { SeatTemplate } from '@/components/seat-map/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useOnboarding } from '@/hooks/use-onboarding';
import { cn } from '@/lib/utils';
import layouts from '@/routes/layouts';
import type { BreadcrumbItem } from '@/types';
import { mockTemplates, templateStatusLabels } from './mock-data';

const ONBOARDING_STEPS: OnboardingStep[] = [
    {
        target: '[data-onboarding="layouts-heading"]',
        title: 'Le référentiel des cabines',
        body: 'Chaque gabarit décrit la composition d’un bus : sièges vendables, allées, portes, poste conducteur. Une fois enregistré, il se réutilise sur tout le parc.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="layouts-new"]',
        title: 'Créez votre premier gabarit',
        body: 'Cliquez ici pour ouvrir l’éditeur de dessin — une visite guidée vous accompagne pas à pas jusqu’à l’enregistrement.',
        side: 'bottom',
    },
    {
        target: '[data-onboarding="layouts-cards"]',
        title: 'Vos gabarits en un coup d’œil',
        body: 'Chaque carte montre le plan, la capacité vendable et les bus équipés. Un gabarit déjà utilisé est verrouillé : dupliquez-le pour le faire évoluer.',
        side: 'top',
    },
];

const statusTones: Record<SeatTemplate['status'], 'navy' | 'gold' | 'muted'> = {
    active: 'navy',
    draft: 'gold',
    archived: 'muted',
};

function KpiCard({
    icon: Icon,
    label,
    hint,
    value,
    tone = 'navy',
}: {
    icon: React.ComponentType<{ className?: string; strokeWidth?: number }>;
    label: string;
    hint: string;
    value: string | number;
    tone: 'navy' | 'gold' | 'blue' | 'muted';
}) {
    const toneStyles = {
        navy: 'bg-kora text-white',
        gold: 'bg-gold/15 text-gold',
        blue: 'bg-digital/10 text-digital',
        muted: 'bg-muted text-muted-foreground',
    };

    return (
        <article className="cash-ledger-card cash-ledger-card-cash">
            <div className="cash-ledger-head">
                <div className="cash-ledger-identity">
                    <span className={cn('cash-ledger-brand', toneStyles[tone])}>
                        <Icon className="size-4" strokeWidth={1.8} />
                    </span>
                    <div>
                        <span className="cash-ledger-title">{label}</span>
                        <small>{hint}</small>
                    </div>
                </div>
            </div>
            <div className="cash-ledger-balance">
                <span>Total</span>
                <strong>{value}</strong>
            </div>
        </article>
    );
}

export default function LayoutsIndexPage() {
    const [templates, setTemplates] = useState<SeatTemplate[]>(mockTemplates);
    const onboarding = useOnboarding('layouts');
    const { shouldAutoStart, start } = onboarding;

    useEffect(() => {
        if (!shouldAutoStart) {
            return;
        }

        const timer = window.setTimeout(() => start(), 450);

        return () => window.clearTimeout(timer);
    }, [shouldAutoStart, start]);

    const stats = useMemo(() => {
        const active = templates.filter((t) => t.status === 'active');
        const drafts = templates.filter((t) => t.status === 'draft').length;

        return {
            total: templates.length,
            active: active.length,
            drafts,
            busesEquipped: templates.reduce((acc, t) => acc + t.busesCount, 0),
            sellableSeats: active.reduce(
                (acc, t) => acc + seatStats(t.grid).sellable,
                0,
            ),
        };
    }, [templates]);

    const archive = (template: SeatTemplate) => {
        setTemplates((prev) =>
            prev.map((t) =>
                t.uuid === template.uuid
                    ? {
                          ...t,
                          status:
                              t.status === 'archived' ? 'draft' : 'archived',
                      }
                    : t,
            ),
        );
        toast.success(
            template.status === 'archived'
                ? `« ${template.name} » restauré (maquette).`
                : `« ${template.name} » archivé (maquette).`,
        );
    };

    const duplicate = (template: SeatTemplate) => {
        router.get(layouts.create.url({ query: { duplicate: template.uuid } }));
    };

    return (
        <>
            <Head title="Gabarits de sièges" />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div data-onboarding="layouts-heading">
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Ressources / Gabarits
                    </div>
                    <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                        Gabarits de sièges
                    </h1>
                    <p className="mt-1.5 max-w-2xl text-[0.8125rem] text-muted-foreground">
                        Dessinez la composition des cabines — sièges, allées,
                        portes, poste conducteur — puis réutilisez chaque
                        gabarit sur les bus du parc.
                    </p>
                </div>

                <div className="flex items-center gap-2.5">
                    <button
                        type="button"
                        onClick={onboarding.start}
                        className="btn btn-ghost"
                        title="Revoir la visite guidée"
                    >
                        <CircleHelp className="size-3.5" />
                        Guide
                    </button>
                    <button
                        type="button"
                        onClick={() => router.get(layouts.create.url())}
                        className="btn btn-primary"
                        data-onboarding="layouts-new"
                    >
                        <Plus className="size-3.5" />
                        Nouveau gabarit
                    </button>
                </div>
            </div>

            <section className="mb-6 grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                <KpiCard
                    icon={Grid3X3}
                    label="Gabarits"
                    hint="Référentiel cabines"
                    value={stats.total}
                    tone="navy"
                />
                <KpiCard
                    icon={BusFront}
                    label="Bus équipés"
                    hint="Utilisent un gabarit"
                    value={stats.busesEquipped}
                    tone="blue"
                />
                <KpiCard
                    icon={Armchair}
                    label="Sièges vendables"
                    hint="Capacité des gabarits actifs"
                    value={stats.sellableSeats}
                    tone="gold"
                />
                <KpiCard
                    icon={Layers}
                    label="Brouillons"
                    hint="En cours de conception"
                    value={stats.drafts}
                    tone="muted"
                />
            </section>

            <section
                className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                data-onboarding="layouts-cards"
            >
                {templates.map((template) => {
                    const seats = seatStats(template.grid);
                    const locked = template.busesCount > 0;

                    return (
                        <article
                            key={template.uuid}
                            className="group flex flex-col overflow-hidden rounded-[var(--kora-radius)] border border-border/50 bg-card shadow-[0_16px_45px_rgba(0,29,78,0.07)] transition-shadow hover:shadow-[0_20px_55px_rgba(0,29,78,0.12)]"
                        >
                            <button
                                type="button"
                                onClick={() =>
                                    router.get(layouts.edit.url(template.uuid))
                                }
                                className="flex min-h-[190px] cursor-pointer items-center justify-center border-b border-border/40 bg-muted/30 p-4"
                                aria-label={`Ouvrir le gabarit ${template.name}`}
                            >
                                <SeatMap
                                    grid={template.grid}
                                    size="mini"
                                    cabin
                                />
                            </button>

                            <div className="flex flex-1 flex-col gap-3 p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-[0.9375rem] font-bold text-foreground">
                                                {template.name}
                                            </h3>
                                            <span className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-[0.625rem] font-bold text-muted-foreground">
                                                {template.code}
                                            </span>
                                        </div>
                                        <p className="mt-1 line-clamp-2 text-[0.6875rem] leading-relaxed text-muted-foreground">
                                            {template.description}
                                        </p>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Actions pour ${template.name}`}
                                            >
                                                <MoreHorizontal className="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    router.get(
                                                        layouts.edit.url(
                                                            template.uuid,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Edit3 className="size-4" />
                                                Ouvrir l'éditeur
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    duplicate(template)
                                                }
                                            >
                                                <Copy className="size-4" />
                                                Dupliquer
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() =>
                                                    archive(template)
                                                }
                                            >
                                                {template.status ===
                                                'archived' ? (
                                                    <>
                                                        <RotateCcw className="size-4" />
                                                        Restaurer
                                                    </>
                                                ) : (
                                                    <>
                                                        <Archive className="size-4" />
                                                        Archiver
                                                    </>
                                                )}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div className="mt-auto flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-border/40 pt-3 text-[0.6875rem] text-muted-foreground">
                                    <span className="inline-flex items-center gap-1.5">
                                        <Armchair className="size-3.5 text-gold" />
                                        <strong className="text-foreground">
                                            {seats.sellable}
                                        </strong>
                                        vendables
                                    </span>
                                    <span>
                                        <strong className="text-foreground">
                                            {seats.crew}
                                        </strong>{' '}
                                        équipage
                                    </span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <BusFront className="size-3.5" />
                                        <strong className="text-foreground">
                                            {template.busesCount}
                                        </strong>{' '}
                                        bus
                                        {locked && (
                                            <Lock className="size-3 text-gold" />
                                        )}
                                    </span>
                                    <span className="ml-auto">
                                        <KoraStatusBadge
                                            label={
                                                templateStatusLabels[
                                                    template.status
                                                ]
                                            }
                                            tone={statusTones[template.status]}
                                        />
                                    </span>
                                </div>
                            </div>
                        </article>
                    );
                })}

                {/* Carte de création */}
                <button
                    type="button"
                    onClick={() => router.get(layouts.create.url())}
                    className="flex min-h-[280px] cursor-pointer flex-col items-center justify-center gap-3 rounded-[var(--kora-radius)] border border-dashed border-border/70 bg-muted/10 p-8 text-center transition-colors hover:border-gold/60 hover:bg-muted/20"
                >
                    <span className="grid size-12 place-items-center rounded-[0.9rem] bg-gold/15 text-gold">
                        <Plus className="size-5" />
                    </span>
                    <span className="text-sm font-bold text-foreground">
                        Nouveau gabarit
                    </span>
                    <span className="max-w-[220px] text-[0.6875rem] leading-relaxed text-muted-foreground">
                        Dessinez une nouvelle composition de cabine cellule par
                        cellule.
                    </span>
                </button>
            </section>

            <OnboardingTour
                steps={ONBOARDING_STEPS}
                isActive={onboarding.isActive}
                onComplete={onboarding.complete}
                onDismiss={onboarding.dismiss}
            />
        </>
    );
}

LayoutsIndexPage.layout = {
    breadcrumbs: [
        { title: 'Ressources', href: '/resources' } satisfies BreadcrumbItem,
        { title: 'Gabarits', href: '/layouts' } satisfies BreadcrumbItem,
    ],
};
