<?php

namespace App\Modules\AppDistribution\Data;

use Stringable;

/**
 * Version d'une application mobile : semver (`MAJOR.MINOR.PATCH[-pre]`) +
 * numéro de build optionnel (le `versionCode` Android, `+42` côté Flutter).
 *
 * Ordre de comparaison (MEZ-150) : précédence semver d'abord (pré-versions
 * comprises, cf. semver.org §11), puis numéro de build quand les deux
 * versions en portent un. Un build absent d'un côté ne départage jamais :
 * `1.4.0` et `1.4.0+42` sont considérées équivalentes, faute de mieux.
 *
 * Formats acceptés par [parse] (remontés par les terminaux dans
 * `app_version`) : `1.4.0`, `v1.4.0`, `1.4.0+42`, `1.4.0 (42)`,
 * `1.4.0-beta.1+42`, `1.4` (patch implicite à 0).
 */
final class AppVersion implements Stringable
{
    /**
     * Format validé à la publication d'une release (patch obligatoire).
     */
    public const SEMVER_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?$/';

    private const LENIENT_PATTERN = '/^v?(\d+)\.(\d+)(?:\.(\d+))?(?:-([0-9A-Za-z.-]+))?(?:\s*(?:\+|\()\s*(\d+)\s*\)?)?$/i';

    public function __construct(
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
        public readonly ?string $preRelease = null,
        public readonly ?int $build = null,
    ) {}

    /**
     * Lecture tolérante d'une version remontée par un appareil. Retourne
     * `null` si la chaîne est vide ou illisible (le terminal est alors
     * considéré en « version inconnue », jamais en erreur).
     */
    public static function parse(?string $raw): ?self
    {
        $value = trim((string) $raw);

        if ($value === '' || preg_match(self::LENIENT_PATTERN, $value, $matches) !== 1) {
            return null;
        }

        return new self(
            (int) $matches[1],
            (int) $matches[2],
            (int) (($matches[3] ?? '') !== '' ? $matches[3] : 0),
            ($matches[4] ?? '') !== '' ? $matches[4] : null,
            ($matches[5] ?? '') !== '' ? (int) $matches[5] : null,
        );
    }

    /**
     * Construit la version d'une release publiée (semver déjà validé).
     */
    public static function fromParts(string $semver, ?int $build): ?self
    {
        $version = self::parse($semver);

        return $version?->withBuild($build);
    }

    public static function isValidSemver(string $value): bool
    {
        return preg_match(self::SEMVER_PATTERN, $value) === 1;
    }

    public function withBuild(?int $build): self
    {
        return new self($this->major, $this->minor, $this->patch, $this->preRelease, $build);
    }

    /**
     * @return int -1 si $this < $other, 0 si équivalentes, 1 si $this > $other
     */
    public function compare(self $other): int
    {
        $core = [$this->major, $this->minor, $this->patch] <=> [$other->major, $other->minor, $other->patch];

        if ($core !== 0) {
            return $core;
        }

        $preRelease = self::comparePreRelease($this->preRelease, $other->preRelease);

        if ($preRelease !== 0) {
            return $preRelease;
        }

        if ($this->build === null || $other->build === null) {
            return 0;
        }

        return $this->build <=> $other->build;
    }

    public function isOlderThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isNewerThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    /**
     * Partie semver seule (`1.4.0-beta.1`).
     */
    public function semver(): string
    {
        $core = "{$this->major}.{$this->minor}.{$this->patch}";

        return $this->preRelease !== null ? "{$core}-{$this->preRelease}" : $core;
    }

    /**
     * Forme complète (`1.4.0+42`), identique au `version` du pubspec Flutter.
     */
    public function __toString(): string
    {
        return $this->build !== null ? "{$this->semver()}+{$this->build}" : $this->semver();
    }

    /**
     * Précédence des pré-versions (semver.org §11) : une version sans
     * pré-version est supérieure à la même avec pré-version ; sinon,
     * comparaison identifiant par identifiant (numérique < alphanumérique).
     */
    private static function comparePreRelease(?string $left, ?string $right): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        $leftParts = explode('.', $left);
        $rightParts = explode('.', $right);
        $length = max(count($leftParts), count($rightParts));

        for ($index = 0; $index < $length; $index++) {
            $leftPart = $leftParts[$index] ?? null;
            $rightPart = $rightParts[$index] ?? null;

            if ($leftPart === null) {
                return -1;
            }

            if ($rightPart === null) {
                return 1;
            }

            $leftNumeric = ctype_digit($leftPart);
            $rightNumeric = ctype_digit($rightPart);

            $result = match (true) {
                $leftNumeric && $rightNumeric => (int) $leftPart <=> (int) $rightPart,
                $leftNumeric => -1,
                $rightNumeric => 1,
                default => strcmp($leftPart, $rightPart) <=> 0,
            };

            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }
}
