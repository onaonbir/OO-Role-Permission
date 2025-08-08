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
        // Drop existing time_permissions table and recreate with polymorphic structure
        Schema::dropIfExists(config('oo-role-permission.tables.time_permissions', 'oo_time_permissions'));

        Schema::create(config('oo-role-permission.tables.time_permissions', 'oo_time_permissions'), function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship - can be applied to Role, User, or any model
            $table->string('constraintable_type'); // Role::class, User::class, etc.
            $table->unsignedBigInteger('constraintable_id'); // role_id, user_id, etc.

            // Permissions this constraint applies to
            $table->json('additional_permissions')->nullable(); // ['admin.*', 'user.delete'] or null for all

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
            $table->index(['constraintable_type', 'constraintable_id'], 'idx_constraintable');
            $table->index(['is_active', 'start_date', 'end_date'], 'idx_active_dates');
            $table->index(['start_time', 'end_time'], 'idx_daily_times');
            $table->index(['timezone'], 'idx_timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('oo-role-permission.tables.time_permissions', 'oo_time_permissions'));
    }
};
