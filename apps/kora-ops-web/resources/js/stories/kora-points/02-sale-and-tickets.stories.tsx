import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import type { KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import type { DemoScenarioId } from '@/components/kora-points/mock/kora-point-mock-data';
import { MOCK_POINT_SALES } from '@/components/kora-points/mock/kora-point-mock-data';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';
import { KoraPointSaleSuccessDialog } from '@/components/kora-points/sale/kora-point-sale-success-dialog';
import { KoraPointSaleView } from '@/components/kora-points/sale/kora-point-sale-view';
import { KoraPointTicketDetailDrawer } from '@/components/kora-points/tickets/kora-point-ticket-detail-drawer';
import { KoraPointTicketListView } from '@/components/kora-points/tickets/kora-point-ticket-list-view';

const meta: Meta = {
    title: 'KORA POINT / 02 - Guichet, Vente & Billets',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

// Interactive Sale Journey Wrapper
function SaleInteractiveStory({ initialScenario = 'autonomous_healthy' }: { initialScenario?: DemoScenarioId }) {
    const demo = useKoraPointDemo(initialScenario);
    const [activePage, setActivePage] = useState<KoraPointActivePage>('sale');

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
            {activePage === 'sale' ? (
                <KoraPointSaleView
                    point={demo.point}
                    health={demo.health}
                    onSaleCompleted={(saleData) => demo.recordSimulatedSale(saleData)}
                    onBackToDashboard={() => setActivePage('dashboard')}
                />
            ) : (
                <KoraPointTicketListView
                    sales={demo.sales}
                    onStartNewSale={() => setActivePage('sale')}
                />
            )}
        </KoraPointShell>
    );
}

// Tickets Journal Interactive Story
function TicketsInteractiveStory() {
    const demo = useKoraPointDemo('autonomous_healthy');
    const [activePage, setActivePage] = useState<KoraPointActivePage>('tickets');

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
            <KoraPointTicketListView
                sales={demo.sales}
                onStartNewSale={() => setActivePage('sale')}
            />
        </KoraPointShell>
    );
}

export const SaleJourneyInteractive: Story = {
    name: '01. Parcours de vente guichet (Trajet → Places → Passagers → Paiement)',
    render: () => <SaleInteractiveStory initialScenario="autonomous_healthy" />,
};

export const SaleBlockedByLimit: Story = {
    name: '02. Vente bloquée par dépassement de plafond (Plafond atteint)',
    render: () => <SaleInteractiveStory initialScenario="autonomous_limit_reached" />,
};

export const SaleSuccessConfirmationModal: Story = {
    name: '03. Reçu de vente & Billet thermique simulé (Exemple démo)',
    render: () => {
        const [isOpen, setIsOpen] = useState(true);

        return (
            <div className="p-8">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora"
                >
                    Rouvrir le reçu de vente
                </button>
                {isOpen && (
                    <KoraPointSaleSuccessDialog
                        saleRecord={MOCK_POINT_SALES[0]}
                        onClose={() => setIsOpen(false)}
                        onNewSale={() => setIsOpen(false)}
                    />
                )}
            </div>
        );
    },
};

export const TicketLedgerFull: Story = {
    name: '04. Journal des billets & Activité du Point (Filtres & Statuts)',
    render: () => <TicketsInteractiveStory />,
};

export const TicketDetailDrawerPreview: Story = {
    name: '05. Tiroir de détail du billet sélectionné',
    render: () => {
        const [selectedSale, setSelectedSale] = useState<any>(MOCK_POINT_SALES[0]);

        return (
            <div className="p-8">
                <button
                    type="button"
                    onClick={() => setSelectedSale(MOCK_POINT_SALES[0])}
                    className="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white dark:bg-white dark:text-slate-900"
                >
                    Ouvrir le détail du billet TK-AKP-260801-01
                </button>
                <KoraPointTicketDetailDrawer
                    sale={selectedSale}
                    onClose={() => setSelectedSale(null)}
                />
            </div>
        );
    },
};
