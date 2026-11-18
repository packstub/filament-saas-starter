<?php

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Schedule;

// Housekeeping for the plugins: expired invitations and expired feature overrides.
Schedule::command('teams:prune-invitations')->daily();
Schedule::command('features:prune')->daily();

// API tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired', ['--hours' => 24])->daily();

// The workspaces' audit log keeps `audit.retention_days` of history.
Schedule::command('model:prune', ['--model' => [AuditEvent::class]])->daily();
