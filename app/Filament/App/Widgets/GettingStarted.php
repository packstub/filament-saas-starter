<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Pages\Auth\EditProfile;
use App\Filament\App\Pages\Billing;
use App\Filament\App\Resources\Projects\ProjectResource;
use App\Models\User;
use App\Models\Workspace;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Packstub\Features\Facades\Features;
use Packstub\Teams\Facades\Teams;
use Packstub\Teams\Filament\Pages\Members;

/**
 * The first thing a new workspace's owners and admins see on the dashboard:
 * four steps that tick themselves off from what the workspace really has.
 * It goes away once every step is done, or when someone dismisses it
 * (`onboarding_dismissed_at` on the workspace, for everyone in it).
 */
class GettingStarted extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.app.widgets.getting-started';

    /** Set once dismissed, so the checklist disappears without a page load. */
    public bool $dismissed = false;

    public static function canView(): bool
    {
        $workspace = Filament::getTenant();
        $user = Filament::auth()->user();

        return $workspace instanceof Workspace
            && $user instanceof User
            && $workspace->onboarding_dismissed_at === null
            && $user->canManageTeam($workspace)
            && collect(static::steps($workspace, $user))->contains('done', false);
    }

    /**
     * Each step, done or not, with where to do it.
     *
     * @return list<array{key: string, title: string, description: string, done: bool, url: ?string, action: string}>
     */
    public static function steps(Workspace $workspace, User $user): array
    {
        return [
            [
                'key' => 'invite',
                'title' => __('dashboard.getting_started.steps.invite.title'),
                'description' => __('dashboard.getting_started.steps.invite.description'),
                'done' => Teams::membersOf($workspace)->count() > 1 || Teams::invitationsOf($workspace)->pending()->exists(),
                'url' => Members::getUrl(tenant: $workspace),
                'action' => __('dashboard.getting_started.steps.invite.action'),
            ],
            [
                'key' => 'project',
                'title' => __('dashboard.getting_started.steps.project.title'),
                'description' => __('dashboard.getting_started.steps.project.description'),
                'done' => $workspace->projects()->exists(),
                'url' => ProjectResource::getUrl(tenant: $workspace),
                'action' => __('dashboard.getting_started.steps.project.action'),
            ],
            [
                'key' => 'plan',
                'title' => __('dashboard.getting_started.steps.plan.title'),
                'description' => __('dashboard.getting_started.steps.plan.description'),
                'done' => $workspace->subscriptions()->exists() || Features::plan($workspace) !== config('packstub-features.default_plan'),
                'url' => Billing::canAccess() ? Billing::getUrl(tenant: $workspace) : null,
                'action' => __('dashboard.getting_started.steps.plan.action'),
            ],
            [
                'key' => 'two-factor',
                'title' => __('dashboard.getting_started.steps.two-factor.title'),
                'description' => __('dashboard.getting_started.steps.two-factor.description'),
                'done' => filled($user->getAppAuthenticationSecret()),
                'url' => EditProfile::getUrl(),
                'action' => __('dashboard.getting_started.steps.two-factor.action'),
            ],
        ];
    }

    /**
     * @return list<array{key: string, title: string, description: string, done: bool, url: ?string, action: string}>
     */
    public function getSteps(): array
    {
        return static::steps(Filament::getTenant(), Filament::auth()->user());
    }

    /**
     * Hide the checklist for everyone in the workspace.
     */
    public function dismiss(): void
    {
        $workspace = Filament::getTenant();

        abort_unless(Filament::auth()->user()?->canManageTeam($workspace), 403);

        $workspace->forceFill(['onboarding_dismissed_at' => now()])->save();

        $this->dismissed = true;
    }
}
