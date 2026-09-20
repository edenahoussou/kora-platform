<?php

namespace App\Modules\IdentityAccess\Services;

use Illuminate\Http\Request;

/**
 * Extrait l'identifiant (clé primaire) du jeton Sanctum courant à partir du
 * header Authorization (`{id}|{plainTextToken}`), de la même façon que
 * Sanctum le fait en interne. Volontairement indépendant de
 * `currentAccessToken()` : reste correct même si le jeton a déjà été
 * supprimé (session révoquée) ou si l'app personnalise le modèle de jeton.
 */
class OpsSessionTokenReader
{
    public function tokenId(Request $request): ?int
    {
        $bearer = $request->bearerToken();

        if (! $bearer || ! str_contains($bearer, '|')) {
            return null;
        }

        [$id] = explode('|', $bearer, 2);

        return ctype_digit($id) ? (int) $id : null;
    }
}
