<?php

use Illuminate\Support\Facades\Schedule;

// Housekeeping for the plugins: expired invitations and expired feature overrides.
Schedule::command('teams:prune-invitations')->daily();
Schedule::command('features:prune')->daily();
