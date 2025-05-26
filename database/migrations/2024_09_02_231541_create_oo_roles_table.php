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
            $table->id()->unique();
            $table->string('name');
            $table->string('readable_name')->nullable();
            $table->text('description')->nullable();
            $table->json('permissions');
            $table->string('type')->nullable();
            $table->string('state')->nullable();
            $table->string('status')->default('active')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('oo-role-permission.tables.roles'));
        Schema::dropIfExists(config('oo-role-permission.tables.role_models'));
    }
};
