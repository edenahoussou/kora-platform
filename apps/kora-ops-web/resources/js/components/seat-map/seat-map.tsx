import { DoorOpen } from 'lucide-react';
import { cn } from '@/lib/utils';
import { cellAt } from './layout-utils';
import type { LayoutCell, LayoutGrid, OperationalSeatStatus } from './types';

type SeatMapMode = 'display' | 'edit' | 'select';

type Props = {
    grid: LayoutGrid;
    mode?: SeatMapMode;
    size?: 'normal' | 'mini';
    cabin?: boolean;
    seatStatuses?: Record<string, OperationalSeatStatus>;
    selectedCodes?: string[];
    onSeatClick?: (cell: LayoutCell, event: React.MouseEvent) => void;
};

function seatStatusClass(
    cell: LayoutCell,
    statuses: Record<string, OperationalSeatStatus>,
    selectedCodes: string[],
): string {
    if (cell.code && selectedCodes.includes(cell.code)) {
        return 'selected';
    }

    if (cell.code && statuses[cell.code] === 'maintenance') {
        return 'sm-seat--maintenance';
    }

    if (cell.code && statuses[cell.code] === 'blocked') {
        return 'sm-seat--blocked';
    }

    return '';
}

function MapCell({
    cell,
    mode,
    statuses,
    selectedCodes,
    onSeatClick,
}: {
    cell: LayoutCell | undefined;
    mode: SeatMapMode;
    statuses: Record<string, OperationalSeatStatus>;
    selectedCodes: string[];
    onSeatClick?: Props['onSeatClick'];
}) {
    if (!cell || cell.kind === 'empty' || cell.kind === 'aisle') {
        return (
            <span
                className={cn(
                    'sm-cell',
                    cell?.kind === 'aisle'
                        ? 'sm-cell--aisle'
                        : 'sm-cell--empty',
                )}
                aria-hidden="true"
            />
        );
    }

    if (cell.kind === 'door') {
        return (
            <span className="sm-cell sm-cell--door" title="Porte">
                <DoorOpen className="size-3.5" />
            </span>
        );
    }

    if (cell.kind === 'driver') {
        return (
            <span className="sm-cell sm-cell--driver" title="Conducteur">
                ◉
            </span>
        );
    }

    const clickable = mode !== 'display' || Boolean(onSeatClick);

    return (
        <button
            type="button"
            disabled={!clickable}
            onClick={(event) => onSeatClick?.(cell, event)}
            className={cn(
                'seat sm-seat',
                cell.seatKind === 'crew' && 'sm-seat--crew',
                cell.maintenance && 'sm-seat--maintenance',
                seatStatusClass(cell, statuses, selectedCodes),
            )}
            aria-label={
                cell.code
                    ? `Siège ${cell.code}`
                    : `Siège ${cell.seatKind === 'crew' ? 'équipage' : 'standard'}`
            }
        >
            <span>{cell.code ?? ''}</span>
        </button>
    );
}

export function SeatMap({
    grid,
    mode = 'display',
    size = 'normal',
    cabin = true,
    seatStatuses = {},
    selectedCodes = [],
    onSeatClick,
}: Props) {
    const cells: React.ReactNode[] = [];

    for (let y = 0; y < grid.rows; y++) {
        for (let x = 0; x < grid.cols; x++) {
            cells.push(
                <MapCell
                    key={`${x}-${y}`}
                    cell={cellAt(grid, x, y)}
                    mode={mode}
                    statuses={seatStatuses}
                    selectedCodes={selectedCodes}
                    onSeatClick={onSeatClick}
                />,
            );
        }
    }

    return (
        <div
            className={cn(
                'seat-map',
                `seat-map--${size}`,
                cabin && 'bus-cabin',
            )}
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
