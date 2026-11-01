import { Head, Deferred } from '@inertiajs/react';
import { handleAgentNavigate } from '@/components/kora-points/common/agent-nav';
import { KoraPointShell } from '@/components/kora-points/common/kora-point-shell';
import { KoraPointProfileView } from '@/components/kora-points/profile/kora-point-profile-view';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    KoraPointProfile,
    FinancialHealth,
    PointConvention,
    PointDocument,
    PointAssignedUser,
} from '@/types/kora-points';

type Props = {
    point: KoraPointProfile;
    health: FinancialHealth;
    convention?: PointConvention;
    documents?: PointDocument[];
    assignedUsers?: PointAssignedUser[];
};

function ProfileSkeleton() {
    return (
        <div className="animate-pulse space-y-6">
            <Skeleton className="h-44 w-full rounded-3xl" />
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Skeleton className="h-64 rounded-2xl" />
                <Skeleton className="h-64 rounded-2xl" />
                <Skeleton className="h-64 rounded-2xl" />
            </div>
        </div>
    );
}

export default function AgentProfilePage({
    point,
    health,
    convention,
    documents,
    assignedUsers,
}: Props) {
    return (
        <>
            <Head title="Mon Point — KORA POINT" />
            <KoraPointShell
                point={point}
                health={health}
                activePage="my-point"
                onNavigate={handleAgentNavigate}
            >
                <Deferred
                    data={['convention', 'documents', 'assignedUsers']}
                    fallback={<ProfileSkeleton />}
                >
                    {convention && documents && assignedUsers ? (
                        <KoraPointProfileView
                            point={point}
                            health={health}
                            convention={convention}
                            documents={documents}
                            assignedUsers={assignedUsers}
                            onNavigateTab={(tab) => {
                                if (tab === 'settlements') {
                                    handleAgentNavigate('settlements');
                                }
                            }}
                        />
                    ) : (
                        <ProfileSkeleton />
                    )}
                </Deferred>
            </KoraPointShell>
        </>
    );
}
