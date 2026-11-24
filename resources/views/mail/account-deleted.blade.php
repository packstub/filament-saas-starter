<x-mail::message>
# {{ __('ui_mail.account_deleted.heading') }}

{{ __('ui_mail.account_deleted.greeting', ['name' => $name]) }}

{{ __('ui_mail.account_deleted.body', ['app' => config('app.name')]) }}

{{ __('ui_mail.account_deleted.contact', ['email' => config('site.contact_email')]) }}
</x-mail::message>
