<?php

namespace App\Modules\BusNetwork\Data;

/**
 * Grille de cabine à cellules typées à positions explicites (x, y).
 * Format canonique des gabarits de sièges — le `layout` historique
 * (rangées de codes) est dérivé de cette structure.
 */
final class SeatLayout
{
    /** @param list<array{kind: string, x: int, y: int, code?: string|null, seatKind?: string|null, codeLocked?: bool}> $cells */
    private function __construct(
        public readonly int $rows,
        public readonly int $cols,
        public readonly array $cells,
    ) {}

    /** @param array{rows?: int, cols?: int, cells?: list<array<string, mixed>>} $grid */
    public static function fromGridArray(array $grid): self
    {
        $cells = [];
        foreach ($grid['cells'] ?? [] as $cell) {
            $cells[] = array_filter([
                'kind' => (string) ($cell['kind'] ?? LayoutCellKind::Seat->value),
                'x' => (int) ($cell['x'] ?? 0),
                'y' => (int) ($cell['y'] ?? 0),
                'code' => isset($cell['code']) ? mb_strtoupper(trim((string) $cell['code'])) : null,
                'seatKind' => isset($cell['seatKind']) ? (string) $cell['seatKind'] : null,
                'codeLocked' => isset($cell['codeLocked']) ? (bool) $cell['codeLocked'] : null,
            ], fn (mixed $value): bool => $value !== null);
        }

        return new self(
            rows: (int) ($grid['rows'] ?? 0),
            cols: (int) ($grid['cols'] ?? 0),
            cells: $cells,
        );
    }

    /**
     * Dérive une grille depuis l'ancien format `layout` (rangées de codes type 1A).
     *
     * @param  list<array{type?: string, codes?: list<string>}>  $layout
     */
    public static function fromLegacyLayout(array $layout): self
    {
        $cells = [];
        $rows = 0;
        $cols = 0;

        foreach ($layout as $group) {
            $type = (string) ($group['type'] ?? SeatType::Standard->value);
            foreach ($group['codes'] ?? [] as $code) {
                if (preg_match('/^(\d+)([A-Z]+)$/', (string) $code, $m) !== 1) {
                    continue;
                }

                $y = ((int) $m[1]) - 1;
                $x = self::lettersToIndex($m[2]);
                $cells[] = [
                    'kind' => LayoutCellKind::Seat->value,
                    'x' => $x,
                    'y' => $y,
                    'code' => (string) $code,
                    'seatKind' => in_array($type, SeatType::values(), true) ? $type : SeatType::Standard->value,
                ];
                $rows = max($rows, $y + 1);
                $cols = max($cols, $x + 1);
            }
        }

        return new self($rows, $cols, $cells);
    }

    /** @return array{rows: int, cols: int, cells: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['rows' => $this->rows, 'cols' => $this->cols, 'cells' => $this->cells];
    }

    /** @return list<array<string, mixed>> */
    public function seatCells(): array
    {
        return array_values(array_filter(
            $this->cells,
            fn (array $cell): bool => $cell['kind'] === LayoutCellKind::Seat->value,
        ));
    }

    public function seatCount(): int
    {
        return count($this->seatCells());
    }

    public function sellableCount(): int
    {
        return count(array_filter(
            $this->seatCells(),
            fn (array $cell): bool => ($cell['seatKind'] ?? SeatType::Standard->value) !== SeatType::Crew->value,
        ));
    }

    /**
     * Re-génère l'ancien format `layout` : une entrée par rangée et type.
     *
     * @return list<array{type: string, codes: list<string>}>
     */
    public function toLegacyLayout(): array
    {
        $rows = [];
        foreach ($this->seatCells() as $cell) {
            $rows[(int) $cell['y']][(string) ($cell['seatKind'] ?? SeatType::Standard->value)][] = [
                'x' => (int) $cell['x'],
                'code' => (string) ($cell['code'] ?? ''),
            ];
        }

        ksort($rows);
        $legacy = [];
        foreach ($rows as $byType) {
            foreach ($byType as $type => $rowSeats) {
                usort($rowSeats, fn (array $a, array $b): int => $a['x'] <=> $b['x']);
                $legacy[] = [
                    'type' => $type,
                    'codes' => array_column($rowSeats, 'code'),
                ];
            }
        }

        return $legacy;
    }

    /** @return list<string> Erreurs métier en français. */
    public function errors(): array
    {
        $errors = [];
        $positions = [];
        $codes = [];
        $drivers = 0;

        foreach ($this->cells as $cell) {
            if ($cell['x'] < 0 || $cell['x'] >= $this->cols || $cell['y'] < 0 || $cell['y'] >= $this->rows) {
                $errors[] = 'Une cellule dépasse les dimensions de la grille.';
            }

            $position = $cell['y'].'-'.$cell['x'];
            if (isset($positions[$position])) {
                $errors[] = 'Deux cellules occupent la même position sur la grille.';
            }
            $positions[$position] = true;

            if ($cell['kind'] === LayoutCellKind::Seat->value) {
                $code = (string) ($cell['code'] ?? '');
                if ($code === '') {
                    $errors[] = 'Chaque siège doit avoir un code.';
                } elseif (isset($codes[$code])) {
                    $errors[] = sprintf('Le code « %s » est utilisé plusieurs fois.', $code);
                }
                $codes[$code] = true;
            }

            if ($cell['kind'] === LayoutCellKind::Driver->value) {
                $drivers++;
            }
        }

        if ($this->sellableCount() === 0) {
            $errors[] = 'Le gabarit doit contenir au moins un siège vendable.';
        }

        if ($drivers > 1) {
            $errors[] = 'Un seul poste conducteur est autorisé par gabarit.';
        }

        return array_values(array_unique($errors));
    }

    private static function lettersToIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - ord('A') + 1);
        }

        return $index - 1;
    }
}
