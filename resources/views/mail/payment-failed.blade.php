<x-mail::message :workspace="$workspace" :logo="$logo">
# {{ __('mail.payment_failed.heading') }}

{{ __('mail.payment_failed.body', ['workspace' => $workspace]) }}

{{ __('mail.payment_failed.update_card') }}

<x-mail::button :url="$url">
{{ __('mail.payment_failed.button') }}
</x-mail::button>

{{ __('mail.payment_failed.reason', ['workspace' => $workspace]) }}
</x-mail::message>
