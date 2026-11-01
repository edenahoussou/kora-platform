import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraAdminOrganizationsView } from '@/components/kora-points/admin/kora-admin-organizations-view';
import { KoraPointShell, KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';

const meta: Meta = {
    title: 'KORA POINT / 10 - Organisations & Héritage Contractuel',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

function AdminOrganizationsStoryWrapper() {
    const demo = useKoraPointDemo('org_attached_healthy');
    const [activePage, setActivePage] = useState<KoraPointActivePage>('admin-organizations');

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
            <KoraAdminOrganizationsView
                organizations={demo.adminOrganizations}
                subpoints={demo.organizationSubPoints}
                onToggleUserStatus={(uuid) => demo.toggleUserStatus(uuid)}
                onUpdateTermsOverride={(uuid, terms) => demo.updatePointTermsOverride(uuid, terms)}
            />
        </KoraPointShell>
    );
}

export const OrganizationDetailsLaPoste: Story = {
    name: '01. Fiche Organisation — La Poste du Bénin (Points, Accord-cadre, Conventions & Habilitations)',
    render: () => <AdminOrganizationsStoryWrapper />,
};

export const ContractualInheritanceMatrix: Story = {
    name: '02. Matrice d’héritage — Badges sémantiques (Règle personnalisée vs Héritée) & Surcharge',
    render: () => <AdminOrganizationsStoryWrapper />,
};
