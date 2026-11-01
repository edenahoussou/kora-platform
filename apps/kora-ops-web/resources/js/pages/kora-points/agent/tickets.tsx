import { Head, Deferred } from '@inertiajs/react';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointTicketListView } from '@/components/kora-points/tickets/kora-point-ticket-list-view';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    KoraPointProfile,
    FinancialHealth,
    PointSaleRecord,
} from '@/types/kora-points';

type PaginatedSales = {
    data: PointSaleRecord[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    point: KoraPointProfile;
    sales?: PaginatedSales;
};

function TicketsSkeleton() {
    return (
        <div className="animate-pulse space-y-4">
            <div className="flex items-center justify-between">
                <Skeleton className="h-10 w-64 rounded-xl" />
                <Skeleton className="h-10 w-36 rounded-xl" />
            </div>
            <Skeleton className="h-14 w-full rounded-2xl" />
            <Skeleton className="h-14 w-full rounded-2xl" />
            <Skeleton className="h-14 w-full rounded-2xl" />
            <Skeleton className="h-14 w-full rounded-2xl" />
        </div>
    );
}

export default function AgentTicketsPage({ point, sales }: Props) {
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
            <Head title="Billets émis — KORA POINT" />
            <KoraPointShell
                point={point}
                health={dummyHealth}
                activePage="tickets"
                onNavigate={handleAgentNavigate}
            >
                <Deferred data="sales" fallback={<TicketsSkeleton />}>
                    {sales ? (
                        <KoraPointTicketListView
                            sales={sales.data}
                            onStartNewSale={() => handleAgentNavigate('sale')}
                            onRequestRefund={() =>
                                handleAgentNavigate('refunds')
                            }
                        />
                    ) : (
                        <TicketsSkeleton />
                    )}
                </Deferred>
            </KoraPointShell>
        </>
    );
}
