<x-mail::message :mail-message="$message ?? null" :preview="$title">
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
</x-mail::message>
