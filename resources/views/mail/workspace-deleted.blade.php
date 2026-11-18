<x-mail::message :workspace="$workspace">
# {{ $workspace }} was deleted

@if ($deletedBy)
{{ $deletedBy }} deleted the workspace **{{ $workspace }}**.
@else
The workspace **{{ $workspace }}** was deleted.
@endif
Its projects, members and invitations are gone, and its subscription was cancelled.

Your account and your other workspaces are not affected.

<x-mail::button :url="route('filament.app.tenant')">
Open {{ config('app.name') }}
</x-mail::button>
</x-mail::message>
