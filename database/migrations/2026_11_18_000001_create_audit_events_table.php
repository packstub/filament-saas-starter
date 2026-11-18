<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The workspaces' audit log (App\Models\AuditEvent). Central, like billing
     * and the support-login log: it is read from the admin panel across all
     * workspaces and keeps one entry for a workspace after it is deleted, so it
     * stays here if workspaces ever get databases of their own (exclude it from
     * `tenants:move`). A ULID key and no foreign keys, so an entry outlives the
     * people and the workspace it names; their names are kept as they were.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('workspace_id');
            $table->string('workspace_name');
            $table->string('event');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            // The admin behind a support login, when the actor was signed in as by one.
            $table->unsignedBigInteger('impersonator_id')->nullable();
            $table->string('impersonator_name')->nullable();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
