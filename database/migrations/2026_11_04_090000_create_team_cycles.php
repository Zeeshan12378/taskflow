<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'start_date', 'end_date']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('cycle_id')->nullable()->after('reviewer_id')->constrained()->nullOnDelete();
            $table->index(['project_id', 'cycle_id']);
        });

        $this->restoreSqliteTaskTypeConstraint();
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'cycle_id']);
            $table->dropConstrainedForeignId('cycle_id');
        });

        Schema::dropIfExists('cycles');
        $this->restoreSqliteTaskTypeConstraint();
    }

    private function restoreSqliteTaskTypeConstraint(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS tasks_type_insert_check
            BEFORE INSERT ON tasks
            FOR EACH ROW
            WHEN NEW.type IS NULL OR NEW.type NOT IN ('task', 'bug', 'feature')
            BEGIN
                SELECT RAISE(ABORT, 'Invalid task type');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS tasks_type_update_check
            BEFORE UPDATE OF type ON tasks
            FOR EACH ROW
            WHEN NEW.type IS NULL OR NEW.type NOT IN ('task', 'bug', 'feature')
            BEGIN
                SELECT RAISE(ABORT, 'Invalid task type');
            END;
        SQL);
    }
};
