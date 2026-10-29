<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cycles', function (Blueprint $table): void {
            $table->text('goal')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('story_points')->nullable();
        });

        DB::table('cycles')->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->update(['status' => 'active', 'started_at' => now()]);

        DB::table('cycles')->whereDate('end_date', '<', today())
            ->update(['status' => 'completed', 'completed_at' => now()]);

        $this->createSqliteConstraints();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach ([
                'cycles_status_insert_check', 'cycles_status_update_check',
                'cycles_active_team_insert_check', 'cycles_active_team_update_check',
                'tasks_story_points_insert_check', 'tasks_story_points_update_check',
            ] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('story_points');
        });

        Schema::table('cycles', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['goal', 'status', 'started_at', 'completed_at']);
        });
    }

    private function createSqliteConstraints(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_status_insert_check
            BEFORE INSERT ON cycles
            FOR EACH ROW
            WHEN NEW.status NOT IN ('planned', 'active', 'completed')
            BEGIN
                SELECT RAISE(ABORT, 'Invalid sprint status');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_status_update_check
            BEFORE UPDATE OF status ON cycles
            FOR EACH ROW
            WHEN NEW.status NOT IN ('planned', 'active', 'completed')
            BEGIN
                SELECT RAISE(ABORT, 'Invalid sprint status');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_active_team_insert_check
            BEFORE INSERT ON cycles
            FOR EACH ROW
            WHEN NEW.status = 'active' AND EXISTS (SELECT 1 FROM cycles WHERE team_id = NEW.team_id AND status = 'active')
            BEGIN
                SELECT RAISE(ABORT, 'A team can only have one active sprint');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_active_team_update_check
            BEFORE UPDATE OF status, team_id ON cycles
            FOR EACH ROW
            WHEN NEW.status = 'active' AND EXISTS (SELECT 1 FROM cycles WHERE team_id = NEW.team_id AND status = 'active' AND id <> NEW.id)
            BEGIN
                SELECT RAISE(ABORT, 'A team can only have one active sprint');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS tasks_story_points_insert_check
            BEFORE INSERT ON tasks
            FOR EACH ROW
            WHEN NEW.story_points IS NOT NULL AND NEW.story_points NOT IN (1, 2, 3, 5, 8, 13, 21)
            BEGIN
                SELECT RAISE(ABORT, 'Invalid story point estimate');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS tasks_story_points_update_check
            BEFORE UPDATE OF story_points ON tasks
            FOR EACH ROW
            WHEN NEW.story_points IS NOT NULL AND NEW.story_points NOT IN (1, 2, 3, 5, 8, 13, 21)
            BEGIN
                SELECT RAISE(ABORT, 'Invalid story point estimate');
            END;
        SQL);
    }
};
