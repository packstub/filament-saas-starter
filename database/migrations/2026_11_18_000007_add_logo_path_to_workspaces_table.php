<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The workspace's logo, a path on the uploads disk (config/uploads.php)
     * under the workspace's own folder (App\Support\Uploads).
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (! Schema::hasColumn('workspaces', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
