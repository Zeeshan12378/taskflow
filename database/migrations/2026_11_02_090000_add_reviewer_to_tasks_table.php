<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('reviewer_id')->nullable()->after('assignee_id')->constrained('users')->nullOnDelete();
            $table->index(['project_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'reviewer_id']);
            $table->dropConstrainedForeignId('reviewer_id');
        });
    }
};
