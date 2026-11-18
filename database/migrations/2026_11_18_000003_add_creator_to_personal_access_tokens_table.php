<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who created a workspace's API token (App\Models\ApiToken), by id and
     * by name as it was, with no foreign key: the token belongs to the
     * workspace and keeps working after that person leaves it.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'created_by_id')) {
                $table->unsignedBigInteger('created_by_id')->nullable();
            }

            if (! Schema::hasColumn('personal_access_tokens', 'created_by_name')) {
                $table->string('created_by_name')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['created_by_id', 'created_by_name']);
        });
    }
};
