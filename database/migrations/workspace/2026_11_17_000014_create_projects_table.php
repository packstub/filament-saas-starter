<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            // A ULID, so rows keep their keys if a workspace ever moves to a database of its own.
            $table->ulid('id')->primary();
            // Indexed, with no foreign key on purpose: Workspace's deleting hook removes a workspace's projects.
            $table->foreignId('workspace_id')->index();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
