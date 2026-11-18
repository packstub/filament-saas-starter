<?php

namespace App\Http\Controllers;

use App\Support\DataExport;
use Illuminate\Http\Request;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Download your data" on the Profile page: everything the app holds about
 * the person signed in, never during a support login.
 *
 * A plain GET download rather than a file returned from a Filament action:
 * Livewire would buffer all of it and send it inside its JSON response,
 * while from a route the JSON goes to the browser as it is read.
 */
class PersonalDataExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_if(AccountSwitcher::isImpersonating(), 403);

        return DataExport::download(DataExport::forUser($request->user()), 'my-data-'.now()->format('Y-m-d').'.json');
    }
}
