<?php

namespace App\Modules\Notifications\Services\Sms;

/**
 * Normalise un texte GSM-7 et le tient sur un segment de 160 caractères.
 * Hors GSM-7, un SMS bascule en UCS-2 et coûte 70 caractères par segment.
 */
final class SmsText
{
    public const int MAX_LENGTH = 160;

    /** Alphabet GSM-7 par défaut, extensions comprises. */
    private const string GSM7_ALPHABET = '@£'."\x24".'¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€';

    /** @var array<string, string> */
    private const REPLACEMENTS = [
        '’' => "'",
        '‘' => "'",
        '“' => '"',
        '”' => '"',
        '–' => '-',
        '—' => '-',
        '…' => '...',
        'œ' => 'oe',
        'Œ' => 'OE',
        '→' => '>',
        '•' => '-',
        '€' => 'EUR',
        'ç' => 'c',
        'â' => 'a',
        'ê' => 'e',
        'î' => 'i',
        'ô' => 'o',
        'û' => 'u',
        'ë' => 'e',
        'ï' => 'i',
        'ÿ' => 'y',
        'Â' => 'A',
        'Ê' => 'E',
        'Î' => 'I',
        'Ô' => 'O',
        'Û' => 'U',
        'Ë' => 'E',
        'Ï' => 'I',
    ];

    public static function normalize(string $text): string
    {
        $text = strtr($text, self::REPLACEMENTS);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) > self::MAX_LENGTH) {
            $text = rtrim(mb_substr($text, 0, self::MAX_LENGTH));
        }

        return $text;
    }

    public static function isGsm7(string $text): bool
    {
        foreach (mb_str_split($text) as $char) {
            if (! str_contains(self::GSM7_ALPHABET, $char)) {
                return false;
            }
        }

        return true;
    }
}
