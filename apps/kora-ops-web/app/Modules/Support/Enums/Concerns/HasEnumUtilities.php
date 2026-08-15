<?php

namespace App\Modules\Support\Enums\Concerns;

use Illuminate\Support\Facades\App;
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

    /**
     * @return array<string, string>
     */
    public static function translatedOptions(?string $locale = null): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->translatedLabel($locale)])
            ->all();
    }

    public function label(): string
    {
        return Str::headline(str_replace(['-', '_'], ' ', $this->value));
    }

    /**
     * Retourne le libellé traduit via les fichiers de langue enums.php.
     * Tombe sur label() si aucune traduction n'est trouvée.
     */
    public function translatedLabel(?string $locale = null): string
    {
        $key = $this->translationKey();

        $translated = trans("enums.{$key}", [], $locale ?? App::getLocale());

        return $translated !== "enums.{$key}" ? $translated : $this->label();
    }

    /**
     * Clé de traduction basée sur le nom court de l'enum + la valeur du case.
     * Ex: SystemRole::Passenger → "system_role.passenger"
     */
    public function translationKey(): string
    {
        $enumShortName = Str::snake(class_basename($this));

        return "{$enumShortName}.{$this->value}";
    }
}
