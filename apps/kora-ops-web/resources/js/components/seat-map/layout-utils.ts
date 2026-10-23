import type { LayoutCell, LayoutGrid, SeatKind } from './types';

export function cellAt(grid: LayoutGrid, x: number, y: number) {
    return grid.cells.find((cell) => cell.x === x && cell.y === y);
}

export function seatStats(grid: LayoutGrid): {
    sellable: number;
    crew: number;
    total: number;
} {
    const seats = grid.cells.filter((cell) => cell.kind === 'seat');
    const crew = seats.filter((cell) => cell.seatKind === 'crew').length;

    return {
        sellable: seats.length - crew,
        crew,
        total: seats.length,
    };
}

/** Attribue les codes `rangée+lettre` en sautant les cellules non-siège. */
export function renumberSeats(grid: LayoutGrid, force = false): LayoutGrid {
    const cells = grid.cells.map((cell) => ({ ...cell }));

    for (let y = 0; y < grid.rows; y++) {
        const rowSeats = cells
            .filter((cell) => cell.kind === 'seat' && cell.y === y)
            .sort((a, b) => a.x - b.x);

        rowSeats.forEach((cell, index) => {
            if (force || !cell.codeLocked) {
                cell.code = `${y + 1}${String.fromCharCode(65 + index)}`;
                cell.codeLocked = force ? false : cell.codeLocked;
            }
        });
    }

    return { ...grid, cells };
}

/** Indique si le gabarit est figé parce qu'il équipe déjà des bus. */
export function isTemplateLocked(template: { busesCount: number }): boolean {
    return template.busesCount > 0;
}

/** Duplique un gabarit pour en créer une copie modifiable. */
export function cloneGrid(grid: LayoutGrid): LayoutGrid {
    return {
        rows: grid.rows,
        cols: grid.cols,
        cells: grid.cells.map((cell) => ({ ...cell })),
    };
}

export function newCell(
    kind: LayoutCell['kind'],
    x: number,
    y: number,
    seatKind?: SeatKind,
): LayoutCell {
    return { kind, x, y, seatKind };
}
