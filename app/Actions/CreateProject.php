<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Packstub\Features\Exceptions\FeatureLimitReached;
use Packstub\Features\Facades\Features;

/**
 * Create a project in a workspace, within its plan's `projects` limit. The
 * panel (ManageProjects) comes through here, and so should any other way
 * of making one, so the limit holds everywhere.
 *
 * The limit is checked under a lock per workspace: a form opened while
 * there was room, or two requests at the same moment, cannot take the
 * workspace past its plan.
 */
final class CreateProject
{
    /**
     * @param  array{name: string, status?: mixed}  $data
     *
     * @throws FeatureLimitReached when the plan has no room for another project
     * @throws LockTimeoutException when someone else is adding one right now
     */
    public static function in(Workspace $workspace, array $data): Project
    {
        return Cache::lock('projects:'.$workspace->getKey(), 10)->block(3, function () use ($workspace, $data): Project {
            Features::assertCanUse('projects', $workspace);

            return $workspace->projects()->create($data);
        });
    }
}
