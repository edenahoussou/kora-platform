import { DoorOpen } from 'lucide-react';
import { cellAt } from '@/components/seat-map/layout-utils';
import type { LayoutCell, LayoutGrid } from '@/components/seat-map/types';
import { cn } from '@/lib/utils';

export type EditorTool =
    'seat' | 'crew' | 'aisle' | 'empty' | 'door' | 'driver' | 'eraser';

type Props = {
    grid: LayoutGrid;
    locked: boolean;
    renaming: { x: number; y: number; value: string } | null;
    onPaintStart: (x: number, y: number) => void;
    onPaintEnter: (x: number, y: number) => void;
    onSeatClick: (cell: LayoutCell) => void;
    onRenameChange: (value: string) => void;
    onRenameCommit: () => void;
    onRenameCancel: () => void;
};

function EditorCell({
    cell,
    x,
    y,
    locked,
    renaming,
    onPaintStart,
    onPaintEnter,
    onSeatClick,
    onRenameChange,
    onRenameCommit,
    onRenameCancel,
}: {
    cell: LayoutCell | undefined;
    x: number;
    y: number;
    locked: boolean;
    renaming: Props['renaming'];
} & Pick<
    Props,
    | 'onPaintStart'
    | 'onPaintEnter'
    | 'onSeatClick'
    | 'onRenameChange'
    | 'onRenameCommit'
    | 'onRenameCancel'
>) {
    const isRenaming =
        renaming !== null && renaming.x === x && renaming.y === y;

    const paintProps = locked
        ? {}
        : {
              onMouseDown: (event: React.MouseEvent) => {
                  event.preventDefault();
                  onPaintStart(x, y);
              },
              onMouseEnter: () => onPaintEnter(x, y),
          };

    if (!cell || cell.kind === 'empty' || cell.kind === 'aisle') {
        return (
            <span
                className={cn(
                    'sm-cell',
                    cell?.kind === 'aisle'
                        ? 'sm-cell--aisle'
                        : 'sm-cell--empty',
                )}
                {...paintProps}
            />
        );
    }

    if (cell.kind === 'door') {
        return (
            <span className="sm-cell sm-cell--door" {...paintProps}>
                <DoorOpen className="size-3.5" />
            </span>
        );
    }

    if (cell.kind === 'driver') {
        return (
            <span className="sm-cell sm-cell--driver" {...paintProps}>
                ◉
            </span>
        );
    }

    return (
        <button
            type="button"
            disabled={locked}
            onClick={() => onSeatClick(cell)}
            className={cn(
                'seat sm-seat',
                cell.seatKind === 'crew' && 'sm-seat--crew',
            )}
            aria-label={`Siège ${cell.code ?? 'sans code'}`}
            {...paintProps}
        >
            {isRenaming ? (
                <input
                    className="sm-code-input"
                    value={renaming.value}
                    autoFocus
                    onChange={(event) => onRenameChange(event.target.value)}
                    onBlur={onRenameCommit}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            onRenameCommit();
                        }

                        if (event.key === 'Escape') {
                            onRenameCancel();
                        }
                    }}
                    onClick={(event) => event.stopPropagation()}
                    onMouseDown={(event) => event.stopPropagation()}
                />
            ) : (
                <span>{cell.code ?? ''}</span>
            )}
        </button>
    );
}

export function EditorGrid({
    grid,
    locked,
    renaming,
    onPaintStart,
    onPaintEnter,
    onSeatClick,
    onRenameChange,
    onRenameCommit,
    onRenameCancel,
}: Props) {
    const cells: React.ReactNode[] = [];

    for (let y = 0; y < grid.rows; y++) {
        for (let x = 0; x < grid.cols; x++) {
            cells.push(
                <EditorCell
                    key={`${x}-${y}`}
                    cell={cellAt(grid, x, y)}
                    x={x}
                    y={y}
                    locked={locked}
                    renaming={renaming}
                    onPaintStart={onPaintStart}
                    onPaintEnter={onPaintEnter}
                    onSeatClick={onSeatClick}
                    onRenameChange={onRenameChange}
                    onRenameCommit={onRenameCommit}
                    onRenameCancel={onRenameCancel}
                />,
            );
        }
    }

    return (
        <div
            className={cn(
                'seat-map seat-map--edit editor-canvas',
                locked && 'pointer-events-none opacity-90',
            )}
            style={{ '--sm-cell-size': '48px' } as React.CSSProperties}
        >
            <div
                className="sm-grid"
                style={{
                    gridTemplateColumns: `repeat(${grid.cols}, var(--sm-cell-size, 45px))`,
                }}
            >
                {cells}
            </div>
        </div>
    );
}
