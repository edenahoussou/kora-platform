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

/**
 * Attribue les codes `rangée+lettre` par rangée de sièges : la première
 * rangée contenant des sièges vaut 1 — les rangées conducteur/porte/vide
 * ne consomment aucun numéro.
 */
export function renumberSeats(grid: LayoutGrid, force = false): LayoutGrid {
    const cells = grid.cells.map((cell) => ({ ...cell }));
    const seatRows = [
        ...new Set(
            cells.filter((cell) => cell.kind === 'seat').map((cell) => cell.y),
        ),
    ].sort((a, b) => a - b);

    seatRows.forEach((y, rowIndex) => {
        cells
            .filter((cell) => cell.kind === 'seat' && cell.y === y)
            .sort((a, b) => a.x - b.x)
            .forEach((cell, index) => {
                if (force || !cell.codeLocked) {
                    cell.code = `${rowIndex + 1}${String.fromCharCode(65 + index)}`;
                    cell.codeLocked = force ? false : cell.codeLocked;
                }
            });
    });

    return { ...grid, cells };
}

/** Insère une rangée vide en position `y` — les rangées en dessous descendent. */
export function insertRowAt(grid: LayoutGrid, y: number): LayoutGrid {
    return {
        rows: grid.rows + 1,
        cols: grid.cols,
        cells: grid.cells.map((cell) => ({
            ...cell,
            y: cell.y >= y ? cell.y + 1 : cell.y,
        })),
    };
}

/** Supprime la rangée `y` et toutes ses cellules. */
export function deleteRowAt(grid: LayoutGrid, y: number): LayoutGrid {
    return {
        rows: grid.rows - 1,
        cols: grid.cols,
        cells: grid.cells
            .filter((cell) => cell.y !== y)
            .map((cell) => ({ ...cell, y: cell.y > y ? cell.y - 1 : cell.y })),
    };
}

/** Insère une colonne vide en position `x` — les colonnes à droite décalent. */
export function insertColAt(grid: LayoutGrid, x: number): LayoutGrid {
    return {
        rows: grid.rows,
        cols: grid.cols + 1,
        cells: grid.cells.map((cell) => ({
            ...cell,
            x: cell.x >= x ? cell.x + 1 : cell.x,
        })),
    };
}

/** Supprime la colonne `x` et toutes ses cellules. */
export function deleteColAt(grid: LayoutGrid, x: number): LayoutGrid {
    return {
        rows: grid.rows,
        cols: grid.cols - 1,
        cells: grid.cells
            .filter((cell) => cell.x !== x)
            .map((cell) => ({ ...cell, x: cell.x > x ? cell.x - 1 : cell.x })),
    };
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
