import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraPointCashView } from '@/components/kora-points/cash/kora-point-cash-view';
import { KoraPointCashSessionDialog } from '@/components/kora-points/cash/kora-point-cash-session-dialog';
import { KoraPointProfileView } from '@/components/kora-points/profile/kora-point-profile-view';
import { KoraPointShell, KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';
import { DemoScenarioId } from '@/components/kora-points/mock/kora-point-mock-data';

const meta: Meta = {
    title: 'KORA POINT / 03 - Caisse & Fiche Mon Point',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

// Interactive wrapper for Cash view inside partner shell
function CashStoryWrapper({
    initialScenario = 'autonomous_healthy',
    startClosed = false,
}: {
    initialScenario?: DemoScenarioId;
    startClosed?: boolean;
}) {
    const demo = useKoraPointDemo(initialScenario);
    const [activePage, setActivePage] = useState<KoraPointActivePage>('cash');

    return (
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
                />
            }
        >
            <KoraPointCashView
                point={demo.point}
                health={demo.health}
                cashSession={
                    startClosed
                        ? {
                              ...demo.cashSession,
                              status: 'CLOSED',
                              closed_at: '2026-08-16 20:30',
                              actual_closing_cash: 125000,
                              cash_variance: 0,
                          }
                        : demo.cashSession
                }
                onOpenSession={demo.openCashSession}
                onCloseSession={demo.closeCashSession}
                onNavigateToSettlements={() => setActivePage('settlements')}
            />
        </KoraPointShell>
    );
}

// Interactive wrapper for Mon Point profile inside partner shell
function ProfileStoryWrapper({
    initialScenario = 'autonomous_healthy',
}: {
    initialScenario?: DemoScenarioId;
}) {
    const demo = useKoraPointDemo(initialScenario);
    const [activePage, setActivePage] = useState<KoraPointActivePage>('my-point');

    return (
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
                />
            }
        >
            <KoraPointProfileView
                point={demo.point}
                health={demo.health}
                convention={demo.convention}
                documents={demo.documents}
                assignedUsers={demo.assignedUsers}
            />
        </KoraPointShell>
    );
}

export const CashSessionOpen: Story = {
    name: '01. Caisse ouverte — Distinction Caisse physique vs Dette KORA',
    render: () => <CashStoryWrapper initialScenario="autonomous_healthy" />,
};

export const CashSessionClosed: Story = {
    name: '02. Caisse fermée — Écran d’invitation à l’ouverture',
    render: () => <CashStoryWrapper initialScenario="autonomous_healthy" startClosed={true} />,
};

export const CashClosingModalPreview: Story = {
    name: '03. Caisse — Dialogue de clôture avec calcul d’écart automatique',
    render: () => {
        const [isOpen, setIsOpen] = useState(true);
        return (
            <div className="p-8 bg-slate-900 min-h-screen text-white flex items-center justify-center">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                >
                    Rouvrir le modal de clôture
                </button>
                <KoraPointCashSessionDialog
                    isOpen={isOpen}
                    mode="CLOSE"
                    currentSession={{
                        id: 'cs-demo',
                        status: 'OPEN',
                        opened_at: '2026-08-17 07:45',
                        opened_by_name: 'Bio Kora Agent',
                        opening_amount: 50000,
                        cash_collected: 85000,
                        mobile_money_collected: 20000,
                        expected_closing_cash: 135000,
                        movements: [],
                    }}
                    onClose={() => setIsOpen(false)}
                    onSubmit={(amt, note) => {
                        alert(`Session clôturée avec montant compté: ${amt.toLocaleString()} FCFA. Note: ${note || 'Aucune'}`);
                        setIsOpen(false);
                    }}
                />
            </div>
        );
    },
};

export const MyPointAutonomous: Story = {
    name: '04. Fiche Mon Point — Point autonome (Akpakpa)',
    render: () => <ProfileStoryWrapper initialScenario="autonomous_healthy" />,
};

export const MyPointOrganizationAttached: Story = {
    name: '05. Fiche Mon Point — Bureau rattaché avec héritage (La Poste Bénin)',
    render: () => <ProfileStoryWrapper initialScenario="org_attached_healthy" />,
};
