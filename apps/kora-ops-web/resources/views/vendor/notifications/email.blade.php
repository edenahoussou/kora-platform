<x-mail::message :mail-message="$message ?? null" :preview="$preview ?? $subject">
@if (filled($subject))
# {{ $subject }}
@endif

@if (! empty($greeting))
{{ $greeting }}
@else
@if ($level === 'error')
@lang('Whoops!')
@else
@lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

@if (! empty($welcomeCredentials))
<x-mail::panel>
**Vos accès**

@foreach ($welcomeCredentials as $label => $value)
**{{ $label }}** : {{ $value }}

@endforeach
</x-mail::panel>
@endif

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Cordialement,
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    'Si le bouton « :actionText » ne fonctionne pas, copiez ce lien dans votre navigateur.',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
