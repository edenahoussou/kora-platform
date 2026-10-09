import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FileText,
    Loader2,
    RefreshCw,
    Search,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import type {
    LogEntry,
    LogFileItem,
    LogLevel,
    LogsResponse,
} from '@/components/monitoring/monitoring-types';
import { LOG_LEVELS } from '@/components/monitoring/monitoring-types';
import { cn } from '@/lib/utils';

type BadgeTone = 'navy' | 'gold' | 'blue' | 'muted' | 'destructive';

const levelTones: Record<string, BadgeTone> = {
    emergency: 'destructive',
    alert: 'destructive',
    critical: 'destructive',
    error: 'destructive',
    warning: 'gold',
    notice: 'blue',
    info: 'blue',
    debug: 'muted',
};

async function fetchJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json() as Promise<T>;
}

export function LogBrowser() {
    const [files, setFiles] = useState<LogFileItem[]>([]);
    const [selectedFile, setSelectedFile] = useState<string>('');
    const [query, setQuery] = useState('');
    const [debouncedQuery, setDebouncedQuery] = useState('');
    const [excludedLevels, setExcludedLevels] = useState<Set<LogLevel>>(
        new Set(),
    );
    const [direction, setDirection] = useState<'desc' | 'asc'>('desc');
    const [page, setPage] = useState(1);
    const [data, setData] = useState<LogsResponse | null>(null);
    const [levelCounts, setLevelCounts] = useState<Record<string, number>>({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [expandedIndex, setExpandedIndex] = useState<number | null>(null);
    const requestId = useRef(0);

    useEffect(() => {
        fetchJson<LogFileItem[] | { data: LogFileItem[] }>(
            '/log-viewer/api/files',
        )
            .then((json) => {
                const list = Array.isArray(json) ? json : json.data;
                setFiles(list);
                setSelectedFile(
                    (current) => current || (list[0]?.identifier ?? ''),
                );
            })
            .catch(() =>
                setError('Impossible de charger les fichiers de log.'),
            );
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedQuery(query.trim());
            setPage(1);
        }, 350);

        return () => clearTimeout(timer);
    }, [query]);

    const loadLogs = useCallback(async () => {
        const id = ++requestId.current;
        setLoading(true);
        setError(null);

        const params = new URLSearchParams();

        if (selectedFile) {
            params.set('file', selectedFile);
        }

        if (debouncedQuery !== '') {
            params.set('query', debouncedQuery);
        }

        excludedLevels.forEach((level) =>
            params.append('exclude_levels[]', level),
        );
        params.set('direction', direction);
        params.set('page', String(page));

        try {
            const json = await fetchJson<LogsResponse>(
                `/log-viewer/api/logs?${params.toString()}`,
            );

            if (id !== requestId.current) {
                return;
            }

            setData(json);
            const counts: Record<string, number> = {};
            json.levelCounts.forEach((item) => {
                counts[item.level] = item.count;
            });
            setLevelCounts(counts);
        } catch {
            if (id === requestId.current) {
                setError('Impossible de charger les logs.');
            }
        } finally {
            if (id === requestId.current) {
                setLoading(false);
            }
        }
    }, [selectedFile, debouncedQuery, excludedLevels, direction, page]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            void loadLogs();
        }, 0);

        return () => window.clearTimeout(timer);
    }, [loadLogs]);

    const toggleLevel = (level: LogLevel) => {
        setExcludedLevels((current) => {
            const next = new Set(current);

            if (next.has(level)) {
                next.delete(level);
            } else {
                next.add(level);
            }

            return next;
        });
        setPage(1);
    };

    const pagination = data?.pagination ?? null;

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-56 flex-1">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Rechercher dans les logs…"
                        className="h-11 w-full rounded-xl bg-muted/55 pr-4 pl-9 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all outline-none placeholder:font-medium placeholder:text-muted-foreground/70 focus:bg-card focus:ring-2 focus:ring-gold/35"
                    />
                </div>
                <select
                    value={selectedFile}
                    onChange={(event) => {
                        setSelectedFile(event.target.value);
                        setPage(1);
                    }}
                    className="h-11 appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                >
                    <option value="">Tous les fichiers</option>
                    {files.map((file) => (
                        <option key={file.identifier} value={file.identifier}>
                            {file.name}
                        </option>
                    ))}
                </select>
                <select
                    value={direction}
                    onChange={(event) => {
                        setDirection(event.target.value as 'desc' | 'asc');
                        setPage(1);
                    }}
                    className="h-11 appearance-none rounded-xl bg-muted/55 px-3 pr-8 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all outline-none focus:bg-card focus:ring-2 focus:ring-gold/35"
                >
                    <option value="desc">Plus récents</option>
                    <option value="asc">Plus anciens</option>
                </select>
                <button
                    type="button"
                    onClick={loadLogs}
                    className="inline-flex h-11 items-center gap-2 rounded-xl bg-muted/55 px-4 text-xs font-bold text-foreground ring-1 ring-black/[0.045] transition-all hover:bg-muted"
                >
                    <RefreshCw
                        className={cn('size-4', loading && 'animate-spin')}
                    />
                    Actualiser
                </button>
            </div>

            <div className="flex flex-wrap gap-1.5">
                {LOG_LEVELS.map((level) => {
                    const active = !excludedLevels.has(level);
                    const count = levelCounts[level];

                    return (
                        <button
                            key={level}
                            type="button"
                            onClick={() => toggleLevel(level)}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[0.625rem] font-extrabold tracking-[0.04em] uppercase ring-1 transition-all',
                                active
                                    ? 'bg-card text-foreground ring-black/[0.08]'
                                    : 'bg-muted/40 text-muted-foreground/50 line-through ring-transparent',
                            )}
                        >
                            {level}
                            {count !== undefined && (
                                <span className="text-muted-foreground/70">
                                    {count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <div className="overflow-hidden rounded-2xl bg-card ring-1 ring-black/[0.045] dark:ring-white/10">
                {error && (
                    <p className="px-5 py-4 text-xs font-bold text-destructive">
                        {error}
                    </p>
                )}
                {!error && loading && !data && (
                    <div className="flex items-center justify-center gap-2 px-5 py-16 text-muted-foreground">
                        <Loader2 className="size-4 animate-spin" />
                        <span className="text-xs font-bold">
                            Chargement des logs…
                        </span>
                    </div>
                )}
                {!error && data && data.logs.length === 0 && (
                    <div className="flex flex-col items-center gap-2 px-5 py-16 text-muted-foreground">
                        <FileText className="size-6" strokeWidth={1.5} />
                        <span className="text-xs font-bold">
                            Aucun log ne correspond aux filtres.
                        </span>
                    </div>
                )}
                {!error && data && data.logs.length > 0 && (
                    <ul className="divide-y divide-black/[0.04] dark:divide-white/[0.06]">
                        {data.logs.map((entry) => (
                            <LogRow
                                key={`${entry.file_identifier}-${entry.index}`}
                                entry={entry}
                                expanded={expandedIndex === entry.index}
                                onToggle={() =>
                                    setExpandedIndex((current) =>
                                        current === entry.index
                                            ? null
                                            : entry.index,
                                    )
                                }
                            />
                        ))}
                    </ul>
                )}
            </div>

            {pagination && pagination.last_page > 1 && (
                <div className="flex items-center justify-between">
                    <p className="text-[0.625rem] font-bold tracking-[0.08em] text-muted-foreground uppercase">
                        Page {pagination.current_page} / {pagination.last_page}{' '}
                        — {pagination.total} entrées
                    </p>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={pagination.current_page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            className="inline-flex h-9 items-center gap-1 rounded-lg bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] disabled:opacity-40"
                        >
                            <ChevronLeft className="size-4" />
                            Précédent
                        </button>
                        <button
                            type="button"
                            disabled={
                                pagination.current_page >= pagination.last_page
                            }
                            onClick={() => setPage((p) => p + 1)}
                            className="inline-flex h-9 items-center gap-1 rounded-lg bg-muted/55 px-3 text-xs font-bold text-foreground ring-1 ring-black/[0.045] disabled:opacity-40"
                        >
                            Suivant
                            <ChevronRight className="size-4" />
                        </button>
                    </div>
                </div>
            )}
        </section>
    );
}

function LogRow({
    entry,
    expanded,
    onToggle,
}: {
    entry: LogEntry;
    expanded: boolean;
    onToggle: () => void;
}) {
    const tone = levelTones[entry.level] ?? 'muted';
    const hasDetails =
        entry.full_text !== undefined ||
        (entry.context !== null &&
            typeof entry.context === 'object' &&
            Object.keys(entry.context as object).length > 0);

    return (
        <li>
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-start gap-3 px-5 py-3 text-left transition-colors hover:bg-muted/30"
            >
                <KoraStatusBadge
                    label={entry.level_name}
                    tone={tone}
                    className="mt-0.5 shrink-0"
                />
                <div className="min-w-0 flex-1">
                    <p className="truncate font-mono text-xs font-semibold text-foreground">
                        {entry.message}
                    </p>
                    <p className="mt-0.5 text-[0.625rem] font-medium text-muted-foreground">
                        {entry.datetime ?? entry.time ?? ''} ·{' '}
                        {entry.file_identifier}
                    </p>
                </div>
                {hasDetails && (
                    <ChevronDown
                        className={cn(
                            'mt-1 size-4 shrink-0 text-muted-foreground transition-transform',
                            expanded && 'rotate-180',
                        )}
                    />
                )}
            </button>
            {expanded && (
                <pre className="overflow-x-auto bg-muted/30 px-5 py-3 font-mono text-[0.6875rem] leading-relaxed whitespace-pre-wrap text-muted-foreground">
                    {entry.full_text ??
                        JSON.stringify(
                            { context: entry.context, extra: entry.extra },
                            null,
                            2,
                        )}
                </pre>
            )}
        </li>
    );
}
