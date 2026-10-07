<x-mail::message>
# Relevé de facturation — {{ $periodLabel }}

Bonjour,

Le relevé de facturation consolidé de **{{ $accountName }}** (réf. {{ $accountReference }}) pour **{{ $periodLabel }}** est disponible.

**Référence :** {{ $statementReference }}
**Réservations :** {{ $reservationsCount }}
**Factures normalisées :** {{ $invoicesCount }}
**Total débits :** {{ $debitTotal }} {{ $currency }}
**Remboursements :** {{ $refundTotal }} {{ $currency }}
**Net à payer :** {{ $netTotal }} {{ $currency }}

<x-mail::button :url="$portalUrl">
Consulter mes relevés
</x-mail::button>

Les factures normalisées unitaires restent les pièces légales de chaque opération.

{{ config('app.name') }}
</x-mail::message>
