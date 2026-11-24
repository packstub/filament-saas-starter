<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Models\Workspace;
use App\Support\Uploads;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Stripe\Exception\ApiErrorException;

/**
 * "Workspace settings" in the workspace menu, for owners and admins
 * (WorkspacePolicy::update()). The slug stays as it is so links keep working.
 * Owners also find "Export workspace data" and "Delete workspace" here
 * (WorkspacePolicy::export() and delete()).
 */
class EditWorkspace extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return __('ui_workspace.settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(Workspace::NAME_MAX_LENGTH),
            $this->logoUpload(),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            Section::make(__('ui_workspace.export.heading'))
                ->description(__('ui_workspace.export.description'))
                ->visible(fn (): bool => $this->canExport())
                ->schema([
                    Actions::make([$this->exportAction()]),
                ]),
            Section::make(__('ui_workspace.delete.heading'))
                ->description(__('ui_workspace.delete.description'))
                ->visible(fn (): bool => $this->canDelete())
                ->schema([
                    Actions::make([$this->deleteAction()]),
                ]),
        ]);
    }

    /**
     * The workspace's logo, in its own folder on the uploads disk (config/uploads.php).
     * Workspace removes the file it replaces, and the folder goes with the workspace.
     */
    protected function logoUpload(): FileUpload
    {
        return FileUpload::make('logo_path')
            ->label(__('ui_workspace.logo.label'))
            ->helperText(__('ui_workspace.logo.help'))
            ->image()
            ->avatar()
            ->disk(Uploads::diskName())
            ->visibility(config('uploads.visibility'))
            ->directory(fn (): string => Uploads::workspaceDirectory($this->workspace()).'/logo')
            ->acceptedFileTypes(config('uploads.image_types'))
            ->maxSize(config('uploads.max_size'));
    }

    /**
     * Everything the workspace holds (App\Support\DataExport::forWorkspace()), for
     * owners, downloaded from App\Http\Controllers\WorkspaceDataExportController.
     */
    public function exportAction(): Action
    {
        return Action::make('export')
            ->label(__('ui_workspace.export.action'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize(fn (): bool => $this->canExport())
            ->url(fn (): string => route('exports.workspace', $this->workspace()));
    }

    /**
     * Owners only, after typing the workspace's name. Workspace::delete()
     * cancels the Stripe subscription first, then removes everything the
     * workspace owns in one transaction; if Stripe refuses, nothing is
     * deleted and the owner is told.
     */
    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('ui_workspace.delete.action'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize(fn (): bool => $this->canDelete())
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('ui_workspace.delete.modal_heading', ['workspace' => $this->workspace()->name]))
            ->modalDescription(__('ui_workspace.delete.modal_description'))
            ->modalSubmitActionLabel(__('ui_workspace.delete.action'))
            ->schema([
                TextInput::make('name')
                    ->label(__('ui_workspace.delete.confirm_name'))
                    ->required()
                    ->in(fn (): array => [$this->workspace()->name])
                    ->validationMessages(['in' => __('ui_workspace.delete.wrong_name')]),
            ])
            ->action(function (Action $action) {
                try {
                    $this->workspace()->delete();
                } catch (ApiErrorException $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title(__('ui_workspace.delete.stripe_failed'))
                        ->body(__('ui_workspace.delete.try_again'))
                        ->send();

                    $action->halt();
                }

                Notification::make()->success()->title(__('ui_workspace.delete.deleted'))->send();

                // The panel picks another workspace of theirs, or offers to create one.
                return redirect()->route('filament.app.tenant');
            });
    }

    protected function canExport(): bool
    {
        return (bool) Filament::auth()->user()?->can('export', $this->workspace());
    }

    protected function canDelete(): bool
    {
        return (bool) Filament::auth()->user()?->can('delete', $this->workspace());
    }

    protected function workspace(): Workspace
    {
        return $this->tenant;
    }
}
