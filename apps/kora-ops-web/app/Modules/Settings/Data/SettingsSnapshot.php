<?php

namespace App\Modules\Settings\Data;

use DateTimeImmutable;
use Spatie\LaravelSettings\Settings;

final readonly class SettingsSnapshot
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $sensitiveProperties
     */
    public function __construct(
        public SettingsGroup $group,
        public array $values,
        public array $sensitiveProperties,
        public DateTimeImmutable $capturedAt,
    ) {}

    /**
     * @param  list<string>  $sensitiveProperties
     */
    public static function fromSettings(
        SettingsGroup $group,
        Settings $settings,
        array $sensitiveProperties,
    ): self {
        $values = $settings->toArray();

        foreach ($sensitiveProperties as $property) {
            $values[$property] = filled($values[$property] ?? null);
        }

        return new self(
            group: $group,
            values: $values,
            sensitiveProperties: $sensitiveProperties,
            capturedAt: new DateTimeImmutable,
        );
    }
}
