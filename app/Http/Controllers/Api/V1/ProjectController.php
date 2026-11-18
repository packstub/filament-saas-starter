<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateProject;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Workspace;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Packstub\Features\Exceptions\FeatureLimitReached;
use Packstub\Features\Facades\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * The example API: a workspace's projects. The token decides the workspace
 * (`$request->user()` is the Workspace that owns it), so every query starts
 * from it and another workspace's projects are never in reach.
 */
class ProjectController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            $this->workspace($request)->projects()->latest()->paginate(50),
        );
    }

    public function show(Request $request, string $project): ProjectResource
    {
        return new ProjectResource($this->workspace($request)->projects()->findOrFail($project));
    }

    /**
     * Within the plan's `projects` limit, like New project in the panel.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        try {
            $project = CreateProject::in($workspace, $request->validated());
        } catch (FeatureLimitReached $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'limit' => $exception->limit,
                'upgrade_url' => Features::upgradeUrl($workspace),
            ], Response::HTTP_FORBIDDEN);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Someone else is adding a project right now. Try again in a moment.'], Response::HTTP_CONFLICT);
        }

        return (new ProjectResource($project))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    protected function workspace(Request $request): Workspace
    {
        return $request->user();
    }
}
