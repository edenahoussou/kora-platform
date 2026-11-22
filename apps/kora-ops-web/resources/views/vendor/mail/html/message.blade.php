@props(['preview' => null, 'mailMessage' => null])
@php
    $logo = $mailMessage instanceof \Illuminate\Mail\Message
        ? $mailMessage->embed(public_path('kora-logo-invoice.png'))
        : asset('kora-logo-invoice.png');
@endphp
<x-mail::layout :preview="$preview">
    <x-slot:header>
        <x-mail::header />
    </x-slot:header>

    {{-- Body --}}
    {!! $slot !!}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {!! $subcopy !!}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer :logo="$logo">
            {{ $legalFooter }}
            </x-mail:footer>
    </x-slot:footer>
</x-mail::layout>
