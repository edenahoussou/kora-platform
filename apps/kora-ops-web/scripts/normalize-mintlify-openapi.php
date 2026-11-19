<?php

declare(strict_types=1);

$openApiPath = dirname(__DIR__, 3).'/docs/mintlify/api/openapi.yaml';
$openApi = file_get_contents($openApiPath);

if ($openApi === false) {
    fwrite(STDERR, "Unable to read generated OpenAPI specification.\n");
    exit(1);
}

$openApi = preg_replace(
    '/^  title: .*$/m',
    "  title: 'Kora Platforme Web API Documentation'",
    $openApi,
    1,
);
$openApi = preg_replace(
    '/^  - url: .*$/m',
    "  - url: 'https://staging.koraexpress.app'\n    description: Staging\n  - url: 'https://prod.koraexpress.app'\n    description: Production",
    $openApi,
    1,
);
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

// Scribe discovers Laravel's broadcasting authorization route but cannot infer
// its dynamically generated channel authorization response.
$broadcastingPath = '/api/v1/broadcasting/auth';
$broadcastingPathPattern = sprintf(
    '/(  %s:\n    get:\n.*?      responses:) \{\}/s',
    preg_quote($broadcastingPath, '/'),
);
$normalizedOpenApi = preg_replace(
    $broadcastingPathPattern,
    "$1\n        200:\n          description: Channel authorization payload for an authenticated subscriber.\n        403:\n          description: The authenticated user is not authorized to subscribe to the channel.",
    $openApi,
    1,
);

if ($normalizedOpenApi === null || $normalizedOpenApi === $openApi) {
    fwrite(STDERR, "Unable to normalize the broadcasting authorization response in the OpenAPI specification.\n");
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

if ($openApi === null || file_put_contents($openApiPath, $openApi) === false) {
    fwrite(STDERR, "Unable to normalize generated OpenAPI specification.\n");
    exit(1);
}
