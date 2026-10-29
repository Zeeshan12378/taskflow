<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS tasks_type_insert_check');
        DB::unprepared('DROP TRIGGER IF EXISTS tasks_type_update_check');
    }
};
