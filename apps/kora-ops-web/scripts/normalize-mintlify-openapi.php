<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__).'/vendor/autoload.php';

$docsPath = dirname(__DIR__, 3).'/docs/mintlify';
$openApiPath = $docsPath.'/api/openapi.yaml';
$openApi = file_get_contents($openApiPath);

if ($openApi === false) {
    fwrite(STDERR, "Unable to read generated OpenAPI specification.\n");
    exit(1);
}

$openApi = preg_replace_callback(
    '/^(\s+example: )[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/mi',
    static fn (array $matches): string => $matches[1].'00000000-0000-4000-8000-000000000000',
    $openApi,
);
// Scribe evaluates datetime examples at generation time, which makes the spec
// differ on every run. Pin them to a stable value so the CI staleness check
// compares structure, not the clock.
$openApi = preg_replace_callback(
    '/^(\s+example: )\'?[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.?[0-9]+)?(?:Z|[+-][0-9]{2}:[0-9]{2})?\'?$/m',
    static fn (array $matches): string => $matches[1]."'2026-01-01T00:00:00+00:00'",
    $openApi,
);
$openApi = preg_replace_callback(
    '/^(\s*)example: [^\n]+\n(\s*)enum:\n((?:\s+- .*\n)+)/m',
    static function (array $matches): string {
        preg_match('/^\s+- (.+)$/m', $matches[3], $firstEnumValue);

        return $matches[1].'example: '.($firstEnumValue[1] ?? '')."\n".$matches[2].'enum:'."\n".$matches[3];
    },
    $openApi,
);
$openApi = preg_replace(
    '/^(\s+example: )(?:true|false)$/m',
    '$1false',
    $openApi,
);

// Bare `...` scalars are quoted to keep the output identical across YAML emitters.
$openApi = preg_replace('/^(\s+\S+: )\.\.\.$/m', "$1'...'", $openApi);

$pdfPath = '/api/v1/tickets/{ticket_uuid}/pdf';
$pdfPathPattern = sprintf(
    '/(  [\'\"]?%s[\'\"]?:\n.*?)(?=\n  [\'\"]?\/api\/v1\/|\z)/s',
    preg_quote($pdfPath, '/'),
);
$normalizedOpenApi = preg_replace_callback(
    $pdfPathPattern,
    static fn (array $matches): string => str_replace(
        'application/octet-stream:',
        'application/pdf:',
        $matches[1],
    ),
    $openApi,
    1,
);

if ($normalizedOpenApi === null || $normalizedOpenApi === $openApi) {
    fwrite(STDERR, "Unable to normalize the ticket PDF response in the OpenAPI specification.\n");
    exit(1);
}

$openApi = $normalizedOpenApi;

if (! str_contains($openApi, 'securitySchemes:')) {
    $openApi = preg_replace(
        '/^tags:\n/m',
        "components:\n  securitySchemes:\n    sanctum:\n      type: http\n      scheme: bearer\ntags:\n",
        $openApi,
        1,
    );
}

foreach (['quotes', 'holds'] as $resource) {
    $path = "/api/v1/bus/{$resource}";
    $nextPath = $resource === 'quotes' ? '/api/v1/bus/holds' : '/api/v1/bookings';
    $pattern = sprintf(
        '/(  %s:\n    post:\n.*?)(?=  [\'\"]?%s)/s',
        preg_quote($path, '/'),
        preg_quote($nextPath, '/'),
    );

    $openApi = preg_replace_callback(
        $pattern,
        static function (array $matches): string {
            if (str_contains($matches[1], "      security:\n")) {
                return $matches[1];
            }

            return rtrim($matches[1])."\n      security:\n        - sanctum: []\n";
        },
        $openApi,
        1,
    );
}

if ($openApi === null) {
    throw new RuntimeException('Unable to normalize generated OpenAPI specification.');
}

$navigation = json_decode(file_get_contents($docsPath.'/docs.json'), true, flags: JSON_THROW_ON_ERROR)['navigation'];
$spec = Yaml::parse($openApi, Yaml::PARSE_OBJECT_FOR_MAP);
$passengerOperations = [];
$passengerTags = [];

array_walk_recursive($navigation, static function (mixed $page) use ($docsPath, $spec, &$passengerOperations, &$passengerTags): void {
    if (! is_string($page) || ! str_starts_with($page, 'reference/')) {
        return;
    }

    $contents = file_get_contents($docsPath.'/'.$page.'.mdx');
    if ($contents === false) {
        throw new RuntimeException("Unable to read Mintlify reference page: {$page}");
    }

    if (! preg_match('/^---\R(.*?)\R---/s', $contents, $matches)) {
        return;
    }

    $api = Yaml::parse($matches[1])['api'] ?? null;
    if ($api === null) {
        return;
    }

    [$method, $path] = explode(' ', $api, 2);
    $method = strtolower($method);
    $operation = $spec->paths->{$path}->{$method} ?? throw new RuntimeException("OpenAPI operation not found for {$page}: {$api}");
    if (isset($passengerOperations[$path][$method])) {
        throw new RuntimeException("Duplicate Mintlify reference operation: {$api}");
    }

    $passengerOperations[$path][$method] = true;
    foreach ($operation->tags ?? [] as $tag) {
        $passengerTags[$tag] = true;
    }
});

if ($passengerOperations === []) {
    throw new RuntimeException('No passenger operations found in the Mintlify navigation.');
}

$spec->info->title = 'KORA BUS API Passager';
$spec->info->description = 'API REST V1 de l’application mobile passager KORA BUS.';
$spec->servers = [
    ['url' => 'https://staging.koraexpress.app', 'description' => 'Staging'],
    ['url' => 'https://prod.koraexpress.app', 'description' => 'Production'],
];
$spec->paths = (object) array_intersect_key((array) $spec->paths, $passengerOperations);
foreach ($spec->paths as $path => $item) {
    $spec->paths->{$path} = (object) array_intersect_key((array) $item, $passengerOperations[$path] + ['parameters' => true]);
}
$spec->tags = array_values(array_filter($spec->tags, static fn (object $tag): bool => isset($passengerTags[$tag->name])));
$normalizedOpenApi = Yaml::dump($spec, 20, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP);

if (file_put_contents($openApiPath, $normalizedOpenApi) === false) {
    fwrite(STDERR, "Unable to write the Mintlify OpenAPI specification.\n");
    exit(1);
}
