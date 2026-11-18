<x-mail::message>
# Your account was deleted

Hello {{ $name }},

your {{ config('app.name') }} account was deleted, together with every workspace where you were the only member. You left the other workspaces you were in, and their owners keep them.

If you did not delete it yourself, reply to this e-mail or write to {{ config('site.contact_email') }}.
</x-mail::message>
