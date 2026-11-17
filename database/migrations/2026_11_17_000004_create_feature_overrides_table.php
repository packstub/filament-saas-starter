<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('packstub-features.connection'))->create(config('packstub-features.overrides_table', 'feature_overrides'), function (Blueprint $table): void {
            $table->id();
            // Null scope = global override (kill switch / global rollout).
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('feature');
            $table->json('value')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'feature']);
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::connection(config('packstub-features.connection'))->dropIfExists(config('packstub-features.overrides_table', 'feature_overrides'));
    }
};
