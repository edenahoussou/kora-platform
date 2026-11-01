import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraAdminNetworkView } from '@/components/kora-points/admin/kora-admin-network-view';
import { KoraAdminPointWizardDialog } from '@/components/kora-points/admin/kora-admin-point-wizard-dialog';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import type { KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';

const meta: Meta = {
    title: 'KORA POINT / 09 - Administration Réseau Commercial',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

function AdminNetworkStoryWrapper() {
    const demo = useKoraPointDemo('autonomous_healthy');
    const [activePage, setActivePage] =
        useState<KoraPointActivePage>('admin-network');

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
            <KoraAdminNetworkView
                points={demo.adminNetworkPoints}
                organizations={demo.adminOrganizations}
                onCreatePoint={(data) => demo.createNetworkPoint(data)}
            />
        </KoraPointShell>
    );
}

export const AdminNetworkDashboard: Story = {
    name: '01. Réseau Commercial — Catalogue des 8 Points (Autonomes & Organisations rattachées)',
    render: () => <AdminNetworkStoryWrapper />,
};

function CreatePointWizardStory() {
    const demo = useKoraPointDemo('autonomous_healthy');
    const [isOpen, setIsOpen] = useState(true);

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-900 p-8 text-white">
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className="rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora uppercase"
            >
                Rouvrir le formulaire de création
            </button>
            <KoraAdminPointWizardDialog
                isOpen={isOpen}
                organizations={demo.adminOrganizations}
                onClose={() => setIsOpen(false)}
                onSubmit={(data) => {
                    demo.createNetworkPoint(data);
                    alert(
                        `Point "${data.name}" déployé avec succès avec plafond de ${data.creditLimit.toLocaleString()} FCFA.`,
                    );
                    setIsOpen(false);
                }}
            />
        </div>
    );
}

export const CreatePointWizardModalPreview: Story = {
    name: '02. Déploiement guidé — Formulaire en 4 étapes (Localisation, Rattachement, Conditions, Pièces)',
    render: () => <CreatePointWizardStory />,
};
