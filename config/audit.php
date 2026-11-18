<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace audit log
    |--------------------------------------------------------------------------
    |
    | Days an entry of the audit log (App\Models\AuditEvent) is kept before
    | `model:prune` removes it; the schedule in routes/console.php runs it
    | daily. An entry keeps the names and e-mail addresses of the people it
    | is about, so keep it no longer than your privacy policy says.
    |
    */

    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

];
