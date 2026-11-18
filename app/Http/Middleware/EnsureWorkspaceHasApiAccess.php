<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Packstub\Features\Facades\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API is a plan feature (`api_access` in config/packstub-features.php):
 * a workspace whose plan does not include it is refused, even with a valid
 * token, for instance after moving down to the Free plan.
 */
class EnsureWorkspaceHasApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->user();

        if (! $workspace instanceof Workspace || ! Features::active('api_access', $workspace)) {
            return response()->json([
                'message' => 'The API is not included in this workspace\'s plan.',
                'upgrade_url' => Features::upgradeUrl($workspace instanceof Workspace ? $workspace : null),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
