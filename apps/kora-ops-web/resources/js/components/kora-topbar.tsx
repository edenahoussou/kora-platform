import { router } from '@inertiajs/react';
import { Menu, Moon, Search, Sun } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { KoraCorridorFlagPicker } from '@/components/kora-corridor-flag-picker';
import { KoraUserMenuContent } from '@/components/kora-user-menu';
import { OpsNotificationDrawer } from '@/components/ops-notification-drawer';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { getOpsMessaging, listenForOpsPush } from '@/lib/firebase-messaging';
import type { OpsFirebaseConfig } from '@/lib/firebase-messaging';
import { logout } from '@/routes';
import {
    unreadCount as notificationUnreadCount,
    webPushConfiguration,
} from '@/routes/api/v1/notifications';
import { update as updatePushPreference } from '@/routes/settings/notifications/push';
import type { User } from '@/types';

type Props = {
    user?: User;
    onMenuClick?: () => void;
};

export function KoraTopbar({ user, onMenuClick }: Props) {
    const [query, setQuery] = useState('');
    const [unreadCount, setUnreadCount] = useState(0);
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const refreshUnreadCount = useCallback(async () => {
        try {
            const response = await fetch(notificationUnreadCount.url(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const result = (await response.json()) as {
                data: { unread: number };
            };
            setUnreadCount(result.data.unread);
        } catch {
            // A transient count failure leaves the last known badge in place.
        }
    }, []);

    useEffect(() => {
        const initialRefresh = window.setTimeout(
            () => void refreshUnreadCount(),
            0,
        );
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                void refreshUnreadCount();
            }
        }, 30_000);
        const onFocus = () => void refreshUnreadCount();
        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                void refreshUnreadCount();
            }
        };
        window.addEventListener('focus', onFocus);
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            window.clearTimeout(initialRefresh);
            window.clearInterval(timer);
            window.removeEventListener('focus', onFocus);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [refreshUnreadCount]);

    useEffect(() => {
        if (
            !('Notification' in window) ||
            Notification.permission !== 'granted'
        ) {
            return;
        }

        let unsubscribe: (() => void) | undefined;
        let cancelled = false;
        void fetch(webPushConfiguration.url(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<{
                          configured: boolean;
                          firebase: OpsFirebaseConfig | null;
                      }>)
                    : null,
            )
            .then(async (config) => {
                if (!config?.configured || !config.firebase || cancelled) {
                    return;
                }

                const messaging = await getOpsMessaging(config.firebase);

                if (messaging && !cancelled) {
                    unsubscribe = listenForOpsPush(
                        messaging,
                        () => void refreshUnreadCount(),
                    );
                }
            })
            .catch(() => undefined);

        return () => {
            cancelled = true;
            unsubscribe?.();
        };
    }, [refreshUnreadCount]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();

        if (query.trim()) {
            router.visit(`/search?q=${encodeURIComponent(query)}`);
        }
    };

    const handleLogout = async () => {
        const fingerprint = window.localStorage.getItem(
            'kora-ops-web-push-device',
        );

        if (
            fingerprint &&
            window.localStorage.getItem('kora-ops-web-push-enabled') === 'true'
        ) {
            const xsrf = document.cookie
                .split('; ')
                .find((part) => part.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);

            try {
                await fetch(updatePushPreference.url(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
                    },
                    body: JSON.stringify({
                        device_fingerprint: fingerprint,
                        device_name: 'Navigateur Web',
                        push_token: null,
                    }),
                });
            } catch {
                // Logout still proceeds if best-effort token cleanup is offline.
            }
        }

        router.post(
            logout().url,
            {},
            {
                onFinish: () => {
                    window.localStorage.removeItem('kora-ops-web-push-enabled');
                    router.flushAll();
                },
            },
        );
    };

    const getInitials = useInitials();
    const initials = user ? getInitials(user.name) : 'KO';

    return (
        <header className="kora-topbar sticky top-0 z-30 flex h-[4.75rem] items-center gap-4 border-b border-border bg-background/96 px-[clamp(1rem,3vw,1.875rem)] backdrop-blur-xl">
            {/* Mobile menu toggle */}
            <button
                type="button"
                onClick={onMenuClick}
                className="grid size-[2.625rem] place-items-center rounded-[0.6875rem] border border-border bg-background text-foreground lg:hidden"
                aria-label="Ouvrir la navigation"
            >
                <Menu className="size-5" />
            </button>

            {/* Search — hidden on mobile */}
            <form
                onSubmit={handleSearch}
                className="relative hidden max-w-[28.75rem] flex-1 md:block"
            >
                <Search className="absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted-foreground" />
                <input
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Rechercher un billet, un départ, un colis…"
                    className="h-[2.625rem] w-full rounded-[0.75rem] border border-border bg-muted pr-4 pl-10 text-sm text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                />
            </form>

            <div className="flex-1" />

            {/* Corridor transnational KORA (Bénin · Togo · Ghana · Côte d'Ivoire) */}
            <KoraCorridorFlagPicker />

            <OpsNotificationDrawer
                unreadCount={unreadCount}
                onUnreadCountChange={setUnreadCount}
            />

            {/* Theme toggle */}
            <button
                type="button"
                onClick={() =>
                    updateAppearance(
                        resolvedAppearance === 'dark' ? 'light' : 'dark',
                    )
                }
                className="grid size-[2.625rem] place-items-center rounded-[0.75rem] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label={
                    resolvedAppearance === 'dark'
                        ? 'Passer en mode clair'
                        : 'Passer en mode sombre'
                }
            >
                {resolvedAppearance === 'dark' ? (
                    <Sun className="size-5" />
                ) : (
                    <Moon className="size-5" />
                )}
            </button>

            {/* Profile Dropdown KORA OPS unifié */}
            {user && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="group flex items-center gap-2.5 rounded-[0.8125rem] p-1 text-left transition-all duration-200 hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-gold/40 focus-visible:outline-none"
                            data-test="profile-button"
                        >
                            <Avatar className="size-9 rounded-[0.625rem] shadow-xs ring-1 ring-border transition-transform duration-200 group-hover:scale-105">
                                <AvatarImage
                                    src={user.avatar ?? undefined}
                                    alt={user.name}
                                />
                                <AvatarFallback className="rounded-[0.625rem] bg-gold text-[0.75rem] font-black text-[var(--kora)]">
                                    {initials}
                                </AvatarFallback>
                            </Avatar>
                            <span className="hidden sm:block">
                                <strong className="block text-[0.75rem] font-extrabold tracking-tight text-foreground">
                                    {user.name}
                                </strong>
                                <small className="block max-w-[140px] truncate text-[0.625rem] text-muted-foreground">
                                    {user.email}
                                </small>
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        sideOffset={8}
                        className="rounded-2xl border-border bg-card p-1.5 shadow-[0_20px_50px_rgba(0,29,78,0.2)]"
                    >
                        <KoraUserMenuContent
                            user={user}
                            onLogout={handleLogout}
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </header>
    );
}
