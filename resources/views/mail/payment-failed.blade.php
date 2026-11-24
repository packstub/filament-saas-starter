<x-mail::message :workspace="$workspace" :logo="$logo">
# {{ __('ui_mail.payment_failed.heading') }}

{{ __('ui_mail.payment_failed.body', ['workspace' => $workspace]) }}

{{ __('ui_mail.payment_failed.update_card') }}

<x-mail::button :url="$url">
{{ __('ui_mail.payment_failed.button') }}
</x-mail::button>

{{ __('ui_mail.payment_failed.reason', ['workspace' => $workspace]) }}
</x-mail::message>
