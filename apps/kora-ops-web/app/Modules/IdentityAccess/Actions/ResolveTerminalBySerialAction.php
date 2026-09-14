<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Exceptions\TerminalUnknownException;
use App\Modules\IdentityAccess\Models\TerminalP501;

class ResolveTerminalBySerialAction
{
    /**
     * Recherche insensible à la casse : le numéro de série est un
     * identifiant matériel, pas une valeur dont la casse doit faire foi.
     * On ne suppose pas que l'appelant (handshake, middleware, admin) a
     * normalisé la valeur de la même façon qu'à l'enregistrement.
     *
     * @throws TerminalUnknownException Si aucun terminal n'a ce numéro de série.
     */
    public function execute(string $serialNumber): TerminalP501
    {
        $normalized = trim($serialNumber);

        $terminal = TerminalP501::query()
            ->whereRaw('LOWER(serial_number) = ?', [mb_strtolower($normalized)])
            ->first();

        if (! $terminal) {
            throw new TerminalUnknownException;
        }

        return $terminal;
    }
}
