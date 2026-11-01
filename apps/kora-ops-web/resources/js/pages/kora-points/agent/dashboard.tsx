import { Deferred, Head } from '@inertiajs/react';
import { Ticket } from 'lucide-react';
import { KoraPointAgentDashboard } from '@/components/kora-points/agent-dashboard/kora-point-agent-dashboard';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    DailyMetrics,
    FinancialHealth,
    KoraPointProfile,
    PointPriorityAlert,
} from '@/types/kora-points';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    metrics?: DailyMetrics;
    priorities?: PointPriorityAlert[];
};

function DashboardSkeleton() {
    return (
        <div className="animate-pulse space-y-6">
            <Skeleton className="h-44 w-full rounded-3xl" />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Skeleton className="h-28 rounded-2xl" />
                <Skeleton className="h-28 rounded-2xl" />
                <Skeleton className="h-28 rounded-2xl" />
                <Skeleton className="h-28 rounded-2xl" />
            </div>
            <Skeleton className="h-64 w-full rounded-2xl" />
        </div>
    );
}

export default function AgentDashboardPage({
    point,
    health,
    metrics,
    priorities,
}: Props) {
    const displayName = point.name?.toLowerCase().includes('kora point')
        ? point.name
        : `KORA POINT ${point.name}`;

    return (
        <>
            <Head title={`Tableau de bord — ${displayName} (${point.code})`} />
            <KoraPointShell
                point={point}
                health={health}
                activePage="dashboard"
                onNavigate={handleAgentNavigate}
            >
                <div className="kora-page-head cash-reveal mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                            KORA POINT / Espace Agent / Tableau de bord
                        </div>
                        <h1 className="text-[clamp(1.8rem,3vw,2.65rem)] font-extrabold tracking-[-0.055em] text-foreground">
                            Tableau de bord Guichet {displayName} ({point.code})
                        </h1>
                        <p className="mt-1.5 text-[0.8125rem] leading-6 text-muted-foreground">
                            Vue d'ensemble de votre point de vente KORA EXPRESS
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => handleAgentNavigate('sale')}
                        >
                            <Ticket className="size-4" /> Vendre un billet
                        </button>
                    </div>
                </div>

                <Deferred
                    data={['metrics', 'priorities']}
                    fallback={<DashboardSkeleton />}
                >
                    {metrics && priorities ? (
                        <KoraPointAgentDashboard
                            point={point}
                            health={health}
                            metrics={metrics}
                            priorities={priorities}
                            onStartSale={() => handleAgentNavigate('sale')}
                            onViewSettlements={() =>
                                handleAgentNavigate('settlements')
                            }
                            onNavigate={handleAgentNavigate}
                        />
                    ) : (
                        <DashboardSkeleton />
                    )}
                </Deferred>
            </KoraPointShell>
        </>
    );
}
