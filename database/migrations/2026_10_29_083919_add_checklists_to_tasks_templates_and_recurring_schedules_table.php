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
        Schema::table('tasks_templates_and_recurring_schedules', function (Blueprint $table) {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->json('checklist')->nullable();
            });

            Schema::table('task_templates', function (Blueprint $table): void {
                $table->json('checklist')->nullable();
            });

            Schema::table('recurring_task_schedules', function (Blueprint $table): void {
                $table->json('checklist')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks_templates_and_recurring_schedules', function (Blueprint $table) {
            Schema::table('recurring_task_schedules', function (Blueprint $table): void {
                $table->dropColumn('checklist');
            });

            Schema::table('task_templates', function (Blueprint $table): void {
                $table->dropColumn('checklist');
            });

            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropColumn('checklist');
            });
        });
    }
};
