@props(['workspace' => null, 'logo' => null])
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ $workspace ?? config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            @if ($workspace)
                {{ __('mail.sent_by', ['app' => config('app.name'), 'workspace' => $workspace]) }}
            @endif
            © {{ date('Y') }} {{ config('site.company') }} · {{ config('site.contact_email') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
