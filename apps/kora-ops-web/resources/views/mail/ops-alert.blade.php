<x-mail::message>
# {{ $title }}

{{ $alertMessage }}

@if ($departure)
**Départ :** {{ $departure }}
@endif

@if ($terminal)
**Terminal :** {{ $terminal }}
@endif

@if ($convoyeur)
**Convoyeur :** {{ $convoyeur }}
@endif

**Date :** {{ $occurredAt }}
**Code :** {{ $code }}

Cette alerte est aussi disponible dans les notifications KORA OPS.

{{ config('app.name') }}
</x-mail::message>
