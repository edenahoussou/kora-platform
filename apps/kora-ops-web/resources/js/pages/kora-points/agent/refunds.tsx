import { Head, router, Deferred } from '@inertiajs/react';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointRefundListView } from '@/components/kora-points/refunds/kora-point-refund-list-view';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    KoraPointProfile,
    FinancialHealth,
    PointRefundRecord,
    PointSaleRecord,
} from '@/types/kora-points';

type PaginatedRefunds = {
    data: PointRefundRecord[];
    current_page: number;
    last_page: number;
    total: number;
};

type PaginatedSales = {
    data: PointSaleRecord[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    refunds?: PaginatedRefunds;
    sales?: PaginatedSales;
};

function RefundsSkeleton() {
    return (
        <div className="animate-pulse space-y-4">
            <div className="flex items-center justify-between">
                <Skeleton className="h-10 w-64 rounded-xl" />
                <Skeleton className="h-10 w-44 rounded-xl" />
            </div>
            <Skeleton className="h-16 w-full rounded-2xl" />
            <Skeleton className="h-16 w-full rounded-2xl" />
            <Skeleton className="h-16 w-full rounded-2xl" />
        </div>
    );
}

export default function AgentRefundsPage({
    point,
    health,
    refunds,
    sales,
}: Props) {
    const handleInitiateRefund = (data: {
        ticketNumber: string;
        passengerName: string;
        passengerPhone: string;
        originalAmount: number;
        reasonLabel: string;
    }) => {
        // Find matching sale if available
        const matchedSale = sales?.data.find(
            (s) =>
                s.ticket_number === data.ticketNumber ||
                s.short_code === data.ticketNumber,
        );

        if (matchedSale) {
            router.post(
                `/kora-points/sales/${matchedSale.uuid}/refunds`,
                {
                    reason: data.reasonLabel,
                },
                { preserveScroll: true },
            );
        }
    };

    const handleConfirmRestitution = (refundUuid: string) => {
        router.post(
            `/kora-points/refunds/${refundUuid}/confirm`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Remboursements — KORA POINT" />
            <KoraPointShell
                point={point}
                health={health}
                activePage="refunds"
                onNavigate={handleAgentNavigate}
            >
                <Deferred
                    data={['refunds', 'sales']}
                    fallback={<RefundsSkeleton />}
                >
                    {refunds && sales ? (
                        <KoraPointRefundListView
                            point={point}
                            refunds={refunds.data}
                            sales={sales.data}
                            onInitiateRefund={handleInitiateRefund}
                            onConfirmRestitution={handleConfirmRestitution}
                        />
                    ) : (
                        <RefundsSkeleton />
                    )}
                </Deferred>
            </KoraPointShell>
        </>
    );
}
