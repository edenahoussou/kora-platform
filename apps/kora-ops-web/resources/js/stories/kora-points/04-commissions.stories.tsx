import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { KoraPointCommissionsView } from '@/components/kora-points/commissions/kora-point-commissions-view';
import { KoraPointExportDemoDialog } from '@/components/kora-points/commissions/kora-point-export-demo-dialog';
import { KoraPointDemoBanner } from '@/components/kora-points/common/kora-point-demo-banner';
import type { KoraPointActivePage } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import type { DemoScenarioId } from '@/components/kora-points/mock/kora-point-mock-data';
import { useKoraPointDemo } from '@/components/kora-points/mock/use-kora-point-demo';

const meta: Meta = {
    title: 'KORA POINT / 04 - Commissions & Rémunération',
    parameters: {
        layout: 'fullscreen',
    },
};

export default meta;

type Story = StoryObj;

// Interactive wrapper for Commissions inside partner shell
function CommissionsStoryWrapper({
    initialScenario = 'autonomous_healthy',
}: {
    initialScenario?: DemoScenarioId;
}) {
    const demo = useKoraPointDemo(initialScenario);
    const [activePage, setActivePage] = useState<KoraPointActivePage>('commissions');
    const [selectedRefundRef, setSelectedRefundRef] = useState<string | null>(null);

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
            {selectedRefundRef && (
                <div className="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 flex items-center justify-between dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200">
                    <div>
                        <strong>Dossier de remboursement :</strong> Vous avez cliqué sur{' '}
                        <span className="font-mono font-bold">{selectedRefundRef}</span>. Dans le Lot 3 (Écran 06), ce lien ouvrira directement le dossier de restitution passager.
                    </div>
                    <button
                        type="button"
                        onClick={() => setSelectedRefundRef(null)}
                        className="rounded-lg bg-amber-200 px-3 py-1 font-bold text-amber-900 dark:bg-amber-800 dark:text-amber-100"
                    >
                        Fermer l’aperçu
                    </button>
                </div>
            )}

            <KoraPointCommissionsView
                point={demo.point}
                commissions={demo.commissions}
                onViewRefundDetail={(ref) => setSelectedRefundRef(ref)}
            />
        </KoraPointShell>
    );
}

export const CommissionsSummaryAndTable: Story = {
    name: '01. Synthèse par période & Tableau des ventes (Retenue à la source 3 %)',
    render: () => <CommissionsStoryWrapper initialScenario="autonomous_healthy" />,
};

export const CommissionsWithRefundAdjustment: Story = {
    name: '02. Ligne avec correction remboursement négative (-270 FCFA sur REM-2026-0004)',
    render: () => <CommissionsStoryWrapper initialScenario="autonomous_healthy" />,
};

function ExportDemoStory() {
    const [exportType, setExportType] = useState<'EXCEL' | 'PDF' | null>('EXCEL');

    return (
            <div className="p-8 bg-slate-900 min-h-screen text-white flex flex-col items-center justify-center gap-4">
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setExportType('EXCEL')}
                        className="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black uppercase text-white"
                    >
                        Ouvrir Démo Excel
                    </button>
                    <button
                        type="button"
                        onClick={() => setExportType('PDF')}
                        className="rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase text-kora"
                    >
                        Ouvrir Démo PDF
                    </button>
                </div>
                <KoraPointExportDemoDialog
                    isOpen={exportType !== null}
                    exportType={exportType || 'EXCEL'}
                    periodLabel="Août 2026 (En cours)"
                    pointName="Point Service Akpakpa"
                    onClose={() => setExportType(null)}
                />
            </div>
    );
}

export const ExportExcelAndPdfModalPreview: Story = {
    name: '03. Modals d’export démo — Bordereau Excel & Relevé officiel PDF',
    render: () => <ExportDemoStory />,
};
