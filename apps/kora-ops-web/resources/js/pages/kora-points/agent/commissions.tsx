import { Head, Deferred, router } from '@inertiajs/react';
import { KoraPointCommissionsView } from '@/components/kora-points/commissions/kora-point-commissions-view';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    KoraPointProfile,
    FinancialHealth,
    PointCommissionRecord,
} from '@/types/kora-points';

type PaginatedCommissions = {
    data: PointCommissionRecord[];
    current_page: number;
    last_page: number;
    total: number;
    totals: {
        gross: number;
        initial: number;
        corrections: number;
        net: number;
    };
    period: 'current_month' | 'last_month' | 'all';
    period_label: string;
    count: number;
};

type Props = {
    point: KoraPointProfile;
    commissions?: PaginatedCommissions;
};

function CommissionsSkeleton() {
    return (
        <div className="animate-pulse space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Skeleton className="h-24 rounded-2xl" />
                <Skeleton className="h-24 rounded-2xl" />
                <Skeleton className="h-24 rounded-2xl" />
            </div>
            <Skeleton className="h-14 w-full rounded-2xl" />
            <Skeleton className="h-14 w-full rounded-2xl" />
            <Skeleton className="h-14 w-full rounded-2xl" />
        </div>
    );
}

export default function AgentCommissionsPage({ point, commissions }: Props) {
    const dummyHealth: FinancialHealth = {
        credit_limit: point.terms.credit_limit_amount,
        current_exposure: 0,
        available_balance: point.terms.credit_limit_amount,
        usage_percentage: 0,
        next_due_date: new Date().toISOString(),
        next_due_label: 'Conforme',
        is_overdue: false,
        sales_allowed: true,
        block_reason: null,
    };

    return (
        <>
            <Head title="Commissions — KORA POINT" />
            <KoraPointShell
                point={point}
                health={dummyHealth}
                activePage="commissions"
                onNavigate={handleAgentNavigate}
            >
                <Deferred data="commissions" fallback={<CommissionsSkeleton />}>
                    {commissions ? (
                        <KoraPointCommissionsView
                            point={point}
                            commissions={commissions.data}
                            periodFilter={commissions.period}
                            periodLabel={commissions.period_label}
                            totals={commissions.totals}
                            operationCount={commissions.count}
                            onPeriodChange={(period) =>
                                router.get(
                                    '/kora-point/commissions',
                                    { period },
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                )
                            }
                            onViewRefundDetail={() =>
                                handleAgentNavigate('refunds')
                            }
                        />
                    ) : (
                        <CommissionsSkeleton />
                    )}
                </Deferred>
            </KoraPointShell>
        </>
    );
}
