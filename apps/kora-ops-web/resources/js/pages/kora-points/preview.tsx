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
import { KoraAdminNetworkView } from '@/components/kora-points/admin/kora-admin-network-view';
import { KoraAdminOrganizationsView } from '@/components/kora-points/admin/kora-admin-organizations-view';
import { KoraFinanceReconciliationView } from '@/components/kora-points/finance/kora-finance-reconciliation-view';
import { KoraNotificationsView } from '@/components/kora-points/notifications/kora-notifications-view';
import { KoraGlobalSearchDialog } from '@/components/kora-points/search/kora-global-search-dialog';
import { KoraPointShell, KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';
import { KoraPointUserRole, MOCK_PERSONAS } from '@/components/kora-points/mock/kora-point-mock-data';

export default function KoraPointPreviewPage() {
    const demo = useKoraPointDemo('autonomous_healthy');
    const [currentRole, setCurrentRole] = useState<KoraPointUserRole>('AGENT_POINT');
    const [activePage, setActivePage] = useState<KoraPointActivePage>('dashboard');
    const [tourKey, setTourKey] = useState(0);
    const [isSearchOpen, setIsSearchOpen] = useState(false);

    const handleRoleChange = (newRole: KoraPointUserRole) => {
        setCurrentRole(newRole);
        const persona = MOCK_PERSONAS[newRole];
        if (persona) {
            setActivePage(persona.defaultPage as KoraPointActivePage);
        }
    };

    const unreadNotificationsCount = demo.notifications.filter((n) => !n.read).length;

    return (
        <>
            <Head title="KORA POINT VOYAGE — Démonstration UI (MEZ-157)" />

            <KoraPointShell
                point={demo.point}
                health={demo.health}
                activePage={activePage}
                onNavigate={(page) => setActivePage(page)}
                unreadNotificationsCount={unreadNotificationsCount}
                onOpenSearch={() => setIsSearchOpen(true)}
                currentRole={currentRole}
                bannerSlot={
                    <KoraPointDemoBanner
                        currentScenario={demo.scenarioId}
                        onScenarioChange={demo.changeScenario}
                        currentRole={currentRole}
                        onRoleChange={handleRoleChange}
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

                {activePage === 'admin-network' && (
                    <KoraAdminNetworkView
                        points={demo.adminNetworkPoints}
                        organizations={demo.adminOrganizations}
                        onCreatePoint={(data) => demo.createNetworkPoint(data)}
                    />
                )}

                {activePage === 'admin-organizations' && (
                    <KoraAdminOrganizationsView
                        organizations={demo.adminOrganizations}
                        subpoints={demo.organizationSubPoints}
                        onToggleUserStatus={(userUuid) => demo.toggleUserStatus(userUuid)}
                        onUpdateTermsOverride={(pointUuid, terms) =>
                            demo.updatePointTermsOverride(pointUuid, terms)
                        }
                    />
                )}

                {activePage === 'finance' && (
                    <KoraFinanceReconciliationView
                        settlements={demo.settlements}
                        refunds={demo.refunds}
                        groupSettlements={demo.groupSettlements}
                        onReconcileSettlement={(uuid, params) =>
                            demo.reconcileSettlement(uuid, params)
                        }
                        onRejectSettlement={(uuid, params) =>
                            demo.rejectSettlement(uuid, params)
                        }
                        onApproveRefund={(uuid, params) =>
                            demo.approveRefund(uuid, params)
                        }
                        onRejectRefund={(uuid, params) =>
                            demo.rejectRefund(uuid, params)
                        }
                    />
                )}

                {activePage === 'notifications' && (
                    <KoraNotificationsView
                        notifications={demo.notifications}
                        onMarkAsRead={(uuid) => demo.markNotificationAsRead(uuid)}
                        onMarkAllAsRead={() => demo.markAllNotificationsAsRead()}
                        onTriggerTestNotification={() => demo.addTestNotification()}
                        onNavigate={(page) => setActivePage(page)}
                    />
                )}
            </KoraPointShell>

            {/* Global Spotlight Command Palette (Ctrl+K) */}
            <KoraGlobalSearchDialog
                isOpen={isSearchOpen}
                onClose={() => setIsSearchOpen(false)}
                sales={demo.sales}
                points={demo.adminNetworkPoints}
                organizations={demo.adminOrganizations}
                settlements={demo.settlements}
                currentRole={currentRole}
                onNavigate={(page) => setActivePage(page)}
            />
        </>
    );
}
