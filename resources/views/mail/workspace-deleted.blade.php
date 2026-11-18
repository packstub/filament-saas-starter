<x-mail::message :workspace="$workspace">
# {{ __('mail.workspace_deleted.heading', ['workspace' => $workspace]) }}

@if ($deletedBy)
{{ __('mail.workspace_deleted.deleted_by', ['person' => $deletedBy, 'workspace' => $workspace]) }}
@else
{{ __('mail.workspace_deleted.deleted', ['workspace' => $workspace]) }}
@endif
{{ __('mail.workspace_deleted.gone') }}

{{ __('mail.workspace_deleted.unaffected') }}

<x-mail::button :url="route('filament.app.tenant')">
{{ __('mail.workspace_deleted.button', ['app' => config('app.name')]) }}
</x-mail::button>
</x-mail::message>
