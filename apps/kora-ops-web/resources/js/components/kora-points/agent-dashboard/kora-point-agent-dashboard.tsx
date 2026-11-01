import { Check, HelpCircle, X, ArrowRight } from 'lucide-react';
import { useState } from 'react';
import type { KoraPointProfile, FinancialHealth, DailyMetrics, PointPriorityAlert } from '../mock/kora-point-mock-data';
import { KoraPointActivityChart } from './kora-point-activity-chart';
import { KoraPointFinancialHealth } from './kora-point-financial-health';
import { KoraPointHeroBanner } from './kora-point-hero-banner';
import { KoraPointKpiGrid } from './kora-point-kpi-grid';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    metrics: DailyMetrics;
    priorities: PointPriorityAlert[];
    onStartSale: () => void;
    onViewSettlements: () => void;
    onNavigate: (pageId: string) => void;
    showOnboardingTourDefault?: boolean;
};

export function KoraPointAgentDashboard({
    point,
    health,
    metrics,
    priorities,
    onStartSale,
    onViewSettlements,
    onNavigate,
    showOnboardingTourDefault = false,
}: Props) {
    const [tourStep, setTourStep] = useState<number | null>(
        showOnboardingTourDefault ? 1 : null,
    );

    const tourSteps = [
        {
            step: 1,
            title: 'Bienvenue sur votre espace KORA POINT',
            description:
                'Ce tableau de bord centralise vos ventes guichet, votre encours de crédit et vos commissions acquises sur chaque billet Bus émis.',
            highlight: 'hero',
        },
        {
            step: 2,
            title: 'Surveillez votre santé financière',
            description:
                'La jauge d’encours vous indique le disponible restant avant le plafond de crédit. Une alerte se déclenche à 80 % et les ventes se bloquent à 100 % jusqu’à validation de votre versement.',
            highlight: 'health',
        },
        {
            step: 3,
            title: 'Vendez et encaissez en toute simplicité',
            description:
                'Cliquez sur "Vendre un billet" pour rechercher un trajet, choisir les sièges et renseigner les passagers en 4 étapes simples.',
            highlight: 'sale',
        },
    ];

    const currentTour = tourStep !== null ? tourSteps[tourStep - 1] : null;

    return (
        <div className="space-y-6">
            {/* Onboarding Tour Dialog (modal avec fond flouté) */}
            {currentTour && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label="Guide de bienvenue KORA POINT"
                    className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-in fade-in"
                >
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800">
                            <span className="flex items-center gap-1.5 text-[0.6875rem] font-black uppercase tracking-wider text-gold">
                                <HelpCircle className="size-4" />
                                Guide du guichetier · Étape {currentTour.step} sur {tourSteps.length}
                            </span>
                            <button
                                type="button"
                                onClick={() => setTourStep(null)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                aria-label="Fermer le guide"
                            >
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="my-4">
                            <h3 className="text-base font-extrabold text-slate-900 dark:text-white">
                                {currentTour.title}
                            </h3>
                            <p className="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                                {currentTour.description}
                            </p>
                        </div>

                        <div className="flex items-center justify-between pt-2">
                            <button
                                type="button"
                                onClick={() => setTourStep(null)}
                                className="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                Passer le guide
                            </button>

                            <div className="flex items-center gap-2">
                                {tourStep !== null && tourStep < tourSteps.length ? (
                                    <button
                                        type="button"
                                        onClick={() => setTourStep(tourStep + 1)}
                                        className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-4 py-2 text-xs font-bold text-kora shadow-md transition hover:bg-gold/90"
                                    >
                                        <span>Suivant</span>
                                        <ArrowRight className="size-3.5" />
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => setTourStep(null)}
                                        className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-md transition hover:bg-emerald-700"
                                    >
                                        <Check className="size-3.5" />
                                        <span>J'ai compris</span>
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Hero Banner */}
            <div id="tour-hero">
                <KoraPointHeroBanner
                    point={point}
                    health={health}
                    onStartSale={onStartSale}
                    onViewSettlements={onViewSettlements}
                />
            </div>

            {/* Financial Health Ribbon */}
            <div id="tour-health">
                <KoraPointFinancialHealth
                    point={point}
                    health={health}
                    onPrepareSettlement={onViewSettlements}
                />
            </div>

            {/* KPI Grid */}
            <KoraPointKpiGrid
                metrics={metrics}
                terms={point.terms}
                onKpiClick={(type) => {
                    if (type === 'sales' || type === 'tickets') {
onNavigate('tickets');
} else if (type === 'commissions') {
onNavigate('commissions');
} else if (type === 'settlement') {
onNavigate('settlements');
}
                }}
            />

            {/* Activity Chart & Priorities */}
            <KoraPointActivityChart
                activity7d={metrics.activity_7d}
                priorities={priorities}
                onQuickAction={onNavigate}
            />
        </div>
    );
}
