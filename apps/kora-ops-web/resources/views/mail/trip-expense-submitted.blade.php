<x-mail::message :mail-message="$message ?? null" preview="Une dépense de voyage attend votre validation dans KORA OPS.">
# Dépense de voyage à valider

Une dépense **{{ mb_strtolower($kindLabel) }}** de **{{ $amount }} {{ $currency }}** vient d'être saisie depuis KORA OPS Mobile{{ $submitter ? ' par '.$submitter : '' }}. Elle est **{{ mb_strtolower($statusLabel) }}** de validation au bureau.

## La dépense

<x-mail::table>
| | |
|:--|:--|
| **Référence** | {{ $reference }} |
| **Type** | {{ $kindLabel }}{{ $category ? ' ('.$category.')' : '' }} |
| **Montant** | {{ $amount }} {{ $currency }} |
| **Moyen de paiement** | {{ $paymentChannel }} |
| **Date de la dépense** | {{ $occurredAt }} |
@if ($submittedAt)
| **Saisie le** | {{ $submittedAt }} |
@endif
| **Agence** | {{ $agency ?? '—' }} |
| **Statut** | {{ $statusLabel }} |
| **Justificatif** | {{ $hasEvidence ? 'Photo jointe à cet e-mail' : 'Aucun' }} |
</x-mail::table>

@if ($isFuel)
## Détail carburant

<x-mail::table>
| | |
|:--|:--|
| **Quantité** | {{ $liters ?? '—' }} L |
| **Prix au litre** | {{ $unitPrice ? $unitPrice.' '.$currency : '—' }} |
| **Station** | {{ $station ?? '—' }} |
| **Compteur au plein** | {{ $odometerKm ?? '—' }} |
</x-mail::table>
@else
## Détail {{ mb_strtolower($kindLabel) }}

<x-mail::table>
| | |
|:--|:--|
| **Poste** | {{ $checkpoint ?? '—' }} |
| **Pays** | {{ $country ?? '—' }} |
| **Compteur** | {{ $odometerKm ?? '—' }} |
</x-mail::table>
@endif

## Le voyage

<x-mail::table>
| | |
|:--|:--|
| **Ligne** | {{ $line ?? '—' }} |
| **Départ prévu** | {{ $departureAt ?? '—' }} |
| **État du départ** | {{ $departureStatus ?? '—' }} |
| **Bus** | {{ $bus ?? '—' }} |
| **Chauffeur** | {{ $driver ?? '—' }} |
| **Convoyeur** | {{ $conductor ?? '—' }} |
| **Kilométrage départ** | {{ $startKm ?? 'non saisi' }} |
| **Kilométrage arrivée** | {{ $endKm ?? 'non saisi' }} |
| **Distance parcourue** | {{ $distance ?? '—' }} |
@if ($consumption)
| **Consommation estimée** | {{ $consumption }} |
@endif
</x-mail::table>

## Saisi par

<x-mail::table>
| | |
|:--|:--|
| **Nom** | {{ $submitter ?? '—' }} |
| **Rôle** | {{ $submitterRole ?? '—' }} |
| **Téléphone** | {{ $submitterPhone ?? '—' }} |
| **Terminal** | {{ $terminal ?? 'Téléphone (sans terminal P501)' }} |
</x-mail::table>

@if (count($totals) > 0)
## Cumul des dépenses de ce voyage (en attente + approuvées)

<x-mail::table>
| Type | Saisies | Montant |
|:--|:--:|--:|
@foreach ($totals as $row)
| {{ $row['label'] }} | {{ $row['count'] }} | {{ $row['amount'] }} {{ $currency }} |
@endforeach
| **Total** | | **{{ $tripTotal }} {{ $currency }}** |
</x-mail::table>
@endif

@if ($description)
**Libellé enregistré :** {{ $description }}
@endif

<x-mail::button :url="$url">
Valider ou rejeter (Finance › Dépenses)
</x-mail::button>

@if ($busUrl)
[Voir la fiche du bus — carburant et kilométrage]({{ $busUrl }})
@endif

La dépense n'est comptabilisée (rentabilité, caisse) qu'après approbation par un responsable habilité (responsable d'agence, DAF, direction générale ou administrateur).
</x-mail::message>
