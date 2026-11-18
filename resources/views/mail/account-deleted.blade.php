<x-mail::message>
# {{ __('mail.account_deleted.heading') }}

{{ __('mail.account_deleted.greeting', ['name' => $name]) }}

{{ __('mail.account_deleted.body', ['app' => config('app.name')]) }}

{{ __('mail.account_deleted.contact', ['email' => config('site.contact_email')]) }}
</x-mail::message>
