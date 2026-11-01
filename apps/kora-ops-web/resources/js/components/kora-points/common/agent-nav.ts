import { router } from '@inertiajs/react';
import type { KoraPointActivePage } from './kora-point-shell';

/**
 * Common navigation handler for real KORA POINT agent pages.
 * Triggers standard Inertia visits rather than client-only state transitions.
 */
export function handleAgentNavigate(page: KoraPointActivePage | string): void {
    switch (page) {
        case 'dashboard':
            router.visit('/kora-point');
            break;
        case 'sale':
            router.visit('/kora-point/sale');
            break;
        case 'cash':
            router.visit('/kora-point/cash');
            break;
        case 'tickets':
            router.visit('/kora-point/tickets');
            break;
        case 'my-point':
        case 'profile':
            router.visit('/kora-point/profile');
            break;
        case 'commissions':
            router.visit('/kora-point/commissions');
            break;
        case 'settlements':
            router.visit('/kora-point/settlements');
            break;
        case 'refunds':
            router.visit('/kora-point/refunds');
            break;
        default:
            router.visit('/kora-point');
            break;
    }
}
