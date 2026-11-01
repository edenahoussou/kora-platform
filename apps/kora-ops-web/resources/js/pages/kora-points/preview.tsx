import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { KoraPointAgentDashboard } from '@/components/kora-points/agent-dashboard/kora-point-agent-dashboard';
import { KoraPointSaleView } from '@/components/kora-points/sale/kora-point-sale-view';
import { KoraPointTicketListView } from '@/components/kora-points/tickets/kora-point-ticket-list-view';
import { KoraPointCashView } from '@/components/kora-points/cash/kora-point-cash-view';
import { KoraPointProfileView } from '@/components/kora-points/profile/kora-point-profile-view';
import { KoraPointCommissionsView } from '@/components/kora-points/commissions/kora-point-commissions-view';
import { KoraPointSettlementListView } from '@/components/kora-points/settlements/kora-point-settlement-list-view';
import { KoraPointRefundListView } from '@/components/kora-points/refunds/kora-point-refund-list-view';
import { KoraOrgSupervisionView } from '@/components/kora-points/organization/kora-org-supervision-view';
import { KoraOrgTreasuryView } from '@/components/kora-points/organization/kora-org-treasury-view';
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

                {activePage === 'cash' && (
                    <KoraPointCashView
                        point={demo.point}
                        health={demo.health}
                        cashSession={demo.cashSession}
                        onOpenSession={demo.openCashSession}
                        onCloseSession={demo.closeCashSession}
                        onNavigateToSettlements={() => setActivePage('settlements')}
                    />
                )}

                {activePage === 'tickets' && (
                    <KoraPointTicketListView
                        sales={demo.sales}
                        onStartNewSale={() => setActivePage('sale')}
                    />
                )}

                {activePage === 'my-point' && (
                    <KoraPointProfileView
                        point={demo.point}
                        health={demo.health}
                        convention={demo.convention}
                        documents={demo.documents}
                        assignedUsers={demo.assignedUsers}
                    />
                )}

                {activePage === 'commissions' && (
                    <KoraPointCommissionsView
                        point={demo.point}
                        commissions={demo.commissions}
                        onViewRefundDetail={(refundRef) => setActivePage('refunds')}
                    />
                )}

                {activePage === 'settlements' && (
                    <KoraPointSettlementListView
                        point={demo.point}
                        health={demo.health}
                        settlements={demo.settlements}
                        onDeclareSettlement={(data) => demo.createSettlementDeclaration(data)}
                    />
                )}

                {activePage === 'refunds' && (
                    <KoraPointRefundListView
                        point={demo.point}
                        refunds={demo.refunds}
                        sales={demo.sales}
                        onInitiateRefund={(data) => demo.initiateRefundRequest(data)}
                        onConfirmRestitution={(uuid, signatory, note) =>
                            demo.confirmRefundRestitution(uuid, signatory, note)
                        }
                    />
                )}

                {activePage === 'org-supervision' && (
                    <KoraOrgSupervisionView
                        organizationName={demo.point.organization?.name || 'La Poste du Bénin SA'}
                        subpoints={demo.organizationSubPoints}
                        recentSales={demo.sales}
                        recentRefunds={demo.refunds}
                    />
                )}

                {activePage === 'org-treasury' && (
                    <KoraOrgTreasuryView
                        organizationName={demo.point.organization?.name || 'La Poste du Bénin SA'}
                        subpoints={demo.organizationSubPoints}
                        groupSettlements={demo.groupSettlements}
                        refunds={demo.refunds}
                        onDeclareGroupSettlement={(data) =>
                            demo.createGroupSettlementDeclaration(data)
                        }
                    />
                )}

                {activePage !== 'dashboard' &&
                    activePage !== 'sale' &&
                    activePage !== 'cash' &&
                    activePage !== 'tickets' &&
                    activePage !== 'my-point' &&
                    activePage !== 'commissions' &&
                    activePage !== 'settlements' &&
                    activePage !== 'refunds' &&
                    activePage !== 'org-supervision' &&
                    activePage !== 'org-treasury' && (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-900">
                            <span className="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-black uppercase text-amber-800 dark:text-amber-300">
                                Prévu pour les lots suivants
                            </span>
                            <h2 className="mt-3 text-lg font-black text-slate-900 dark:text-white">
                                Module « {activePage} » en cours de développement
                            </h2>
                            <p className="mt-2 text-xs text-slate-500 max-w-md mx-auto">
                                Les Lots 1 à 4 couvrent l'intégralité du quotidien agent, caisse, commissions, reversements, remboursements, et la supervision/trésorerie d'organisation. Les écrans administratifs KORA réseau arriveront dans le Lot 5.
                            </p>
                            <button
                                type="button"
                                onClick={() => setActivePage('dashboard')}
                                className="mt-4 rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                            >
                                Retour à l'accueil
                            </button>
                        </div>
                    )}
            </KoraPointShell>
        </>
    );
}
