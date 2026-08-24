import { Head } from '@inertiajs/react';
import {
    ActivityPanel,
    PriorityPanel,
} from '@/components/dashboard/activity-panel';
import { dashboardMetrics } from '@/components/dashboard/dashboard-data';
import { DashboardHero } from '@/components/dashboard/dashboard-hero';
import { MetricGrid } from '@/components/dashboard/metric-grid';
import { QuickActions } from '@/components/dashboard/quick-actions';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Centre de pilotage" />

            <main className="w-full max-w-full overflow-x-hidden">
                <div className="flex flex-col gap-5 pb-8">
                    <div className="kora-breadcrumb text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Centre de pilotage
                    </div>

                    <DashboardHero />

                    <MetricGrid metrics={dashboardMetrics} />

                    <section className="grid grid-flow-row-dense grid-cols-1 gap-4 lg:grid-cols-5">
                        <div className="lg:col-span-3">
                            <ActivityPanel />
                        </div>
                        <div className="lg:col-span-2">
                            <PriorityPanel />
                        </div>
                    </section>

                    <QuickActions />
                </div>
            </main>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
