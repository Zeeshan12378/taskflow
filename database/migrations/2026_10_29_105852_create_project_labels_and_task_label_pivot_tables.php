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
        Schema::create('project_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('color', 20)->default('slate');
            $table->timestamps();

            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'color']);
        });

        Schema::create('project_label_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'project_label_id']);
            $table->index(['project_label_id', 'task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_label_task');
        Schema::dropIfExists('project_labels');
    }
};
