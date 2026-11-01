import {
    Bell,
    CheckCheck,
    AlertTriangle,
    CheckCircle2,
    Info,
    Receipt,
    Ticket,
    Sliders,
    ExternalLink,
    Volume2,
    ShieldAlert,
    X,
    Radio,
    Sparkles,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type { KoraPointActivePage } from '../common/kora-point-shell';
import type { KoraNotificationItem} from '../mock/kora-point-mock-data';
import { KoraNotificationCategory } from '../mock/kora-point-mock-data';

type Props = {
    notifications: KoraNotificationItem[];
    onMarkAsRead: (uuid: string) => void;
    onMarkAllAsRead: () => void;
    onTriggerTestNotification: () => void;
    onNavigate: (page: KoraPointActivePage) => void;
};

export function KoraNotificationsView({
    notifications,
    onMarkAsRead,
    onMarkAllAsRead,
    onTriggerTestNotification,
    onNavigate,
}: Props) {
    const [selectedCategory, setSelectedCategory] = useState<string>('ALL');
    const [unreadOnly, setUnreadOnly] = useState(false);
    const [browserPushPermission, setBrowserPushPermission] = useState<'granted' | 'default' | 'denied'>('default');
    const [mockPushToast, setMockPushToast] = useState<KoraNotificationItem | null>(null);

    // Compute counts
    const unreadCount = useMemo(
        () => notifications.filter((n) => !n.read).length,
        [notifications],
    );

    // Filter notifications
    const filteredNotifications = useMemo(() => {
        let list = notifications;

        if (unreadOnly) {
            list = list.filter((n) => !n.read);
        }

        if (selectedCategory !== 'ALL') {
            list = list.filter((n) => n.category === selectedCategory);
        }

        return list;
    }, [notifications, unreadOnly, selectedCategory]);

    const handleEnableBrowserPush = () => {
        setBrowserPushPermission('granted');
        const simulatedToast: KoraNotificationItem = {
            uuid: `toast-${Date.now()}`,
            category: 'SYSTEM',
            level: 'success',
            title: 'Notifications push autorisées',
            description:
                'Vous recevrez désormais les alertes financières et opérationnelles critiques en temps réel.',
            created_at: new Date().toISOString(),
            date_label: 'À l’instant',
            read: false,
        };
        setMockPushToast(simulatedToast);
    };

    const handleTriggerTestPush = () => {
        onTriggerTestNotification();
        const testToast: KoraNotificationItem = {
            uuid: `test-${Date.now()}`,
            category: 'FINANCE',
            level: 'info',
            title: 'Simulation d’alerte système KORA',
            description:
                'Test de notification push navigateur réussi. Le canal de transmission est opérationnel.',
            created_at: new Date().toISOString(),
            date_label: 'À l’instant',
            read: false,
            action_label: 'Voir l’activité',
            action_target: 'dashboard',
        };
        setMockPushToast(testToast);
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex items-center gap-2.5">
                        <h1 className="text-xl font-black text-slate-900 sm:text-2xl dark:text-white">
                            Centre de Notifications & Alertes
                        </h1>
                        {unreadCount > 0 ? (
                            <span className="rounded-full bg-amber-500 px-2.5 py-0.5 text-xs font-black text-white">
                                {unreadCount} non lue{unreadCount > 1 ? 's' : ''}
                            </span>
                        ) : (
                            <KoraStatusBadge
                                label="À jour"
                                tone="emerald"
                                variant="solid"
                                className="hidden sm:inline-flex"
                            />
                        )}
                    </div>
                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Flux en direct des événements de réseau : réceptions de versements, seuils de plafond, ventes et remboursements.
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={onMarkAllAsRead}
                        disabled={unreadCount === 0}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition disabled:opacity-40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <CheckCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
                        <span>Tout marquer comme lu</span>
                    </button>
                </div>
            </div>

            {/* Browser Notification Permission Banner */}
            <div className="relative overflow-hidden rounded-2xl border border-gold/30 bg-gradient-to-r from-[var(--kora)] to-slate-900 p-5 text-white shadow-md">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3.5">
                        <div className="grid size-11 place-items-center rounded-xl bg-gold font-black text-kora shrink-0">
                            <Radio className="size-5" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="text-sm font-black text-white">
                                    Notifications push navigateur
                                </h3>
                                <span
                                    className={`rounded-full px-2 py-0.5 text-[0.625rem] font-black uppercase ${
                                        browserPushPermission === 'granted'
                                            ? 'bg-emerald-500/20 text-emerald-300'
                                            : 'bg-amber-500/20 text-amber-300'
                                    }`}
                                >
                                    {browserPushPermission === 'granted'
                                        ? 'Permission accordée'
                                        : 'En attente d’autorisation'}
                                </span>
                            </div>
                            <p className="mt-1 max-w-xl text-xs text-white/70">
                                Activez les alertes navigateur pour être immédiatement averti lors des dépassements de plafonds d’encours, des validations de reversement ou des litiges passagers.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 shrink-0">
                        {browserPushPermission !== 'granted' ? (
                            <button
                                type="button"
                                onClick={handleEnableBrowserPush}
                                className="inline-flex items-center gap-2 rounded-xl bg-gold px-4 py-2 text-xs font-black uppercase tracking-wider text-kora shadow-md transition hover:bg-gold/90"
                            >
                                <Bell className="size-3.5" />
                                <span>Activer sur ce poste</span>
                            </button>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                                <CheckCircle2 className="size-4" />
                                Notifications actives
                            </span>
                        )}

                        <button
                            type="button"
                            onClick={handleTriggerTestPush}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-xs font-bold text-white transition hover:bg-white/20"
                        >
                            <Volume2 className="size-3.5" />
                            <span>Alerte de test</span>
                        </button>
                    </div>
                </div>

                {/* Simulated Floating Browser Toast */}
                {mockPushToast && (
                    <div className="mt-4 flex items-center justify-between rounded-xl border border-gold/40 bg-slate-900/90 p-3 text-white backdrop-blur-md animate-in slide-in-from-top">
                        <div className="flex items-center gap-2.5">
                            <Sparkles className="size-4 text-gold shrink-0 animate-pulse" />
                            <div className="text-xs">
                                <strong className="font-bold text-gold">
                                    [Push Navigateur Démo] {mockPushToast.title}
                                </strong>
                                <span className="block text-[0.6875rem] text-slate-300">
                                    {mockPushToast.description}
                                </span>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setMockPushToast(null)}
                            className="rounded-lg p-1 text-slate-400 hover:text-white"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                )}
            </div>

            {/* Filter Tabs & Unread Toggle */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-800">
                <div className="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        onClick={() => setSelectedCategory('ALL')}
                        className={`rounded-xl px-3.5 py-1.5 text-xs font-black transition ${
                            selectedCategory === 'ALL'
                                ? 'bg-[var(--kora)] text-white shadow-sm'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        Toutes ({notifications.length})
                    </button>

                    <button
                        type="button"
                        onClick={() => setSelectedCategory('FINANCE')}
                        className={`inline-flex items-center gap-1 rounded-xl px-3.5 py-1.5 text-xs font-black transition ${
                            selectedCategory === 'FINANCE'
                                ? 'bg-[var(--kora)] text-white shadow-sm'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        <Receipt className="size-3.5" />
                        <span>Finance & Versements</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setSelectedCategory('THRESHOLD')}
                        className={`inline-flex items-center gap-1 rounded-xl px-3.5 py-1.5 text-xs font-black transition ${
                            selectedCategory === 'THRESHOLD'
                                ? 'bg-[var(--kora)] text-white shadow-sm'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        <AlertTriangle className="size-3.5" />
                        <span>Seuils & Plafonds</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setSelectedCategory('SALES')}
                        className={`inline-flex items-center gap-1 rounded-xl px-3.5 py-1.5 text-xs font-black transition ${
                            selectedCategory === 'SALES'
                                ? 'bg-[var(--kora)] text-white shadow-sm'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        <Ticket className="size-3.5" />
                        <span>Billetterie</span>
                    </button>

                    <button
                        type="button"
                        onClick={() => setSelectedCategory('SYSTEM')}
                        className={`inline-flex items-center gap-1 rounded-xl px-3.5 py-1.5 text-xs font-black transition ${
                            selectedCategory === 'SYSTEM'
                                ? 'bg-[var(--kora)] text-white shadow-sm'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        <Info className="size-3.5" />
                        <span>Système</span>
                    </button>
                </div>

                <label className="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300">
                    <input
                        type="checkbox"
                        checked={unreadOnly}
                        onChange={(e) => setUnreadOnly(e.target.checked)}
                        className="rounded border-slate-300 text-gold focus:ring-gold"
                    />
                    <span>Uniquement les non lues</span>
                </label>
            </div>

            {/* Notifications List */}
            <div className="space-y-3">
                {filteredNotifications.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900">
                        <Bell className="mx-auto size-12 text-slate-300 dark:text-slate-600" />
                        <h3 className="mt-3 text-sm font-bold text-slate-800 dark:text-slate-200">
                            Aucune notification
                        </h3>
                        <p className="mt-1 text-xs text-slate-500">
                            Aucune alerte ne correspond aux filtres appliqués.
                        </p>
                    </div>
                ) : (
                    filteredNotifications.map((notif) => {
                        const isUnread = !notif.read;

                        return (
                            <div
                                key={notif.uuid}
                                className={`flex flex-col gap-3 rounded-2xl border p-4 transition sm:flex-row sm:items-center sm:justify-between ${
                                    isUnread
                                        ? 'border-amber-200 bg-amber-50/40 dark:border-amber-900/30 dark:bg-amber-950/10'
                                        : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'
                                }`}
                            >
                                <div className="flex items-start gap-3.5">
                                    {/* Icon */}
                                    <div
                                        className={`grid size-10 place-items-center rounded-xl shrink-0 ${
                                            notif.level === 'critical'
                                                ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400'
                                                : notif.level === 'warning'
                                                ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                                : notif.level === 'success'
                                                ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                                : 'bg-blue-500/15 text-blue-600 dark:text-blue-400'
                                        }`}
                                    >
                                        {notif.category === 'FINANCE' ? (
                                            <Receipt className="size-5" />
                                        ) : notif.category === 'THRESHOLD' ? (
                                            <AlertTriangle className="size-5" />
                                        ) : notif.category === 'SALES' ? (
                                            <Ticket className="size-5" />
                                        ) : (
                                            <Info className="size-5" />
                                        )}
                                    </div>

                                    {/* Text content */}
                                    <div>
                                        <div className="flex items-center gap-2">
                                            {isUnread && (
                                                <span className="size-2 rounded-full bg-amber-500 shrink-0" />
                                            )}
                                            <h4 className="text-xs font-black text-slate-900 dark:text-white">
                                                {notif.title}
                                            </h4>
                                            <span className="text-[0.625rem] text-slate-400">
                                                • {notif.date_label}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                            {notif.description}
                                        </p>
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="flex items-center gap-2 pl-13 sm:pl-0 shrink-0">
                                    {notif.action_label && notif.action_target && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                onMarkAsRead(notif.uuid);
                                                onNavigate(notif.action_target as KoraPointActivePage);
                                            }}
                                            className="inline-flex items-center gap-1.5 rounded-xl bg-gold px-3 py-1.5 text-xs font-black text-kora shadow-sm hover:bg-gold/90 transition"
                                        >
                                            <span>{notif.action_label}</span>
                                            <ExternalLink className="size-3" />
                                        </button>
                                    )}

                                    {isUnread && (
                                        <button
                                            type="button"
                                            onClick={() => onMarkAsRead(notif.uuid)}
                                            className="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-[0.6875rem] font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                        >
                                            Marquer lu
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}
