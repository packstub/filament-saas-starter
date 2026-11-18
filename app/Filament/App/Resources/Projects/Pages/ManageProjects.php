<?php

namespace App\Filament\App\Resources\Projects\Pages;

use App\Actions\CreateProject;
use App\Filament\App\Pages\Billing;
use App\Filament\App\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Packstub\Features\Exceptions\FeaturesException;
use Packstub\Features\Facades\Features;

class ManageProjects extends ManageRecords
{
    protected static string $resource = ProjectResource::class;

    public function getSubheading(): ?string
    {
        $limit = Features::limit('projects');

        return $limit === null
            ? null
            : Features::used('projects').' of '.$limit.' projects on the '.Features::planLabel().' plan';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Shown instead of "New project" once the plan is full.
            Action::make('upgrade')
                ->label('Upgrade for more projects')
                ->icon(Heroicon::OutlinedArrowUpCircle)
                ->url(fn (): string => Billing::getUrl())
                ->visible(fn (): bool => ! Features::canUse('projects') && Billing::canAccess()),

            // ProjectPolicy::create() hides this at the limit. App\Actions\CreateProject checks it again
            // while saving, under a lock per workspace, so a form opened while there was room and two
            // people saving at the same moment cannot take the workspace past its plan.
            CreateAction::make()
                ->using(function (array $data, CreateAction $action): Project {
                    try {
                        return CreateProject::in(Filament::getTenant(), $data);
                    } catch (FeaturesException $exception) {
                        $this->refuse($action, $exception->getMessage());
                    } catch (LockTimeoutException) {
                        $this->refuse($action, 'Someone else is adding a project right now', 'Try again in a moment.');
                    }
                }),
        ];
    }

    /**
     * Stop the create with a message and keep the form open.
     */
    protected function refuse(CreateAction $action, string $title, ?string $body = null): never
    {
        Notification::make()->danger()->title($title)->body($body)->send();

        $action->halt();
    }
}
