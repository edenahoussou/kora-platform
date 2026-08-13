<?php

namespace App\Modules\Support\Enums\Concerns;

use Illuminate\Support\Str;

trait HasEnumUtilities
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    public function label(): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', $this->value));
    }
}
