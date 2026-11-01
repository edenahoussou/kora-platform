import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import type { KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import type { DemoScenarioId } from '@/components/kora-points/mock/kora-point-mock-data';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';
import { KoraPointSettlementDetailDrawer } from '@/components/kora-points/settlements/kora-point-settlement-detail-drawer';
import { KoraPointSettlementDialog } from '@/components/kora-points/settlements/kora-point-settlement-dialog';
import { KoraPointSettlementListView } from '@/components/kora-points/settlements/kora-point-settlement-list-view';

const meta: Meta = {
    title: 'KORA POINT / 05 - Reversements Partenaires',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

function SettlementsStoryWrapper({
    initialScenario = 'autonomous_healthy',
}: {
    initialScenario?: DemoScenarioId;
}) {
    const demo = useKoraPointDemo(initialScenario);
    const [activePage, setActivePage] =
        useState<KoraPointActivePage>('settlements');

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
            <KoraPointSettlementListView
                point={demo.point}
                health={demo.health}
                settlements={demo.settlements}
                onDeclareSettlement={(data) =>
                    demo.createSettlementDeclaration(data)
                }
            />
        </KoraPointShell>
    );
}

export const SettlementsAutonomousPoint: Story = {
    name: '01. Point autonome — Liste & Action Déclarer disponible',
    render: () => (
        <SettlementsStoryWrapper initialScenario="autonomous_healthy" />
    ),
};

export const SettlementsOrganizationAttached: Story = {
    name: '02. Bureau rattaché (La Poste) — Consultation seule (Paiements centralisés)',
    render: () => (
        <SettlementsStoryWrapper initialScenario="org_attached_healthy" />
    ),
};

function SettlementDeclarationStory() {
    const demo = useKoraPointDemo('autonomous_healthy');
    const [isOpen, setIsOpen] = useState(true);

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-900 p-8 text-white">
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className="rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora uppercase"
            >
                Ouvrir le formulaire de versement
            </button>
            <KoraPointSettlementDialog
                isOpen={isOpen}
                health={demo.health}
                pointName="Point Service Akpakpa"
                onClose={() => setIsOpen(false)}
                onSubmit={(data) => {
                    demo.createSettlementDeclaration(data);
                    alert(
                        `Déclaration transmise : ${data.amountDeclared.toLocaleString()} FCFA (${data.paymentReference})`,
                    );
                    setIsOpen(false);
                }}
            />
        </div>
    );
}

export const SettlementDeclarationModalPreview: Story = {
    name: '03. Modal de déclaration — Sélection dette & Upload preuve (Desktop / Mobile)',
    render: () => <SettlementDeclarationStory />,
};

function SettlementDetailDrawerStory() {
    const demo = useKoraPointDemo('autonomous_healthy');
    const [isOpen, setIsOpen] = useState(true);

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-900 p-8 text-white">
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className="rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora uppercase"
            >
                Ouvrir le tiroir de détail
            </button>
            <KoraPointSettlementDetailDrawer
                isOpen={isOpen}
                settlement={demo.settlements[0]}
                onClose={() => setIsOpen(false)}
            />
        </div>
    );
}

export const SettlementDetailDrawerPreview: Story = {
    name: '04. Tiroir latéral de détail — Reçu bancaire & Billets couverts',
    render: () => <SettlementDetailDrawerStory />,
};
