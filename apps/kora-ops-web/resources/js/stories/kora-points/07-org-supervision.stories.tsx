import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraOrgSupervisionView } from '@/components/kora-points/organization/kora-org-supervision-view';
import { KoraOrgSubPointDrawer } from '@/components/kora-points/organization/kora-org-subpoint-drawer';
import { KoraPointShell, KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';

const meta: Meta = {
    title: 'KORA POINT / 07 - Supervision Organisation (La Poste)',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

function SupervisionStoryWrapper() {
    const demo = useKoraPointDemo('org_attached_healthy');
    const [activePage, setActivePage] = useState<KoraPointActivePage>('org-supervision');

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
            <KoraOrgSupervisionView
                organizationName="La Poste du Bénin SA"
                subpoints={demo.organizationSubPoints}
                recentSales={demo.sales}
                recentRefunds={demo.refunds}
            />
        </KoraPointShell>
    );
}

export const SupervisionGlobalView: Story = {
    name: '01. Supervision globale — 5 bureaux de poste (Consolidation réseau & Alertes)',
    render: () => <SupervisionStoryWrapper />,
};

export const SubPointDetailDrawerPreview: Story = {
    name: '02. Fiche Bureau rattaché en lecture seule — Porto-Novo (Plafond 100 % atteint)',
    render: () => {
        const demo = useKoraPointDemo('org_attached_healthy');
        const [isOpen, setIsOpen] = useState(true);
        const targetSubpoint =
            demo.organizationSubPoints.find((sp) => sp.status === 'LIMIT_REACHED') ||
            demo.organizationSubPoints[0];

        return (
            <div className="p-8 bg-slate-900 min-h-screen text-white flex items-center justify-center">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                >
                    Rouvrir le tiroir de consultation
                </button>
                <KoraOrgSubPointDrawer
                    isOpen={isOpen}
                    subpoint={targetSubpoint}
                    onClose={() => setIsOpen(false)}
                />
            </div>
        );
    },
};
