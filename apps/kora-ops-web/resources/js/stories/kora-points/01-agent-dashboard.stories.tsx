import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraPointAgentDashboard } from '@/components/kora-points/agent-dashboard/kora-point-agent-dashboard';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import type { KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import type { DemoScenarioId } from '@/components/kora-points/mock/kora-point-mock-data';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';

const meta: Meta = {
    title: 'KORA POINT / 01 - Accueil Agent & Navigation',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

// Interactive Container wrapping Shell, Banner, and Dashboard
function DashboardStoryWrapper({
    initialScenario = 'autonomous_healthy',
    startTour = false,
}: {
    initialScenario?: DemoScenarioId;
    startTour?: boolean;
}) {
    const demo = useKoraPointDemo(initialScenario);
    const [activePage, setActivePage] =
        useState<KoraPointActivePage>('dashboard');
    const [tourKey, setTourKey] = useState(0);

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
                    onRestartTour={() => setTourKey((k) => k + 1)}
                />
            }
        >
            <KoraPointAgentDashboard
                key={tourKey}
                point={demo.point}
                health={demo.health}
                metrics={demo.metrics}
                priorities={demo.priorities}
                onStartSale={() => setActivePage('sale')}
                onViewSettlements={() => setActivePage('settlements')}
                onNavigate={(pageId) => setActivePage(pageId as any)}
                showOnboardingTourDefault={startTour || tourKey > 0}
            />
        </KoraPointShell>
    );
}

export const DefaultAutonomous: Story = {
    name: '01. Point autonome — Prêt à vendre (Normal)',
    render: () => (
        <DashboardStoryWrapper initialScenario="autonomous_healthy" />
    ),
};

export const WarningThreshold80: Story = {
    name: '02. Point autonome — Seuil 80 % (Alerte encours)',
    render: () => (
        <DashboardStoryWrapper initialScenario="autonomous_warning_80" />
    ),
};

export const LimitReached100Blocked: Story = {
    name: '03. Point autonome — Plafond 100 % (Ventes bloquées)',
    render: () => (
        <DashboardStoryWrapper initialScenario="autonomous_limit_reached" />
    ),
};

export const OverdueScheduleBlocked: Story = {
    name: '04. Point autonome — Échéance en retard (Suspendu)',
    render: () => (
        <DashboardStoryWrapper initialScenario="autonomous_overdue" />
    ),
};

export const OrganizationAttached: Story = {
    name: '05. Organisation — Bureau rattaché (La Poste)',
    render: () => (
        <DashboardStoryWrapper initialScenario="org_attached_healthy" />
    ),
};

export const InteractiveWithTour: Story = {
    name: '06. Guide d’accueil interactif (Onboarding tour)',
    render: () => (
        <DashboardStoryWrapper
            initialScenario="autonomous_healthy"
            startTour={true}
        />
    ),
};
