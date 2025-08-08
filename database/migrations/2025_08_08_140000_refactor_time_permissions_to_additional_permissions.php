<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(config('oo-role-permission.tables.time_permissions', 'oo_time_permissions'), function (Blueprint $table) {
            // Remove old permission_key column
            $table->dropColumn('permission_key');

            // Add new additional_permissions column (consistent with role_models table)
            $table->json('additional_permissions')->nullable()->after('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(config('oo-role-permission.tables.time_permissions', 'oo_time_permissions'), function (Blueprint $table) {
            // Add back permission_key column
            $table->string('permission_key')->nullable()->after('role_id');

            // Remove additional_permissions column
            $table->dropColumn('additional_permissions');
        });
    }
};
