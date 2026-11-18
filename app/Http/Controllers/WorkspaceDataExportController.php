<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\DataExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Export workspace data" in Workspace settings: everything a workspace
 * holds. The route checks WorkspacePolicy::export() first (owners, never
 * during a support login). A GET download for the reason given in
 * PersonalDataExportController.
 */
class WorkspaceDataExportController
{
    public function __invoke(Workspace $workspace): StreamedResponse
    {
        return DataExport::download(DataExport::forWorkspace($workspace), $workspace->slug.'-'.now()->format('Y-m-d').'.json');
    }
}
