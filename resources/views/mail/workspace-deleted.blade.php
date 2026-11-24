<x-mail::message :workspace="$workspace">
# {{ __('ui_mail.workspace_deleted.heading', ['workspace' => $workspace]) }}

@if ($deletedBy)
{{ __('ui_mail.workspace_deleted.deleted_by', ['person' => $deletedBy, 'workspace' => $workspace]) }}
@else
{{ __('ui_mail.workspace_deleted.deleted', ['workspace' => $workspace]) }}
@endif
{{ __('ui_mail.workspace_deleted.gone') }}

{{ __('ui_mail.workspace_deleted.unaffected') }}

<x-mail::button :url="route('filament.app.tenant')">
{{ __('ui_mail.workspace_deleted.button', ['app' => config('app.name')]) }}
</x-mail::button>
</x-mail::message>
