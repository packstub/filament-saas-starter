{{-- Filament Teams' invitation e-mail, with the workspace's name and logo at the top. --}}
<x-mail::message :workspace="$teamName" :logo="$invitation->team?->getFilamentAvatarUrl()">
# {{ __('packstub-teams::teams.mail.heading', ['team' => $teamName]) }}

@if ($inviter)
{{ __('packstub-teams::teams.mail.intro_by', ['inviter' => $inviter->name, 'team' => $teamName, 'role' => $role]) }}
@else
{{ __('packstub-teams::teams.mail.intro', ['team' => $teamName, 'role' => $role]) }}
@endif

<x-mail::button :url="$acceptUrl">
{{ __('packstub-teams::teams.mail.button') }}
</x-mail::button>

@if ($expiresAt)
{{ __('packstub-teams::teams.mail.expires', ['date' => $expiresAt->toDayDateTimeString()]) }}
@endif

{{ __('packstub-teams::teams.mail.ignore') }}
</x-mail::message>
