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
        // Time-based permission constraints table
        Schema::create(config('oo-role-permission.tables.time_permissions', 'oo_time_permissions'), function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(config('oo-role-permission.models.role'))
                ->constrained(config('oo-role-permission.tables.roles'))
                ->onDelete('cascade');
            $table->string('permission_key')->nullable(); // NULL = applies to all role permissions

            // Time constraints
            $table->time('start_time')->nullable();  // 09:00:00 (daily start)
            $table->time('end_time')->nullable();    // 17:00:00 (daily end)
            $table->date('start_date')->nullable();  // 2025-01-01 (date range start)
            $table->date('end_date')->nullable();    // 2025-12-31 (date range end)
            $table->string('timezone', 50)->default('UTC'); // Europe/Istanbul

            // Weekly pattern
            $table->json('days_of_week')->nullable(); // [1,2,3,4,5] (1=Monday, 7=Sunday)

            // Meta fields
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['role_id', 'permission_key'], 'idx_role_permission');
            $table->index(['is_active', 'start_date', 'end_date'], 'idx_active_dates');
            $table->index(['start_time', 'end_time'], 'idx_daily_times');
            $table->index(['timezone'], 'idx_timezone');
        });

        // Add time support to existing role_models table
        Schema::table(config('oo-role-permission.tables.role_models'), function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('additional_permissions');
            $table->timestamp('activated_at')->nullable()->after('expires_at');
            $table->string('timezone', 50)->default('UTC')->after('activated_at');

            // Performance index for time constraints
            $table->index(['expires_at', 'activated_at'], 'idx_time_constraints');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove columns from role_models table
        Schema::table(config('oo-role-permission.tables.role_models'), function (Blueprint $table) {
            $table->dropIndex('idx_time_constraints');
            $table->dropColumn(['expires_at', 'activated_at', 'timezone']);
        });

        // Drop time permissions table
        Schema::dropIfExists(config('oo-role-permission.tables.time_permissions', 'oo_time_permissions'));
    }
};
