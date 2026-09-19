<?php

namespace App\Modules\IdentityAccess\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Matériel derrière un enregistrement `terminals_p501` — un boîtier P501
 * physique, ou un téléphone en mode caméra (voir ticket "Supporter le
 * scan par caméra téléphone en alternative au P501"). Même table, mêmes
 * statuts (`TerminalP501Status`), même contrat de handshake/assignation
 * des deux côtés : seul [Phone] change la forme du `serial_number`
 * attendu (empreinte matérielle de l'appareil plutôt qu'un numéro de
 * série imprimé) — voir `RegisterTerminalRequest`.
 */
enum TerminalType: string
{
    use HasEnumUtilities;

    case P501 = 'p501';
    case Phone = 'phone';
}
