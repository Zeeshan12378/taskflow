<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropSqliteCycleScopeTriggers();

        Schema::table('cycles', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->change();
            $table->unique(['project_id', 'name']);
            $table->index(['project_id', 'start_date', 'end_date']);
        });

        $this->createSqliteCycleScopeTriggers();
    }

    public function down(): void
    {
        if (DB::table('cycles')->whereNotNull('project_id')->exists()) {
            throw new RuntimeException('Project sprints must be removed before this migration can be rolled back.');
        }

        $this->dropSqliteCycleScopeTriggers();

        Schema::table('cycles', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'name']);
            $table->dropIndex(['project_id', 'start_date', 'end_date']);
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('team_id')->nullable(false)->change();
        });

        $this->createSqliteCycleScopeTriggers();
    }

    private function dropSqliteCycleScopeTriggers(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        foreach ([
            'cycles_owner_insert_check',
            'cycles_owner_update_check',
            'cycles_active_team_insert_check',
            'cycles_active_team_update_check',
            'cycles_active_project_insert_check',
            'cycles_active_project_update_check',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function createSqliteCycleScopeTriggers(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_owner_insert_check
            BEFORE INSERT ON cycles
            FOR EACH ROW
            WHEN (NEW.team_id IS NULL) = (NEW.project_id IS NULL)
            BEGIN
                SELECT RAISE(ABORT, 'A sprint must belong to exactly one team or project');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_owner_update_check
            BEFORE UPDATE OF team_id, project_id ON cycles
            FOR EACH ROW
            WHEN (NEW.team_id IS NULL) = (NEW.project_id IS NULL)
            BEGIN
                SELECT RAISE(ABORT, 'A sprint must belong to exactly one team or project');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_active_team_insert_check
            BEFORE INSERT ON cycles
            FOR EACH ROW
            WHEN NEW.team_id IS NOT NULL AND NEW.status = 'active'
                AND EXISTS (SELECT 1 FROM cycles WHERE team_id = NEW.team_id AND status = 'active')
            BEGIN
                SELECT RAISE(ABORT, 'A team can only have one active sprint');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_active_team_update_check
            BEFORE UPDATE OF status, team_id ON cycles
            FOR EACH ROW
            WHEN NEW.team_id IS NOT NULL AND NEW.status = 'active'
                AND EXISTS (SELECT 1 FROM cycles WHERE team_id = NEW.team_id AND status = 'active' AND id <> NEW.id)
            BEGIN
                SELECT RAISE(ABORT, 'A team can only have one active sprint');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_active_project_insert_check
            BEFORE INSERT ON cycles
            FOR EACH ROW
            WHEN NEW.project_id IS NOT NULL AND NEW.status = 'active'
                AND EXISTS (SELECT 1 FROM cycles WHERE project_id = NEW.project_id AND status = 'active')
            BEGIN
                SELECT RAISE(ABORT, 'A project can only have one active sprint');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER IF NOT EXISTS cycles_active_project_update_check
            BEFORE UPDATE OF status, project_id ON cycles
            FOR EACH ROW
            WHEN NEW.project_id IS NOT NULL AND NEW.status = 'active'
                AND EXISTS (SELECT 1 FROM cycles WHERE project_id = NEW.project_id AND status = 'active' AND id <> NEW.id)
            BEGIN
                SELECT RAISE(ABORT, 'A project can only have one active sprint');
            END;
        SQL);
    }
};
