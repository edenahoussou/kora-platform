import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import type { KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';
import { KoraOrgGroupSettlementDialog } from '@/components/kora-points/organization/kora-org-group-settlement-dialog';
import { KoraOrgTreasuryView } from '@/components/kora-points/organization/kora-org-treasury-view';

const meta: Meta = {
    title: 'KORA POINT / 08 - Trésorier d’Organisation (La Poste)',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

function TreasuryStoryWrapper() {
    const demo = useKoraPointDemo('org_attached_healthy');
    const [activePage, setActivePage] = useState<KoraPointActivePage>('org-treasury');

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
            <KoraOrgTreasuryView
                organizationName="La Poste du Bénin SA"
                subpoints={demo.organizationSubPoints}
                groupSettlements={demo.groupSettlements}
                refunds={demo.refunds}
                onDeclareGroupSettlement={(data) => demo.createGroupSettlementDeclaration(data)}
            />
        </KoraPointShell>
    );
}

export const TreasuryDashboard: Story = {
    name: '01. Accueil financier — Dettes consolidées & Dossiers de versements groupés',
    render: () => <TreasuryStoryWrapper />,
};

export const GroupSettlementModalPreview: Story = {
    name: '02. Reversement groupé — Ventilation automatique au prorata & Contrôle d’écart',
    render: () => {
        const demo = useKoraPointDemo('org_attached_healthy');
        const [isOpen, setIsOpen] = useState(true);

        return (
            <div className="p-8 bg-slate-900 min-h-screen text-white flex items-center justify-center">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                >
                    Ouvrir le formulaire de reversement groupé
                </button>
                <KoraOrgGroupSettlementDialog
                    isOpen={isOpen}
                    organizationName="La Poste du Bénin SA"
                    subpoints={demo.organizationSubPoints}
                    onClose={() => setIsOpen(false)}
                    onSubmit={(data) => {
                        demo.createGroupSettlementDeclaration(data);
                        alert(`Reversement groupé soumis : ${data.totalAmountDeclared.toLocaleString()} FCFA sur ${data.breakdown.length} bureaux. Réf: ${data.paymentReference}`);
                        setIsOpen(false);
                    }}
                />
            </div>
        );
    },
};
