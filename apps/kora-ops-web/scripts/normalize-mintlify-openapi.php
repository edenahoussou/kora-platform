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

$spec->info->title = 'KORA API Passager';
$spec->info->description = 'API REST V1 de l’application mobile passager KORA : Bus et Colis.';
$spec->servers = [
    ['url' => 'https://staging.koraexpress.app', 'description' => 'Staging'],
    ['url' => 'https://prod.koraexpress.app', 'description' => 'Production'],
];
$spec->paths = (object) array_intersect_key((array) $spec->paths, $passengerOperations);
foreach ($spec->paths as $path => $item) {
    $spec->paths->{$path} = (object) array_intersect_key((array) $item, $passengerOperations[$path] + ['parameters' => true]);
}
$spec->tags = array_values(array_filter($spec->tags, static fn (object $tag): bool => isset($passengerTags[$tag->name])));

// Scribe ajoute parfois un requestBody aux GET (paramètres de requête mal
// classés). Un GET ne doit pas exposer de corps de requête dans le contrat :
// les propriétés du schéma deviennent des paramètres de query.
foreach ($spec->paths as $item) {
    foreach ($item as $method => $operation) {
        if ($method !== 'get' || ! is_object($operation) || ! isset($operation->requestBody)) {
            continue;
        }

        $schema = $operation->requestBody->content->{'application/json'}->schema ?? null;
        if (is_object($schema) && isset($schema->properties)) {
            $required = is_array($schema->required ?? null) ? $schema->required : [];
            $parameters = is_array($operation->parameters ?? null) ? $operation->parameters : [];
            $existing = array_column(array_filter($parameters, 'is_array'), 'name');

            foreach ($schema->properties as $name => $property) {
                if (! is_object($property) || in_array($name, $existing, true)) {
                    continue;
                }

                $parameters[] = [
                    'name' => $name,
                    'in' => 'query',
                    'required' => in_array($name, $required, true),
                    'schema' => $property,
                ];
            }

            $operation->parameters = $parameters;
        }

        unset($operation->requestBody);
    }
}

// Scribe omet la réponse 401 sur certaines opérations protégées. Le contrat
// exposé aux intégrateurs doit toujours distinguer « non authentifié » de
// « non autorisé », donc on réutilise un 401 canonique déjà généré.
$canonicalUnauthorized = null;
foreach ($spec->paths as $item) {
    foreach ($item as $method => $operation) {
        if (! is_object($operation) || ! in_array($method, ['get', 'post', 'delete', 'put', 'patch'], true)) {
            continue;
        }

        if (isset($operation->responses->{'401'})) {
            $canonicalUnauthorized = clone $operation->responses->{'401'};

            break 2;
        }
    }
}

foreach ($spec->paths as $item) {
    foreach ($item as $method => $operation) {
        if (! is_object($operation) || ! in_array($method, ['get', 'post', 'delete', 'put', 'patch'], true)) {
            continue;
        }

        $security = $operation->security ?? $spec->security ?? [];
        if ((array) $security === [] || ! isset($operation->responses->{'403'})) {
            continue;
        }

        if (isset($operation->responses->{'401'})) {
            continue;
        }

        $operation->responses->{'401'} = $canonicalUnauthorized !== null
            ? clone $canonicalUnauthorized
            : clone $operation->responses->{'403'};
    }
}

// Scribe documente parfois un contenu sur un 204. Un 204 ne transporte pas de
// corps HTTP ; retirer ce contenu garde le contrat conforme au protocole.
foreach ($spec->paths as $item) {
    foreach ($item as $operation) {
        if (! is_object($operation) || ! isset($operation->responses->{'204'})) {
            continue;
        }

        unset($operation->responses->{'204'}->content);
    }
}

// Certaines annotations Scribe annoncent un message 403 métier alors que
// l’exception d’autorisation Laravel renvoie le message générique. Aligner le
// contrat sur la réponse réellement observée.
foreach ($spec->paths as $item) {
    foreach ($item as $method => $operation) {
        if (! is_object($operation) || ! isset($operation->responses->{'403'})) {
            continue;
        }

        $media = $operation->responses->{'403'}->content->{'application/json'} ?? null;
        $schema = is_object($media) ? ($media->schema ?? null) : null;
        if (! is_object($schema)) {
            continue;
        }

        if (isset($schema->example->message)) {
            $schema->example->message = 'This action is unauthorized.';
        }

        if (isset($schema->properties->message->example)) {
            $schema->properties->message->example = 'This action is unauthorized.';
        }
    }
}

// Scribe ne fournit pas toujours d’exemple de corps de requête alors qu’il
// expose les exemples de chaque champ. Recomposer un exemple depuis le schéma
// garde les requêtes documentées sans ajouter de données arbitraires.
$exampleFromSchema = static function (object $schema) use (&$exampleFromSchema): mixed {
    if (property_exists($schema, 'example')) {
        return $schema->example;
    }

    if (isset($schema->properties)) {
        $example = new stdClass;
        $hasValue = false;
        foreach ($schema->properties as $name => $property) {
            if (! is_object($property)) {
                continue;
            }

            $value = $exampleFromSchema($property);
            if ($value !== null) {
                $example->{$name} = $value;
                $hasValue = true;
            }
        }

        return $hasValue ? $example : null;
    }

    if (isset($schema->items) && is_object($schema->items)) {
        $value = $exampleFromSchema($schema->items);

        return $value === null ? [] : [$value];
    }

    if (isset($schema->oneOf) && is_array($schema->oneOf)) {
        foreach ($schema->oneOf as $branch) {
            if (! is_object($branch)) {
                continue;
            }

            $value = $exampleFromSchema($branch);
            if ($value !== null) {
                return $value;
            }
        }
    }

    return null;
};

foreach ($spec->paths as $item) {
    foreach ($item as $method => $operation) {
        if (! is_object($operation) || ! in_array($method, ['get', 'post', 'delete', 'put', 'patch'], true)) {
            continue;
        }

        $media = $operation->requestBody->content->{'application/json'} ?? null;
        if (! is_object($media) || isset($media->example) || isset($media->examples)) {
            continue;
        }

        $schema = $media->schema ?? null;
        if (! is_object($schema)) {
            continue;
        }

        $example = $exampleFromSchema($schema);
        if ($example !== null) {
            $media->example = $example;
        }
    }
}

// Même correction pour les réponses 200/201 dont le contenu JSON n’a pas
// d’exemple de premier niveau (notamment les réponses en oneOf).
foreach ($spec->paths as $item) {
    foreach ($item as $method => $operation) {
        if (! is_object($operation) || ! in_array($method, ['get', 'post', 'delete', 'put', 'patch'], true)) {
            continue;
        }

        foreach ($operation->responses ?? [] as $status => $response) {
            if (! in_array((int) $status, [200, 201], true) || ! is_object($response)) {
                continue;
            }

            $media = $response->content->{'application/json'} ?? null;
            if (! is_object($media) || isset($media->example) || isset($media->examples)) {
                continue;
            }

            $schema = $media->schema ?? null;
            if (! is_object($schema)) {
                continue;
            }

            $example = $exampleFromSchema($schema);
            if ($example !== null) {
                $media->example = $example;
            }
        }
    }
}

// Scribe génère les réponses en schémas inline. Conserver les schémas nommés
// exposés aux intégrateurs, dans un fichier stable pour que la régénération
// OpenAPI ne les supprime pas et que le contrôle CI reste déterministe.
$schemasPath = dirname(__DIR__).'/scripts/openapi-schemas.yaml';
if (is_file($schemasPath)) {
    $schemas = Yaml::parseFile($schemasPath, Yaml::PARSE_OBJECT_FOR_MAP);
    $spec->components->schemas = $schemas->schemas ?? new stdClass;

    // Certains FormRequest combinent règles dynamiques et annotations Scribe :
    // leur corps n’est pas généré. Le fixture fournit alors le contrat explicite.
    foreach ($schemas->requestBodies ?? [] as $path => $methods) {
        if (! isset($spec->paths->{$path}) || ! is_object($methods)) {
            continue;
        }

        foreach ($methods as $method => $requestBody) {
            $operation = $spec->paths->{$path}->{$method} ?? null;
            if (is_object($operation)) {
                $operation->requestBody = $requestBody;
            }
        }
    }
}

$normalizedOpenApi = Yaml::dump($spec, 20, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_OBJECT_AS_MAP);

if (file_put_contents($openApiPath, $normalizedOpenApi) === false) {
    fwrite(STDERR, "Unable to write the Mintlify OpenAPI specification.\n");
    exit(1);
}
