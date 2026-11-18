<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When someone dismissed the dashboard's Get started checklist
     * (App\Filament\App\Widgets\GettingStarted), for everyone in the workspace.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            if (! Schema::hasColumn('workspaces', 'onboarding_dismissed_at')) {
                $table->timestamp('onboarding_dismissed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('onboarding_dismissed_at');
        });
    }
};
