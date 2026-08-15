import { router } from '@inertiajs/react';
import { Bell, Menu, Moon, Search, Sun } from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user?: User;
    onMenuClick?: () => void;
};

export function KoraTopbar({ user, onMenuClick }: Props) {
    const [query, setQuery] = useState('');
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();

        if (query.trim()) {
            router.visit(`/search?q=${encodeURIComponent(query)}`);
        }
    };

    const handleLogout = () => {
        router.flushAll();
    };

    const initials = user
        ? user.name
              .split(' ')
              .map((n) => n[0])
              .slice(0, 2)
              .join('')
              .toUpperCase()
        : 'KO';

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

            {/* Language switch */}
            <button
                type="button"
                className="grid size-[2.625rem] place-items-center rounded-[0.6875rem] text-[0.75rem] font-bold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label="Changer de langue"
            >
                EN
            </button>

            {/* Alerts */}
            <button
                type="button"
                className="relative grid size-[2.625rem] place-items-center rounded-[0.75rem] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                aria-label="Alertes"
            >
                <Bell className="size-5" />
                <span className="absolute top-2 right-2 size-[7px] rounded-full border-2 border-background bg-gold" />
            </button>

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

            {/* Profile — avatar and name separated */}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="flex items-center gap-2.5 rounded-[0.8125rem] px-1 py-1 text-left transition-colors hover:bg-muted"
                        data-test="profile-button"
                    >
                        <span className="grid size-9 place-items-center rounded-[0.625rem] bg-gold text-[0.75rem] font-black text-[var(--kora)]">
                            {initials}
                        </span>
                        <span className="hidden sm:block">
                            <strong className="block text-[0.75rem] font-bold text-foreground">
                                {user?.name ?? 'Utilisateur'}
                            </strong>
                            <small className="block text-[0.625rem] text-muted-foreground">
                                {user?.email ?? ''}
                            </small>
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="w-64 rounded-[0.9375rem]"
                >
                    <DropdownMenuLabel className="font-normal">
                        <div className="flex flex-col gap-0.5">
                            <span className="text-sm font-bold text-foreground">
                                {user?.name ?? 'Utilisateur'}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {user?.email ?? ''}
                            </span>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <a href={edit().url} className="cursor-pointer">
                            Paramètres du profil
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <a
                            href={logout().url}
                            className="cursor-pointer text-destructive"
                            onClick={handleLogout}
                            data-test="logout-button"
                        >
                            Se déconnecter
                        </a>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
}
