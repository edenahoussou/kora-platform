<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\BusComposition;

class GenerateSeatLayoutCodeAction
{
    /** Code gabarit auto-généré : GAB-001, GAB-002… */
    public function execute(): string
    {
        $next = 1;

        $last = BusComposition::query()
            ->withTrashed()
            ->where('code', 'like', 'GAB-%')
            ->orderByDesc('code')
            ->value('code');

        if (is_string($last) && preg_match('/^GAB-(\d+)$/', $last, $m) === 1) {
            $next = ((int) $m[1]) + 1;
        }

        do {
            $code = sprintf('GAB-%03d', $next++);
        } while (BusComposition::query()->withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
