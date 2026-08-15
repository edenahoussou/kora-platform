@props(['url'])
@php
    $logoPath = public_path('kora-logo-email.png');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : asset('kora-logo-light-v2.png');
@endphp
<tr>
    <td class="header" style="background-color: #001d4e; padding: 28px 0; text-align: center;">
        <a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
            @if (trim($slot) === 'Laravel' || trim($slot) === config('app.name'))
                <img src="{{ $logoSrc }}" style="height: 48px; width: auto; max-width: 200px;" alt="KORA EXPRESS">
            @else
                {!! $slot !!}
            @endif
        </a>
    </td>
</tr>
