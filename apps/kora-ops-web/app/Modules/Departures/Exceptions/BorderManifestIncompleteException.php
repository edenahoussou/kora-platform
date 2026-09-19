<?php

namespace App\Modules\Departures\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Le manifeste frontière d'un départ international ne peut pas être émis :
 * au moins un passager n'a pas toutes les informations obligatoires au
 * contrôle (nom, nationalité, numéro de document, siège, destination).
 *
 * Se rend elle-même en 422 `manifest_incomplete` (même convention que
 * BoardingTicketUnknownException) — l'app OPS Mobile s'appuie sur ce code
 * pour refuser l'impression et afficher les passagers à compléter.
 */
class BorderManifestIncompleteException extends RuntimeException
{
    public const string CODE = 'manifest_incomplete';

    /**
     * @param  list<array{name: string, seat: string, missing: list<string>}>  $incompletePassengers
     */
    public function __construct(private readonly array $incompletePassengers, ?string $message = null)
    {
        parent::__construct($message ?? sprintf(
            'Manifeste frontière incomplet : %d passager(s) sans toutes les informations obligatoires.',
            count($incompletePassengers),
        ));
    }

    /**
     * Aucun passager à faire figurer sur le manifeste (tous annulés / no-show
     * ou départ sans billet émis) : rien à présenter à la frontière.
     */
    public static function noPassengers(): self
    {
        return new self([], 'Aucun passager à faire figurer sur le manifeste frontière.');
    }

    /**
     * @return list<array{name: string, seat: string, missing: list<string>}>
     */
    public function incompletePassengers(): array
    {
        return $this->incompletePassengers;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => self::CODE,
                'message' => $this->getMessage(),
                'passengers' => $this->incompletePassengers,
            ],
        ], 422);
    }
}
