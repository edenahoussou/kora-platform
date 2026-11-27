<x-mail::message :mail-message="$message ?? null" preview="Une demande de réservation attend votre suivi sur WhatsApp.">
Bonjour,

@if ($reference)
@if ($passengerCount > 0)
**{{ $contactName }}** souhaite réserver **{{ $passengerCount }} {{ $passengerCount > 1 ? 'places' : 'place' }}**.
@else
**{{ $contactName }}** souhaite faire une réservation.
@endif

Vous pouvez échanger avec cette personne sur **WhatsApp** au **{{ $contactPhone }}**.

**Référence de réservation** — {{ $reference }}

@if ($itinerary !== [])
**Trajet et places demandées**

@foreach ($itinerary as $leg)
- **{{ $leg['route'] }}** · {{ $leg['departure'] }} · Places souhaitées — {{ implode(', ', $leg['seats']) ?: 'À choisir' }}
@endforeach

@endif
@if ($ticketCodes)
**Références des billets émis** — {{ $ticketCodes }}
@else
Les places sont à confirmer et aucun billet n’a encore été émis.
@endif

Merci de prendre en charge sa demande et de poursuivre l’échange sur WhatsApp pour finaliser sa réservation.
@else
Ouvrez la file des réservations assistées pour consulter les demandes disponibles.
@endif

[{{ $reference ? 'Ouvrir la demande dans KORA OPS' : 'Voir les demandes' }}]({{ $detailUrl }})
</x-mail::message>
