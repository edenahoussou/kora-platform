import type { Meta, StoryObj } from '@storybook/react';
import { useState } from 'react';
import { KoraNotificationsView } from '@/components/kora-points/notifications/kora-notifications-view';
import { KoraGlobalSearchDialog } from '@/components/kora-points/search/kora-global-search-dialog';
import {
    MOCK_NOTIFICATIONS,
    MOCK_POINT_SALES,
    MOCK_ADMIN_NETWORK_POINTS,
    MOCK_ADMIN_ORGANIZATIONS,
    MOCK_POINT_SETTLEMENTS,
} from '@/components/kora-points/mock/kora-point-mock-data';

const meta: Meta<typeof KoraNotificationsView> = {
    title: 'KORA POINT VOYAGE / 12 — Recherche Globale & Notifications',
    component: KoraNotificationsView,
    parameters: {
        layout: 'padded',
    },
};

export default meta;
type Story = StoryObj<typeof KoraNotificationsView>;

function NotificationsInteractiveStory() {
    const [notifications, setNotifications] = useState(MOCK_NOTIFICATIONS);

    return (
        <div className="mx-auto max-w-5xl">
            <KoraNotificationsView
                notifications={notifications}
                onMarkAsRead={(uuid) =>
                    setNotifications((prev) =>
                        prev.map((n) => (n.uuid === uuid ? { ...n, read: true } : n)),
                    )
                }
                onMarkAllAsRead={() =>
                    setNotifications((prev) => prev.map((n) => ({ ...n, read: true })))
                }
                onTriggerTestNotification={() => {
                    const testItem = {
                        uuid: `test-${Date.now()}`,
                        category: 'FINANCE' as const,
                        level: 'info' as const,
                        title: 'Alerte push reçue en temps réel',
                        description: 'La validation du reversement REV-2026-0043 a été effectuée avec succès.',
                        created_at: new Date().toISOString(),
                        date_label: 'À l’instant',
                        read: false,
                        action_label: 'Consulter',
                        action_target: 'settlements',
                    };
                    setNotifications((prev) => [testItem, ...prev]);
                }}
                onNavigate={(page) => alert(`Navigation demandée vers : ${page}`)}
            />
        </div>
    );
}

export const CentreNotifications: Story = {
    render: () => <NotificationsInteractiveStory />,
};

export const RechercheGlobaleSpotlight: StoryObj<typeof KoraGlobalSearchDialog> = {
    render: () => {
        const [isOpen, setIsOpen] = useState(true);

        return (
            <div className="p-8">
                <button
                    type="button"
                    onClick={() => setIsOpen(true)}
                    className="inline-flex items-center gap-2 rounded-xl bg-gold px-4 py-2 text-xs font-black text-kora uppercase"
                >
                    <span>Ouvrir la recherche globale (Ctrl+K)</span>
                </button>

                <KoraGlobalSearchDialog
                    isOpen={isOpen}
                    onClose={() => setIsOpen(false)}
                    sales={MOCK_POINT_SALES}
                    points={MOCK_ADMIN_NETWORK_POINTS}
                    organizations={MOCK_ADMIN_ORGANIZATIONS}
                    settlements={MOCK_POINT_SETTLEMENTS}
                    onNavigate={(page) => {
                        alert(`Navigation vers : ${page}`);
                        setIsOpen(false);
                    }}
                />
            </div>
        );
    },
};
