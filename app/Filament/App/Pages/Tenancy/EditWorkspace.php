<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Models\Workspace;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
 * Owners also find "Delete workspace" here (WorkspacePolicy::delete()).
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
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            Section::make('Delete workspace')
                ->description('Its projects, members, invitations and subscription go with it, for good.')
                ->visible(fn (): bool => $this->canDelete())
                ->schema([
                    Actions::make([$this->deleteAction()]),
                ]),
        ]);
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

    protected function canDelete(): bool
    {
        return (bool) Filament::auth()->user()?->can('delete', $this->workspace());
    }

    protected function workspace(): Workspace
    {
        return $this->tenant;
    }
}
