<?php

namespace App\Modules\IdentityAccess\Data;

use Illuminate\Support\Str;

final class PermissionCatalog
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return SystemRole::allPermissions();
    }

    /**
     * @return array<string, array{code: string, label: string, domain: string}>
     */
    public static function definitions(): array
    {
        return array_reduce(self::codes(), function (array $definitions, string $code): array {
            $domain = (string) Str::before($code, '.');

            $definitions[$code] = [
                'code' => $code,
                'label' => self::label($code),
                'domain' => $domain,
            ];

            return $definitions;
        }, []);
    }

    public static function label(string $code): string
    {
        $translation = __('permissions.'.$code);

        return $translation === 'permissions.'.$code
            ? Str::headline(str_replace(['.', '-', '_'], ' ', $code))
            : $translation;
    }

    public static function domainLabel(string $domain): string
    {
        $translation = __('permission_domains.'.$domain);

        return $translation === 'permission_domains.'.$domain
            ? Str::headline(str_replace(['.', '-', '_'], ' ', $domain))
            : $translation;
    }

    public static function isKnown(string $code): bool
    {
        return in_array($code, self::codes(), true);
    }
}
