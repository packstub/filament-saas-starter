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
        return 'Workspace settings';
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
            Section::make('Export workspace data')
                ->description('The workspace, its members, invitations, subscriptions, overrides and projects, as one JSON file.')
                ->visible(fn (): bool => $this->canExport())
                ->schema([
                    Actions::make([$this->exportAction()]),
                ]),
            Section::make('Delete workspace')
                ->description('Its projects, members, invitations and subscription go with it, for good.')
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
            ->label('Logo')
            ->helperText('Shown in the workspace menu and at the top of the e-mails about this workspace. PNG, JPEG or WebP; square works best.')
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
            ->label('Download workspace data')
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
            ->label('Delete workspace')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize(fn (): bool => $this->canDelete())
            ->requiresConfirmation()
            ->modalHeading(fn (): string => "Delete {$this->workspace()->name}?")
            ->modalDescription('Its projects, members, invitations and subscription are removed, and the subscription is cancelled now. This cannot be undone.')
            ->modalSubmitActionLabel('Delete workspace')
            ->schema([
                TextInput::make('name')
                    ->label('Type the name of the workspace to confirm')
                    ->required()
                    ->in(fn (): array => [$this->workspace()->name])
                    ->validationMessages(['in' => 'That is not the name of this workspace.']),
            ])
            ->action(function (Action $action) {
                try {
                    $this->workspace()->delete();
                } catch (ApiErrorException $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title('The subscription could not be cancelled, so the workspace was kept')
                        ->body('Please try again in a moment.')
                        ->send();

                    $action->halt();
                }

                Notification::make()->success()->title('Workspace deleted')->send();

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
