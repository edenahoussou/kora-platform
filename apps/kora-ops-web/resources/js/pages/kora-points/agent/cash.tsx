import { Head } from '@inertiajs/react';
import { KoraPointCashView } from '@/components/kora-points/cash/kora-point-cash-view';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import type {
    KoraPointProfile,
    FinancialHealth,
    PointCashSession,
    DailyCashBrouillard,
    CashBrouillardFilters,
} from '@/types/kora-points';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    brouillard: DailyCashBrouillard;
    filters?: CashBrouillardFilters;
    cashSession?: PointCashSession;
};

export default function AgentCashPage({
    point,
    health,
    brouillard,
    filters,
    cashSession,
}: Props) {
    return (
        <>
            <Head title="Brouillard d’encaissement — KORA POINT" />
            <KoraPointShell
                point={point}
                health={health}
                activePage="cash"
                onNavigate={handleAgentNavigate}
            >
                <KoraPointCashView
                    point={point}
                    health={health}
                    brouillard={brouillard}
                    filters={filters}
                    cashSession={cashSession}
                    onNavigateToSale={() => handleAgentNavigate('sale')}
                    onNavigateToSettlements={() =>
                        handleAgentNavigate('settlements')
                    }
                />
            </KoraPointShell>
        </>
    );
}
