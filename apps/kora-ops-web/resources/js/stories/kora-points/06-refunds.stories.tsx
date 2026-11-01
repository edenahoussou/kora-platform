import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraPointRefundListView } from '@/components/kora-points/refunds/kora-point-refund-list-view';
import { KoraPointRefundRequestDialog } from '@/components/kora-points/refunds/kora-point-refund-request-dialog';
import { KoraPointRefundRestitutionDialog } from '@/components/kora-points/refunds/kora-point-refund-restitution-dialog';
import { KoraPointShell, KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';
import { DemoScenarioId } from '@/components/kora-points/mock/kora-point-mock-data';

const meta: Meta = {
    title: 'KORA POINT / 06 - Remboursements & Restitutions',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

function RefundsStoryWrapper({
    initialScenario = 'autonomous_healthy',
}: {
    initialScenario?: DemoScenarioId;
}) {
    const demo = useKoraPointDemo(initialScenario);
    const [activePage, setActivePage] = useState<KoraPointActivePage>('refunds');

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
            <KoraPointRefundListView
                point={demo.point}
                refunds={demo.refunds}
                sales={demo.sales}
                onInitiateRefund={(data) => demo.initiateRefundRequest(data)}
                onConfirmRestitution={(uuid, sig, note) =>
                    demo.confirmRefundRestitution(uuid, sig, note)
                }
            />
        </KoraPointShell>
    );
}

export const RefundsFullListView: Story = {
    name: '01. Liste complète — Statuts (En instruction, Prêts pour restitution, Restitués)',
    render: () => <RefundsStoryWrapper initialScenario="autonomous_healthy" />,
};

export const RefundRequestModalPreview: Story = {
    name: '02. Modal de demande — Sélection billet & Décomposition reprise com (-3 %)',
    render: () => {
        const demo = useKoraPointDemo('autonomous_healthy');
        const [isOpen, setIsOpen] = useState(true);

        return (
            <div className="p-8 bg-slate-900 min-h-screen text-white flex items-center justify-center">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                >
                    Ouvrir le formulaire d'annulation
                </button>
                <KoraPointRefundRequestDialog
                    isOpen={isOpen}
                    point={demo.point}
                    eligibleSales={demo.sales.filter((s) => s.refundable)}
                    onClose={() => setIsOpen(false)}
                    onSubmit={(data) => {
                        demo.initiateRefundRequest(data);
                        alert(`Demande enregistrée pour le billet ${data.ticketNumber}. Net voyageur: ${(data.originalAmount - (data.retainedFee || 0)).toLocaleString()} FCFA.`);
                        setIsOpen(false);
                    }}
                />
            </div>
        );
    },
};

export const RefundRestitutionModalPreview: Story = {
    name: '03. Restitution d’espèces & Quittance d’émargement thermique démo',
    render: () => {
        const demo = useKoraPointDemo('autonomous_healthy');
        const [isOpen, setIsOpen] = useState(true);
        const approvedRefund = demo.refunds.find((r) => r.status === 'APPROVED_BY_FINANCE') || demo.refunds[0];

        return (
            <div className="p-8 bg-slate-900 min-h-screen text-white flex items-center justify-center">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                >
                    Ouvrir la confirmation de restitution
                </button>
                <KoraPointRefundRestitutionDialog
                    isOpen={isOpen}
                    refund={approvedRefund}
                    pointName="Point Service Akpakpa"
                    operatorName="Jean Dossou"
                    onClose={() => setIsOpen(false)}
                    onConfirmRestitution={(uuid, sig, note) => {
                        demo.confirmRefundRestitution(uuid, sig, note);
                    }}
                />
            </div>
        );
    },
};
