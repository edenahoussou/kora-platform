import { router } from '@inertiajs/react';
import {
    BellRing,
    Check,
    ChevronDown,
    CircleAlert,
    LoaderCircle,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    index as notificationsIndex,
    read as markNotificationRead,
    readAll as markAllNotificationsRead,
    unreadCount as notificationUnreadCount,
} from '@/routes/api/v1/notifications';

type NotificationItem = {
    uuid: string;
    type: string;
    severity: 'info' | 'warning' | 'critical';
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
    action_url: string | null;
};

type Listing = {
    data: NotificationItem[];
    meta: { current_page: number; last_page: number; unread_count: number };
};

async function request<T>(url: string, init?: RequestInit): Promise<T> {
    const xsrf = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        ...init,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
            ...init?.headers,
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return (await response.json()) as T;
}

function relativeTime(value: string): string {
    const minutes = Math.round(
        (Date.now() - new Date(value).getTime()) / 60000,
    );
    const formatter = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });

    if (Math.abs(minutes) < 60) {
        return formatter.format(-minutes, 'minute');
    }

    const hours = Math.round(minutes / 60);

    if (Math.abs(hours) < 24) {
        return formatter.format(-hours, 'hour');
    }

    return formatter.format(-Math.round(hours / 24), 'day');
}

export function OpsNotificationDrawer({
    unreadCount,
    onUnreadCountChange,
}: {
    unreadCount: number;
    onUnreadCountChange: (count: number) => void;
}) {
    const [open, setOpen] = useState(false);
    const [filter, setFilter] = useState<'all' | 'unread'>('all');
    const [items, setItems] = useState<NotificationItem[]>([]);
    const [page, setPage] = useState(0);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const displayedUnreadCount = unreadCount;

    const refreshCount = useCallback(async () => {
        try {
            const result = await request<{ data: { unread: number } }>(
                notificationUnreadCount.url(),
            );
            onUnreadCountChange(result.data.unread);
        } catch {
            // Keep the last known count; a transient request failure must not hide the inbox.
        }
    }, [onUnreadCountChange]);

    const load = useCallback(
        async (nextPage: number, replace: boolean) => {
            setLoading(true);
            setError(false);

            try {
                const result = await request<Listing>(
                    notificationsIndex.url({
                        query: {
                            per_page: 20,
                            page: nextPage,
                            ...(filter === 'unread' ? { unread: true } : {}),
                        },
                    }),
                );
                setItems((previous) =>
                    replace ? result.data : [...previous, ...result.data],
                );
                setPage(result.meta.current_page);
                setLastPage(result.meta.last_page);
                onUnreadCountChange(result.meta.unread_count);
            } catch {
                setError(true);
            } finally {
                setLoading(false);
            }
        },
        [filter, onUnreadCountChange],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        const initialLoad = window.setTimeout(() => {
            void load(1, true);
            void refreshCount();
        }, 0);

        return () => window.clearTimeout(initialLoad);
    }, [open, filter, load, refreshCount]);

    const markAllRead = async () => {
        try {
            await request(markAllNotificationsRead.url(), {
                method: 'POST',
                body: '{}',
            });
            onUnreadCountChange(0);
            setItems((current) =>
                current.map((item) => ({ ...item, is_read: true })),
            );
        } catch {
            setError(true);
        }
    };

    const openNotification = async (item: NotificationItem) => {
        try {
            if (!item.is_read) {
                await request(markNotificationRead.url(item.uuid), {
                    method: 'POST',
                    body: '{}',
                });
                setItems((current) =>
                    current.map((entry) =>
                        entry.uuid === item.uuid
                            ? { ...entry, is_read: true }
                            : entry,
                    ),
                );
                onUnreadCountChange(Math.max(0, unreadCount - 1));
            }

            setOpen(false);

            if (item.action_url) {
                router.visit(item.action_url);
            }
        } catch {
            setError(true);
        }
    };

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <button
                type="button"
                aria-label={
                    displayedUnreadCount
                        ? `Notifications, ${displayedUnreadCount} non lue${displayedUnreadCount > 1 ? 's' : ''}`
                        : 'Notifications'
                }
                aria-expanded={open}
                onClick={() => setOpen(true)}
                className="relative grid size-[2.625rem] place-items-center rounded-[0.75rem] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                data-test="notifications-trigger"
            >
                <BellRing className="size-5" />
                {displayedUnreadCount > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 grid min-h-4 min-w-4 place-items-center rounded-full border-2 border-background bg-destructive px-1 text-[9px] leading-none font-bold text-white">
                        {displayedUnreadCount > 99
                            ? '99+'
                            : displayedUnreadCount}
                    </span>
                )}
            </button>
            <SheetContent
                side="right"
                overlayClassName="bg-black/35 backdrop-blur-[3px]"
                className="w-full gap-0 overflow-hidden p-0 sm:w-[clamp(420px,32vw,640px)] sm:max-w-none"
                aria-describedby="ops-notifications-description"
            >
                <SheetHeader className="border-b border-border px-5 py-5 sm:px-6">
                    <div className="flex flex-wrap items-center justify-between gap-2 pr-7">
                        <SheetTitle className="text-xl font-bold">
                            Notifications
                        </SheetTitle>
                        {displayedUnreadCount > 0 && (
                            <button
                                type="button"
                                onClick={() => void markAllRead()}
                                className="text-xs font-semibold text-primary hover:underline"
                            >
                                Tout marquer comme lu
                            </button>
                        )}
                    </div>
                    <SheetDescription id="ops-notifications-description">
                        Les alertes opérationnelles de votre périmètre.
                    </SheetDescription>
                    <div
                        className="mt-3 flex gap-2"
                        role="tablist"
                        aria-label="Filtrer les notifications"
                    >
                        {(['all', 'unread'] as const).map((value) => (
                            <button
                                key={value}
                                type="button"
                                role="tab"
                                aria-selected={filter === value}
                                onClick={() => setFilter(value)}
                                className={`rounded-full px-3.5 py-2 text-sm font-semibold transition-colors ${filter === value ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground'}`}
                            >
                                {value === 'all'
                                    ? 'Toutes'
                                    : `Non lues${displayedUnreadCount ? ` (${displayedUnreadCount})` : ''}`}
                            </button>
                        ))}
                    </div>
                </SheetHeader>
                <div className="min-h-0 flex-1 overflow-y-auto">
                    {loading && items.length === 0 && (
                        <div
                            className="space-y-5 px-5 py-6"
                            aria-label="Chargement des notifications"
                        >
                            {[0, 1, 2, 3].map((key) => (
                                <div key={key} className="flex gap-3">
                                    <div className="size-10 animate-pulse rounded-full bg-muted" />
                                    <div className="flex-1 space-y-2">
                                        <div className="h-4 w-3/4 animate-pulse rounded bg-muted" />
                                        <div className="h-3 w-1/3 animate-pulse rounded bg-muted" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    {error && (
                        <div className="m-5 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm">
                            <p className="font-semibold text-destructive">
                                Impossible de charger les notifications.
                            </p>
                            <button
                                type="button"
                                className="mt-2 underline"
                                onClick={() => void load(1, true)}
                            >
                                Réessayer
                            </button>
                        </div>
                    )}
                    {!loading && !error && items.length === 0 && (
                        <div className="flex min-h-72 flex-col items-center justify-center px-8 text-center">
                            <span className="mb-4 grid size-14 place-items-center rounded-2xl bg-muted text-muted-foreground">
                                <Check className="size-6" />
                            </span>
                            <p className="font-bold">
                                {filter === 'unread'
                                    ? 'Tout est à jour'
                                    : 'Aucune notification'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {filter === 'unread'
                                    ? 'Vous n’avez aucune notification non lue.'
                                    : 'Les nouvelles alertes apparaîtront ici.'}
                            </p>
                        </div>
                    )}
                    <ul className="divide-y divide-border">
                        {items.map((item) => (
                            <li key={item.uuid}>
                                <button
                                    type="button"
                                    onClick={() => void openNotification(item)}
                                    className={`flex w-full gap-3 px-5 py-4 text-left transition-colors hover:bg-muted/60 sm:px-6 ${item.is_read ? 'opacity-75' : 'bg-primary/[0.035]'}`}
                                >
                                    <span
                                        className={`mt-0.5 grid size-10 shrink-0 place-items-center rounded-full ${item.severity === 'critical' ? 'bg-destructive/10 text-destructive' : item.severity === 'warning' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-primary/10 text-primary'}`}
                                    >
                                        {item.severity === 'critical' ? (
                                            <CircleAlert className="size-5" />
                                        ) : (
                                            <BellRing className="size-4" />
                                        )}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-start justify-between gap-2">
                                            <span className="line-clamp-2 text-sm leading-5 font-semibold text-foreground">
                                                {item.title}
                                            </span>
                                            {!item.is_read && (
                                                <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                            )}
                                        </span>
                                        <span className="mt-1 line-clamp-2 block text-sm leading-5 text-muted-foreground">
                                            {item.message}
                                        </span>
                                        <span className="mt-2 block text-xs text-muted-foreground">
                                            {relativeTime(item.created_at)}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                    {page < lastPage && !loading && items.length > 0 && (
                        <div className="p-5 text-center">
                            <button
                                type="button"
                                onClick={() => void load(page + 1, false)}
                                className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-semibold hover:bg-muted"
                            >
                                Afficher la suite{' '}
                                <ChevronDown className="size-4" />
                            </button>
                        </div>
                    )}
                    {loading && items.length > 0 && (
                        <div className="flex justify-center p-4">
                            <LoaderCircle className="size-5 animate-spin text-muted-foreground" />
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}
