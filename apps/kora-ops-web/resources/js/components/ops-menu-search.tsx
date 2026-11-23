import { router } from '@inertiajs/react';
import { ArrowUpRight, History, Search, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { OpsNavEntry } from '@/hooks/use-ops-navigation';
import { cn } from '@/lib/utils';

function normalizeLabel(value: string) {
    return value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
}

const recentLimit = 8;

function readRecentMenus(key: string) {
    try {
        const stored: unknown = JSON.parse(
            window.localStorage.getItem(key) ?? '[]',
        );

        if (Array.isArray(stored)) {
            return stored
                .filter((item): item is string => typeof item === 'string')
                .slice(0, recentLimit);
        }
    } catch {
        // La recherche reste disponible si le stockage local est indisponible.
    }

    return [];
}

function ShortcutKey({ children }: { children: string }) {
    return (
        <kbd className="inline-flex min-w-5 items-center justify-center rounded border border-border bg-background px-1.5 py-0.5 font-sans text-xs leading-none text-foreground">
            {children}
        </kbd>
    );
}

export function OpsMenuSearch({
    navigation,
    userUuid,
}: {
    navigation: OpsNavEntry[];
    userUuid?: string;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [selection, setSelection] = useState(0);
    const [recent, setRecent] = useState<string[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const listId = useId();
    const storageKey = userUuid ? `kora-ops-recent-menus:${userUuid}` : null;
    const terms = normalizeLabel(query).trim().split(/\s+/).filter(Boolean);
    const isSearching = terms.length > 0;

    const destinations = navigation.flatMap((entry) =>
        (entry.children ?? [entry]).map((item) => ({
            title: item.title,
            href: item.href,
            group: entry.children ? entry.title : undefined,
            Icon: entry.icon,
        })),
    );
    const results = isSearching
        ? destinations.filter(({ title, group }) => {
              const label = normalizeLabel(group ? `${title} ${group}` : title);

              return terms.every((term) => label.includes(term));
          })
        : recent.flatMap((href) => {
              const item = destinations.find(
                  (destination) => destination.href === href,
              );

              return item ? [item] : [];
          });
    const activeIndex = Math.min(selection, results.length - 1);

    const changeOpen = (next: boolean) => {
        if (next && storageKey) {
            setRecent(readRecentMenus(storageKey));
        }

        setOpen(next);

        if (!next) {
            setQuery('');
            setSelection(0);
        }
    };

    useEffect(() => {
        const handleShortcut = (event: globalThis.KeyboardEvent) => {
            if (
                event.defaultPrevented ||
                event.repeat ||
                event.isComposing ||
                event.altKey ||
                event.shiftKey ||
                (!event.ctrlKey && !event.metaKey) ||
                event.key.toLowerCase() !== 'k' ||
                (!open &&
                    event.target instanceof Element &&
                    event.target.closest('[role="dialog"]'))
            ) {
                return;
            }

            event.preventDefault();

            if (open) {
                inputRef.current?.focus();
            } else {
                triggerRef.current?.click();
            }
        };

        window.addEventListener('keydown', handleShortcut);

        return () => window.removeEventListener('keydown', handleShortcut);
    }, [open]);

    const visit = (href: string) => {
        if (storageKey) {
            try {
                const updated = [
                    href,
                    ...recent.filter((item) => item !== href),
                ].slice(0, recentLimit);
                window.localStorage.setItem(
                    storageKey,
                    JSON.stringify(updated),
                );
            } catch {
                // Une erreur de stockage ne doit pas bloquer le lien choisi.
            }
        }

        changeOpen(false);
        router.visit(href);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (activeIndex < 0 || event.nativeEvent.isComposing) {
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            visit(results[activeIndex].href);
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const offset = event.key === 'ArrowDown' ? 1 : -1;
            const next =
                (activeIndex + offset + results.length) % results.length;

            setSelection(next);
            document
                .getElementById(`${listId}-${next}`)
                ?.scrollIntoView({ block: 'nearest' });
        }
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <button
                    ref={triggerRef}
                    type="button"
                    aria-label="Rechercher un menu"
                    aria-keyshortcuts="Control+K Meta+K"
                    className="flex h-[2.625rem] w-[2.625rem] shrink-0 cursor-pointer items-center justify-center gap-2.5 rounded-xl border border-border bg-muted text-sm text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:min-w-0 md:flex-1 md:justify-start md:px-3.5"
                >
                    <Search aria-hidden="true" className="size-5 shrink-0" />
                    <span className="hidden truncate md:inline">
                        Rechercher un menu…
                    </span>
                    <span
                        aria-hidden="true"
                        className="ml-auto hidden shrink-0 gap-1 md:flex"
                    >
                        <ShortcutKey>Ctrl / ⌘</ShortcutKey>
                        <ShortcutKey>K</ShortcutKey>
                    </span>
                </button>
            </DialogTrigger>

            <DialogPortal>
                <DialogOverlay className="bg-black/45 backdrop-blur-sm" />
            </DialogPortal>
            <DialogContent
                overlay={false}
                showClose={false}
                className="flex max-h-[min(35rem,85dvh)] flex-col gap-0 overflow-hidden rounded-xl border-border bg-card p-0 sm:max-w-xl"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    inputRef.current?.focus();
                }}
            >
                <DialogTitle className="sr-only">
                    Rechercher un menu
                </DialogTitle>
                <DialogDescription className="sr-only">
                    Saisissez un nom de menu ou sélectionnez un accès récent.
                    Utilisez les flèches pour parcourir les résultats, Entrée
                    pour ouvrir et Échap pour fermer.
                </DialogDescription>

                <div className="flex shrink-0 items-center gap-3 border-b border-border px-4">
                    <Search
                        aria-hidden="true"
                        className="size-5 shrink-0 text-muted-foreground"
                    />
                    <input
                        ref={inputRef}
                        type="text"
                        role="combobox"
                        aria-label="Rechercher un menu"
                        aria-autocomplete="list"
                        aria-expanded={results.length > 0}
                        aria-controls={listId}
                        aria-activedescendant={
                            activeIndex >= 0
                                ? `${listId}-${activeIndex}`
                                : undefined
                        }
                        placeholder="Rechercher un menu…"
                        className="h-14 min-w-0 flex-1 bg-transparent text-base text-foreground outline-none placeholder:text-muted-foreground"
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setSelection(0);
                        }}
                        onKeyDown={handleKeyDown}
                    />
                    <DialogClose asChild>
                        <button
                            type="button"
                            aria-label="Fermer la recherche"
                            className="grid size-8 shrink-0 cursor-pointer place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <X aria-hidden="true" className="size-4" />
                        </button>
                    </DialogClose>
                </div>

                <div
                    className="min-h-0 overflow-y-auto overscroll-contain px-2 pt-2 pb-4"
                    data-menu-results
                >
                    {!isSearching && results.length > 0 && (
                        <p className="flex items-center gap-2 px-3 pt-2 pb-1 text-xs font-semibold text-muted-foreground">
                            <History aria-hidden="true" className="size-3.5" />
                            Accès récents
                        </p>
                    )}
                    <div
                        id={listId}
                        role="listbox"
                        aria-label="Menus disponibles"
                        hidden={results.length === 0}
                    >
                        {results.map(({ title, href, group, Icon }, index) => (
                            <button
                                key={`${group ?? ''}:${href}:${title}`}
                                id={`${listId}-${index}`}
                                type="button"
                                role="option"
                                aria-selected={index === activeIndex}
                                tabIndex={-1}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => visit(href)}
                                className={cn(
                                    'flex min-h-12 w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-left text-foreground hover:bg-muted',
                                    index === activeIndex &&
                                        'bg-gold/15 hover:bg-gold/15',
                                )}
                            >
                                <span className="grid size-8 shrink-0 place-items-center rounded-lg border border-border bg-background">
                                    <Icon
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-semibold">
                                        {title}
                                    </span>
                                    {group && (
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {group}
                                        </span>
                                    )}
                                </span>
                                <ArrowUpRight
                                    aria-hidden="true"
                                    className="size-4 shrink-0 text-muted-foreground"
                                />
                            </button>
                        ))}
                    </div>
                    {results.length === 0 && (
                        <p
                            role="status"
                            className="px-4 py-7 text-center text-sm text-muted-foreground"
                        >
                            {isSearching
                                ? 'Aucun menu trouvé.'
                                : 'Vos menus récemment ouverts apparaîtront ici.'}
                        </p>
                    )}
                </div>

                <footer
                    aria-hidden="true"
                    className="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 border-t border-border bg-muted/50 px-4 py-3 text-xs text-muted-foreground"
                >
                    <span className="inline-flex items-center gap-1">
                        <ShortcutKey>↑</ShortcutKey>
                        <ShortcutKey>↓</ShortcutKey>
                        Naviguer
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <ShortcutKey>Entrée</ShortcutKey>
                        Ouvrir
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <ShortcutKey>Échap</ShortcutKey>
                        Fermer
                    </span>
                </footer>
            </DialogContent>
        </Dialog>
    );
}
