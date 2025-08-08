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
        Schema::create(config('oo-role-permission.tables.roles'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Ensure unique role names
            $table->string('readable_name')->nullable();
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->string('type')->nullable();
            $table->string('state')->nullable();
            $table->string('status')->default('active')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['name', 'status']);
            $table->index('status');
            $table->index('type');
        });

        Schema::create(config('oo-role-permission.tables.role_models'), function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(config('oo-role-permission.models.role'))
                ->constrained(config('oo-role-permission.tables.roles'))
                ->onDelete('cascade');
            $table->string('model_type');
            $table->string('model_id');
            $table->json('additional_permissions')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index(['model_type', 'model_id']);
            $table->index(['role_id', 'model_type']);

            // Prevent duplicate role assignments
            $table->unique(['role_id', 'model_type', 'model_id'], 'unique_role_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('oo-role-permission.tables.role_models'));
        Schema::dropIfExists(config('oo-role-permission.tables.roles'));
    }
};
