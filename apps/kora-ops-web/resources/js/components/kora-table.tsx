import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type TableColumn<T> = {
    key: string;
    header: string;
    align?: 'left' | 'right' | 'center';
    render: (row: T) => ReactNode;
};

export type PaginationMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number | null;
    to: number | null;
    /** Base URL for page navigation (e.g. '/agencies'). */
    baseUrl: string;
};

type KoraTableProps<T> = {
    title?: string;
    subtitle?: string;
    icon?: LucideIcon;
    columns: TableColumn<T>[];
    data: T[];
    rowKey: (row: T) => string;
    emptyTitle?: string;
    emptyMessage?: string;
    emptyIcon?: LucideIcon;
    actions?: ReactNode;
    footer?: ReactNode;
    pagination?: PaginationMeta;
    onRowClick?: (row: T) => void;
    variant?: 'standard' | 'cash';
    showHeader?: boolean;
};

/**
 * Reusable KORA table with standard and Caisse appearances.
 *
 * The standard appearance includes a titled panel and illustrated empty state.
 * The Caisse appearance matches the operations table card and pagination.
 */
export function KoraTable<T>({
    title,
    subtitle,
    icon: Icon,
    columns,
    data,
    rowKey,
    emptyTitle = 'Aucun résultat',
    emptyMessage = 'Ajustez votre recherche ou créez un nouvel élément.',
    emptyIcon: EmptyIcon,
    actions,
    footer,
    pagination,
    onRowClick,
    variant = 'standard',
    showHeader = true,
}: KoraTableProps<T>) {
    const isCashTable = variant === 'cash';

    const alignClass = (align?: string): string => {
        if (align === 'right') {
            return 'text-right';
        }

        if (align === 'center') {
            return 'text-center';
        }

        return 'text-left';
    };

    const goToPage = (page: number) => {
        if (!pagination || page < 1 || page > pagination.lastPage) {
            return;
        }

        const url = new URL(pagination.baseUrl, window.location.origin);
        const existing = new URLSearchParams(window.location.search);

        existing.delete('page');

        for (const [key, value] of existing) {
            url.searchParams.set(key, value);
        }

        if (page > 1) {
            url.searchParams.set('page', String(page));
        }

        router.get(
            url.pathname +
                (url.search ? `?${url.searchParams.toString()}` : ''),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const renderPageButtons = () => {
        if (!pagination) {
            return null;
        }

        const { currentPage, lastPage } = pagination;

        if (lastPage <= 1) {
            return null;
        }

        const pages: (number | '...')[] = [];
        const delta = 1;

        for (let i = 1; i <= lastPage; i++) {
            if (
                i === 1 ||
                i === lastPage ||
                (i >= currentPage - delta && i <= currentPage + delta)
            ) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '...') {
                pages.push('...');
            }
        }

        return (
            <div className="flex gap-[5px]">
                {pages.map((page, index) =>
                    page === '...' ? (
                        <span
                            key={`ellipsis-${index}`}
                            className="grid size-[31px] place-items-center text-[0.6875rem] text-muted-foreground"
                        >
                            …
                        </span>
                    ) : (
                        <button
                            key={page}
                            type="button"
                            onClick={() => goToPage(page)}
                            className={`grid size-[31px] place-items-center rounded-[8px] text-[0.6875rem] ${isCashTable ? 'font-black' : 'font-medium'} transition-colors ${
                                isCashTable
                                    ? page === currentPage
                                        ? 'bg-[var(--kora)] text-white'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                    : page === currentPage
                                      ? 'border border-[var(--kora)] bg-[var(--kora)] text-white'
                                      : 'border border-border bg-card text-foreground hover:bg-muted'
                            }`}
                        >
                            {page}
                        </button>
                    ),
                )}
            </div>
        );
    };

    return (
        <div
            className={
                isCashTable
                    ? 'cash-table-card'
                    : 'kora-panel overflow-hidden rounded-[var(--kora-radius)] border border-border bg-card shadow-[0_8px_30px_rgba(0,29,78,0.045)]'
            }
        >
            {showHeader && (title || subtitle || Icon || actions) && (
                <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-4">
                    <div>
                        {title && (
                            <h2 className="text-[0.9375rem] font-bold text-foreground">
                                {title}
                            </h2>
                        )}
                        {subtitle && (
                            <p className="mt-1 text-[0.6875rem] text-muted-foreground">
                                {subtitle}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        {actions}
                        {Icon && <Icon className="size-5 text-gold" />}
                    </div>
                </div>
            )}

            {data.length === 0 ? (
                isCashTable ? (
                    <p className="px-6 py-16 text-center text-sm text-muted-foreground">
                        {emptyMessage}
                    </p>
                ) : (
                    <div className="flex min-h-[22rem] flex-col items-center justify-center px-6 py-12 text-center">
                        <div className="mb-4 grid size-14 place-items-center rounded-2xl bg-gold/10 text-gold">
                            {EmptyIcon ? (
                                <EmptyIcon className="size-7" />
                            ) : Icon ? (
                                <Icon className="size-7" />
                            ) : null}
                        </div>
                        <h3 className="text-base font-bold text-foreground">
                            {emptyTitle}
                        </h3>
                        <p className="mt-2 max-w-sm text-sm text-muted-foreground">
                            {emptyMessage}
                        </p>
                    </div>
                )
            ) : (
                <div
                    className={
                        isCashTable
                            ? 'cash-table-scroll overflow-x-auto'
                            : 'overflow-x-auto'
                    }
                >
                    <table
                        className={
                            isCashTable
                                ? 'w-full min-w-[950px] text-left'
                                : 'w-full min-w-[48rem] text-left text-sm'
                        }
                    >
                        <thead className="bg-muted/35 text-[0.6875rem] font-extrabold tracking-[0.12em] text-muted-foreground uppercase">
                            <tr>
                                {columns.map((column) => (
                                    <th
                                        key={column.key}
                                        className={`${isCashTable ? 'px-4 py-3' : 'px-5 py-3.5'} ${alignClass(column.align)}`}
                                    >
                                        {column.header}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody
                            className={
                                isCashTable
                                    ? undefined
                                    : 'divide-y divide-border/70'
                            }
                        >
                            {data.map((row) => (
                                <tr
                                    key={rowKey(row)}
                                    onClick={
                                        onRowClick
                                            ? () => onRowClick(row)
                                            : undefined
                                    }
                                    className={`transition-colors ${
                                        isCashTable
                                            ? 'hover:bg-muted/30'
                                            : 'hover:bg-muted/25'
                                    } ${onRowClick ? 'cursor-pointer' : ''}`}
                                >
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={`${isCashTable ? 'px-4 py-3.5' : 'px-5 py-4'} ${alignClass(column.align)}`}
                                        >
                                            {column.render(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {pagination && pagination.lastPage > 1 && data.length > 0 && (
                <div
                    className={
                        isCashTable
                            ? 'flex flex-col gap-4 px-4 py-3 text-[0.6875rem] text-muted-foreground sm:flex-row sm:items-center sm:justify-between'
                            : 'flex items-center justify-between border-t border-border px-5 py-[13px] text-[0.6875rem] text-muted-foreground'
                    }
                >
                    {isCashTable ? (
                        <>
                            <span>
                                {pagination.from && pagination.to
                                    ? `${pagination.from}–${pagination.to} sur ${pagination.total} éléments`
                                    : `${pagination.total} résultat${pagination.total > 1 ? 's' : ''}`}
                            </span>
                            <div className="flex items-center gap-[5px]">
                                <button
                                    type="button"
                                    onClick={() =>
                                        goToPage(pagination.currentPage - 1)
                                    }
                                    disabled={pagination.currentPage <= 1}
                                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-colors hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                >
                                    <ChevronLeft className="size-3.5" />
                                </button>
                                {renderPageButtons()}
                                <button
                                    type="button"
                                    onClick={() =>
                                        goToPage(pagination.currentPage + 1)
                                    }
                                    disabled={
                                        pagination.currentPage >=
                                        pagination.lastPage
                                    }
                                    className="grid size-[31px] place-items-center rounded-[8px] bg-muted text-foreground transition-colors hover:bg-gold/15 disabled:pointer-events-none disabled:opacity-35"
                                >
                                    <ChevronRight className="size-3.5" />
                                </button>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() =>
                                        goToPage(pagination.currentPage - 1)
                                    }
                                    disabled={pagination.currentPage <= 1}
                                    className="grid size-[31px] place-items-center rounded-[8px] border border-border bg-card text-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <ChevronLeft className="size-3.5" />
                                </button>
                                <span>
                                    {pagination.from && pagination.to
                                        ? `${pagination.from}–${pagination.to} sur ${pagination.total}`
                                        : `${pagination.total} résultat${pagination.total > 1 ? 's' : ''}`}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        goToPage(pagination.currentPage + 1)
                                    }
                                    disabled={
                                        pagination.currentPage >=
                                        pagination.lastPage
                                    }
                                    className="grid size-[31px] place-items-center rounded-[8px] border border-border bg-card text-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                                >
                                    <ChevronRight className="size-3.5" />
                                </button>
                            </div>
                            {renderPageButtons()}
                        </>
                    )}
                </div>
            )}

            {footer}
        </div>
    );
}
