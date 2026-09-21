<?php

namespace App\Modules\Notifications\Data;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Événement prêt à être publié : construit par OpsNotifier (qui garantit la
 * liste blanche des données), consommé par PublishOpsNotificationAction.
 */
final readonly class OpsNotificationDraft
{
    /**
     * @param  string  $dedupeKey  Identifiant d'événement : un même événement rejoué (retry) ne crée jamais de doublon.
     * @param  array<string, mixed>  $data  Codes métier, UUID et compteurs uniquement — jamais de donnée financière.
     * @param  Collection<int, User>  $primaryRecipients  Destinataires directement concernés (équipage, titulaire du terminal…).
     * @param  User|null  $subject  Convoyeur à l'origine de l'alerte : son agence désigne les directeurs des opérations destinataires.
     */
    public function __construct(
        public OpsNotificationType $type,
        public string $dedupeKey,
        public string $message,
        public array $data,
        public Collection $primaryRecipients,
        public ?User $subject,
        public ?Departure $departure,
        public ?TerminalP501 $terminal,
        public CarbonInterface $occurredAt,
    ) {}
}
