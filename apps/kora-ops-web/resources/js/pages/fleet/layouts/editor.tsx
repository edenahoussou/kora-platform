import { Head, router } from '@inertiajs/react';
import {
    Armchair,
    ArrowLeft,
    Copy,
    DoorOpen,
    Eraser,
    Footprints,
    HardHat,
    Hash,
    LifeBuoy,
    Lock,
    Plus,
    Minus,
    Redo2,
    Square,
    TriangleAlert,
    Undo2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { KoraStatusBadge } from '@/components/kora-status-badge';
import { EditorGrid } from '@/components/layout-editor/editor-grid';
import type { EditorTool } from '@/components/layout-editor/editor-grid';
import {
    cellAt,
    cloneGrid,
    isTemplateLocked,
    renumberSeats,
    seatStats,
} from '@/components/seat-map/layout-utils';
import { SeatMap } from '@/components/seat-map/seat-map';
import type {
    LayoutCell,
    LayoutGrid,
    SeatTemplate,
} from '@/components/seat-map/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import layouts from '@/routes/layouts';
import type { BreadcrumbItem } from '@/types';
import { findTemplate, templateStatusLabels } from './mock-data';

type PageProps = {
    templateUuid?: string;
    duplicate?: string | null;
};

const TOOLS: {
    key: EditorTool;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
}[] = [
    { key: 'seat', label: 'Siège', icon: Armchair },
    { key: 'crew', label: 'Équipage', icon: HardHat },
    { key: 'aisle', label: 'Allée', icon: Footprints },
    { key: 'empty', label: 'Vide', icon: Square },
    { key: 'door', label: 'Porte', icon: DoorOpen },
    { key: 'driver', label: 'Conducteur', icon: LifeBuoy },
];

function emptyGrid(): LayoutGrid {
    return renumberSeats({
        rows: 13,
        cols: 5,
        cells: [
            { kind: 'driver', x: 0, y: 0 },
            { kind: 'door', x: 4, y: 0 },
        ],
    });
}

function duplicatedFrom(source: SeatTemplate): SeatTemplate {
    return {
        ...source,
        uuid: `copy-${source.uuid}`,
        name: `Copie de ${source.name}`,
        code: `${source.code}-C`,
        status: 'draft',
        busesCount: 0,
        grid: cloneGrid(source.grid),
    };
}

export default function LayoutEditorPage({
    templateUuid,
    duplicate,
}: PageProps) {
    const source = templateUuid
        ? findTemplate(templateUuid)
        : duplicate
          ? (() => {
                const origin = findTemplate(duplicate);

                return origin ? duplicatedFrom(origin) : undefined;
            })()
          : undefined;

    const locked = source ? isTemplateLocked(source) : false;

    const [name, setName] = useState(source?.name ?? '');
    const [code, setCode] = useState(source?.code ?? '');
    const [description, setDescription] = useState(source?.description ?? '');
    const [grid, setGrid] = useState<LayoutGrid>(() =>
        source ? cloneGrid(source.grid) : emptyGrid(),
    );
    const [tool, setTool] = useState<EditorTool>('seat');
    const [past, setPast] = useState<LayoutGrid[]>([]);
    const [future, setFuture] = useState<LayoutGrid[]>([]);
    const [renaming, setRenaming] = useState<{
        x: number;
        y: number;
        value: string;
    } | null>(null);
    const painting = useRef(false);

    useEffect(() => {
        const stopPainting = () => {
            if (painting.current) {
                painting.current = false;
                setGrid((prev) => renumberSeats(prev));
            }
        };

        window.addEventListener('mouseup', stopPainting);

        return () => window.removeEventListener('mouseup', stopPainting);
    }, []);

    const pushHistory = (snapshot: LayoutGrid) => {
        setPast((prev) => [...prev.slice(-49), snapshot]);
        setFuture([]);
    };

    const applyTool = (x: number, y: number) => {
        setGrid((prev) => {
            const rest = prev.cells.filter(
                (cell) => !(cell.x === x && cell.y === y),
            );
            const cells: LayoutCell[] = [...rest];

            if (tool !== 'eraser') {
                const kind: LayoutCell['kind'] =
                    tool === 'seat' || tool === 'crew' ? 'seat' : tool;
                cells.push({
                    kind,
                    x,
                    y,
                    seatKind:
                        kind === 'seat'
                            ? tool === 'crew'
                                ? 'crew'
                                : 'standard'
                            : undefined,
                });
            }

            return { ...prev, cells };
        });
    };

    const handlePaintStart = (x: number, y: number) => {
        if (locked) {
            return;
        }

        const existing = cellAt(grid, x, y);

        if (tool === 'seat' && existing?.kind === 'seat') {
            return;
        }

        pushHistory(grid);
        painting.current = true;
        applyTool(x, y);
    };

    const handlePaintEnter = (x: number, y: number) => {
        if (painting.current && !locked) {
            applyTool(x, y);
        }
    };

    const handleSeatClick = (cell: LayoutCell) => {
        if (!locked && tool === 'seat' && cell.kind === 'seat') {
            setRenaming({ x: cell.x, y: cell.y, value: cell.code ?? '' });
        }
    };

    const commitRename = () => {
        if (!renaming) {
            return;
        }

        const value = renaming.value.trim().toUpperCase();
        const target = renaming;
        setRenaming(null);

        if (!value) {
            return;
        }

        pushHistory(grid);
        setGrid((prev) => ({
            ...prev,
            cells: prev.cells.map((cell) =>
                cell.x === target.x && cell.y === target.y
                    ? { ...cell, code: value, codeLocked: true }
                    : cell,
            ),
        }));
    };

    const undo = () => {
        setPast((prev) => {
            if (prev.length === 0) {
                return prev;
            }

            const snapshot = prev[prev.length - 1];
            setFuture((f) => [...f, grid]);
            setGrid(snapshot);

            return prev.slice(0, -1);
        });
    };

    const redo = () => {
        setFuture((prev) => {
            if (prev.length === 0) {
                return prev;
            }

            const snapshot = prev[prev.length - 1];
            setPast((p) => [...p, grid]);
            setGrid(snapshot);

            return prev.slice(0, -1);
        });
    };

    const resizeGrid = (axis: 'rows' | 'cols', delta: number) => {
        if (locked) {
            return;
        }

        pushHistory(grid);
        setGrid((prev) => {
            const size = Math.max(1, prev[axis] + delta);
            const cells = prev.cells.filter((cell) =>
                axis === 'rows' ? cell.y < size : cell.x < size,
            );

            return { ...prev, [axis]: size, cells };
        });
        setGrid((prev) => renumberSeats(prev));
    };

    const errors = useMemo(() => {
        const list: string[] = [];
        const seats = grid.cells.filter((cell) => cell.kind === 'seat');
        const codes = seats
            .map((cell) => cell.code)
            .filter((value): value is string => Boolean(value));
        const duplicated = codes.filter(
            (value, index) => codes.indexOf(value) !== index,
        );

        if (!name.trim()) {
            list.push('Le nom du gabarit est requis.');
        }

        if (!code.trim()) {
            list.push('Le code du gabarit est requis.');
        }

        if (seatStats(grid).sellable < 1) {
            list.push('Au moins un siège vendable est requis.');
        }

        if (duplicated.length > 0) {
            list.push(
                `Codes de sièges en double : ${[...new Set(duplicated)].join(', ')}.`,
            );
        }

        if (grid.cells.filter((cell) => cell.kind === 'driver').length > 1) {
            list.push('Un seul poste conducteur est autorisé.');
        }

        return list;
    }, [grid, name, code]);

    const stats = seatStats(grid);

    if (templateUuid && !source) {
        return (
            <>
                <Head title="Gabarit introuvable" />
                <div className="mx-auto max-w-lg rounded-[var(--kora-radius)] border border-border/50 bg-card p-10 text-center">
                    <Armchair className="mx-auto size-10 text-muted-foreground/40" />
                    <h1 className="mt-3 text-lg font-bold text-foreground">
                        Gabarit introuvable
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Ce gabarit n'existe pas ou a été supprimé.
                    </p>
                    <button
                        type="button"
                        onClick={() => router.get(layouts.index.url())}
                        className="btn btn-ghost mt-5"
                    >
                        <ArrowLeft className="size-3.5" />
                        Retour aux gabarits
                    </button>
                </div>
            </>
        );
    }

    const save = () => {
        if (errors.length > 0) {
            return;
        }

        toast.success(
            `Gabarit « ${name} » enregistré (maquette — non persisté).`,
        );
        router.get(layouts.index.url());
    };

    return (
        <>
            <Head
                title={source ? `Modifier ${source.name}` : 'Nouveau gabarit'}
            />

            <div className="kora-page-head mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="kora-breadcrumb mb-2 text-[0.6875rem] text-muted-foreground">
                        KORA OPS / Ressources / Gabarits /{' '}
                        {source ? source.name : 'Nouveau'}
                    </div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-[1.8125rem] font-extrabold tracking-[-0.035em] text-foreground">
                            {source ? source.name : 'Nouveau gabarit'}
                        </h1>
                        {source && (
                            <KoraStatusBadge
                                label={templateStatusLabels[source.status]}
                                tone={
                                    source.status === 'active' ? 'navy' : 'gold'
                                }
                            />
                        )}
                        {locked && (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-gold/15 px-2.5 py-1 text-[0.6875rem] font-bold text-[var(--kora)]">
                                <Lock className="size-3" />
                                Lecture seule
                            </span>
                        )}
                    </div>
                    <p className="mt-1.5 text-[0.8125rem] text-muted-foreground">
                        {locked
                            ? 'Ce gabarit équipe des bus : dupliquez-le pour créer une version modifiable.'
                            : 'Peignez la cabine cellule par cellule, puis vérifiez le rendu vendeur.'}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onClick={() => router.get(layouts.index.url())}
                        className="btn btn-ghost"
                    >
                        <ArrowLeft className="size-3.5" />
                        {locked ? 'Retour' : 'Annuler'}
                    </button>
                    {locked ? (
                        <button
                            type="button"
                            onClick={() =>
                                router.get(
                                    layouts.create.url({
                                        query: {
                                            duplicate: source?.uuid,
                                        },
                                    }),
                                )
                            }
                            className="btn btn-primary"
                        >
                            <Copy className="size-3.5" />
                            Dupliquer pour modifier
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={save}
                            disabled={errors.length > 0}
                            className="btn btn-primary"
                        >
                            Enregistrer
                        </button>
                    )}
                </div>
            </div>

            {locked && (
                <div className="mb-5 flex items-center gap-3 rounded-[var(--kora-radius)] border border-gold/40 bg-gold/10 px-5 py-3.5 text-sm text-foreground">
                    <Lock className="size-4 shrink-0 text-gold" />
                    <span>
                        Ce gabarit est utilisé par{' '}
                        <strong>{source?.busesCount} bus</strong> : il ne peut
                        plus être modifié directement. Dupliquez-le pour faire
                        évoluer la composition, puis réaffectez les bus.
                    </span>
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                {/* Canvas éditeur */}
                <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                    <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_160px]">
                        <div>
                            <Label htmlFor="layout-name">Nom du gabarit</Label>
                            <Input
                                id="layout-name"
                                value={name}
                                disabled={locked}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                placeholder="Standard 2+2"
                                className="mt-1.5"
                            />
                        </div>
                        <div>
                            <Label htmlFor="layout-code">Code</Label>
                            <Input
                                id="layout-code"
                                value={code}
                                disabled={locked}
                                onChange={(event) =>
                                    setCode(event.target.value.toUpperCase())
                                }
                                placeholder="STD-51"
                                className="mt-1.5 font-mono"
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <Label htmlFor="layout-description">
                                Description
                            </Label>
                            <Input
                                id="layout-description"
                                value={description}
                                disabled={locked}
                                onChange={(event) =>
                                    setDescription(event.target.value)
                                }
                                placeholder="Usage prévu, densité, notes internes…"
                                className="mt-1.5"
                            />
                        </div>
                    </div>

                    {/* Palette d'outils */}
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-border/50 pb-4">
                        <div className="tool-palette">
                            {TOOLS.map(({ key, label, icon: Icon }) => (
                                <button
                                    key={key}
                                    type="button"
                                    disabled={locked}
                                    onClick={() => setTool(key)}
                                    className={cn(tool === key && 'is-active')}
                                    title={
                                        key === 'seat'
                                            ? 'Peindre un siège — cliquez un siège existant pour le renommer'
                                            : label
                                    }
                                >
                                    <Icon className="size-3.5" />
                                    {label}
                                </button>
                            ))}
                            <button
                                type="button"
                                disabled={locked}
                                onClick={() => setTool('eraser')}
                                className={cn(tool === 'eraser' && 'is-active')}
                            >
                                <Eraser className="size-3.5" />
                                Gomme
                            </button>
                        </div>
                        <div className="tool-palette">
                            <button
                                type="button"
                                disabled={locked || past.length === 0}
                                onClick={undo}
                                title="Annuler"
                            >
                                <Undo2 className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                disabled={locked || future.length === 0}
                                onClick={redo}
                                title="Rétablir"
                            >
                                <Redo2 className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                disabled={locked}
                                onClick={() => {
                                    pushHistory(grid);
                                    setGrid((prev) =>
                                        renumberSeats(prev, true),
                                    );
                                }}
                                title="Renuméroter tous les sièges (écrase les codes personnalisés)"
                            >
                                <Hash className="size-3.5" />
                                Renuméroter
                            </button>
                        </div>
                    </div>

                    {/* Grille + contrôles de bords */}
                    <div className="flex gap-3">
                        <div className="min-w-0 flex-1 overflow-x-auto rounded-[1rem] border border-border/40 bg-muted/20 p-5">
                            <EditorGrid
                                grid={grid}
                                locked={locked}
                                renaming={renaming}
                                onPaintStart={handlePaintStart}
                                onPaintEnter={handlePaintEnter}
                                onSeatClick={handleSeatClick}
                                onRenameChange={(value) =>
                                    setRenaming((prev) =>
                                        prev ? { ...prev, value } : prev,
                                    )
                                }
                                onRenameCommit={commitRename}
                                onRenameCancel={() => setRenaming(null)}
                            />
                        </div>
                        <div className="edge-control flex-col">
                            <button
                                type="button"
                                disabled={locked}
                                onClick={() => resizeGrid('cols', 1)}
                                title="Ajouter une colonne"
                            >
                                <Plus className="size-3.5" />
                            </button>
                            <span className="text-[0.625rem] font-bold text-muted-foreground [writing-mode:vertical-rl]">
                                {grid.cols} col.
                            </span>
                            <button
                                type="button"
                                disabled={locked || grid.cols <= 1}
                                onClick={() => resizeGrid('cols', -1)}
                                title="Retirer la dernière colonne"
                            >
                                <Minus className="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <div className="edge-control mt-3">
                        <button
                            type="button"
                            disabled={locked || grid.rows <= 1}
                            onClick={() => resizeGrid('rows', -1)}
                            title="Retirer la dernière rangée"
                        >
                            <Minus className="size-3.5" />
                        </button>
                        <span className="text-[0.625rem] font-bold text-muted-foreground">
                            {grid.rows} rangées
                        </span>
                        <button
                            type="button"
                            disabled={locked}
                            onClick={() => resizeGrid('rows', 1)}
                            title="Ajouter une rangée"
                        >
                            <Plus className="size-3.5" />
                        </button>
                    </div>

                    {errors.length > 0 && (
                        <div className="mt-4 space-y-1.5 rounded-[0.75rem] border border-destructive/30 bg-destructive/5 px-4 py-3">
                            {errors.map((error) => (
                                <p
                                    key={error}
                                    className="flex items-center gap-2 text-[0.75rem] font-medium text-destructive"
                                >
                                    <TriangleAlert className="size-3.5 shrink-0" />
                                    {error}
                                </p>
                            ))}
                        </div>
                    )}
                </section>

                {/* Panneau aperçu vendeur */}
                <aside className="space-y-4">
                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <span className="mb-1 block text-[0.5625rem] font-black tracking-[0.13em] text-[var(--digital)] uppercase">
                            Aperçu temps réel
                        </span>
                        <h3 className="mb-4 text-[0.9375rem] font-bold text-foreground">
                            Rendu au guichet
                        </h3>
                        <SeatMap grid={grid} cabin />
                        <p className="mt-3 text-center text-[0.625rem] text-muted-foreground">
                            Aperçu vendeur : seules les cellules utiles à la
                            vente sont visibles.
                        </p>
                    </section>

                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-3 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Composition
                        </h3>
                        <div className="space-y-2.5 text-xs">
                            {(
                                [
                                    ['Sièges vendables', stats.sellable],
                                    ['Sièges équipage', stats.crew],
                                    [
                                        'Cellules structure',
                                        grid.cells.filter(
                                            (cell) => cell.kind !== 'seat',
                                        ).length,
                                    ],
                                    [
                                        'Dimensions',
                                        `${grid.rows} × ${grid.cols}`,
                                    ],
                                ] as [string, React.ReactNode][]
                            ).map(([label, value]) => (
                                <div
                                    key={label}
                                    className="flex items-center justify-between border-b border-border/40 pb-2 last:border-b-0 last:pb-0"
                                >
                                    <span className="text-muted-foreground">
                                        {label}
                                    </span>
                                    <strong className="text-foreground">
                                        {value}
                                    </strong>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="rounded-[var(--kora-radius)] border border-border/50 bg-card p-5 shadow-[0_16px_45px_rgba(0,29,78,0.07)]">
                        <h3 className="mb-3 text-[0.8125rem] font-bold tracking-tight text-foreground uppercase">
                            Légende
                        </h3>
                        <div className="grid grid-cols-2 gap-2.5 text-[0.6875rem] text-muted-foreground">
                            <span className="flex items-center gap-2">
                                <i className="seat sm-seat inline-block" />
                                Siège vendable
                            </span>
                            <span className="flex items-center gap-2">
                                <i className="seat sm-seat sm-seat--crew inline-block" />
                                Équipage
                            </span>
                            <span className="flex items-center gap-2">
                                <i className="sm-cell sm-cell--driver inline-block" />
                                Conducteur
                            </span>
                            <span className="flex items-center gap-2">
                                <i className="sm-cell sm-cell--door inline-block" />
                                Porte
                            </span>
                            <span className="flex items-center gap-2">
                                <i className="sm-cell sm-cell--aisle inline-block" />
                                Allée
                            </span>
                            <span className="flex items-center gap-2">
                                <i className="sm-cell sm-cell--empty inline-block" />
                                Vide
                            </span>
                        </div>
                    </section>
                </aside>
            </div>
        </>
    );
}

LayoutEditorPage.layout = {
    breadcrumbs: [
        { title: 'Ressources', href: '/resources' } satisfies BreadcrumbItem,
        { title: 'Gabarits', href: '/layouts' } satisfies BreadcrumbItem,
        { title: 'Éditeur', href: '' } satisfies BreadcrumbItem,
    ],
};
