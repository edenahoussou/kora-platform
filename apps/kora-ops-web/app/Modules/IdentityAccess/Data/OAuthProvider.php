<?php

namespace App\Modules\IdentityAccess\Data;

enum OAuthProvider: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
