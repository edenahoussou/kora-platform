import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { KoraPointAgentDashboard } from '@/components/kora-points/agent-dashboard/kora-point-agent-dashboard';
import { KoraPointSaleView } from '@/components/kora-points/sale/kora-point-sale-view';
import { KoraPointTicketListView } from '@/components/kora-points/tickets/kora-point-ticket-list-view';
import { KoraPointShell, KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';

export default function KoraPointPreviewPage() {
    const demo = useKoraPointDemo('autonomous_healthy');
    const [activePage, setActivePage] = useState<KoraPointActivePage>('dashboard');
    const [tourKey, setTourKey] = useState(0);

    return (
        <>
            <Head title="KORA POINT VOYAGE — Démonstration UI (MEZ-157)" />

            <KoraPointShell
                point={demo.point}
                health={demo.health}
                activePage={activePage}
                onNavigate={(page) => setActivePage(page)}
                bannerSlot={
                    <KoraPointDemoBanner
                        currentScenario={demo.scenarioId}
                        onScenarioChange={demo.changeScenario}
                        onReset={demo.resetDemo}
                        onRestartTour={() => setTourKey((k) => k + 1)}
                    />
                }
            >
                {activePage === 'dashboard' && (
                    <KoraPointAgentDashboard
                        key={tourKey}
                        point={demo.point}
                        health={demo.health}
                        metrics={demo.metrics}
                        priorities={demo.priorities}
                        onStartSale={() => setActivePage('sale')}
                        onViewSettlements={() => setActivePage('settlements')}
                        onNavigate={(pageId) => setActivePage(pageId as any)}
                        showOnboardingTourDefault={tourKey > 0}
                    />
                )}

                {activePage === 'sale' && (
                    <KoraPointSaleView
                        point={demo.point}
                        health={demo.health}
                        onSaleCompleted={(saleData) => demo.recordSimulatedSale(saleData)}
                        onBackToDashboard={() => setActivePage('dashboard')}
                    />
                )}

                {activePage === 'tickets' && (
                    <KoraPointTicketListView
                        sales={demo.sales}
                        onStartNewSale={() => setActivePage('sale')}
                    />
                )}

                {activePage !== 'dashboard' &&
                    activePage !== 'sale' &&
                    activePage !== 'tickets' && (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
                            <span className="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-black uppercase text-amber-800 dark:text-amber-300">
                                Prévu pour les lots suivants
                            </span>
                            <h2 className="mt-3 text-lg font-black text-slate-900 dark:text-white">
                                Module « {activePage} » en cours de développement
                            </h2>
                            <p className="mt-2 text-xs text-slate-500 max-w-md mx-auto">
                                Le Lot 1 couvre l'Accueil Agent (Écran 01) et le Guichet de vente & Billets (Écran 02). Cliquez ci-dessous pour revenir aux écrans du Lot 1.
                            </p>
                            <button
                                type="button"
                                onClick={() => setActivePage('dashboard')}
                                className="mt-4 rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                            >
                                Retour à l'accueil agent
                            </button>
                        </div>
                    )}
            </KoraPointShell>
        </>
    );
}
