<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\CycleSnapshot;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class CyclesTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_manager_can_create_a_dated_cycle(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Platform team');
        $start = today()->addDay();
        $end = today()->addDays(14);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->set('cycleName', 'Cycle 1')
            ->set('cycleStartDate', $start->format('Y-m-d'))
            ->set('cycleEndDate', $end->format('Y-m-d'))
            ->call('createCycle')
            ->assertHasNoErrors()
            ->assertSee('Cycle 1')
            ->assertSee('upcoming');

        $this->assertDatabaseHas('cycles', [
            'team_id' => $team->id,
            'name' => 'Cycle 1',
        ]);
        $cycle = Cycle::query()->where('team_id', $team->id)->where('name', 'Cycle 1')->firstOrFail();
        $this->assertSame($start->format('Y-m-d'), $cycle->start_date->format('Y-m-d'));
        $this->assertSame($end->format('Y-m-d'), $cycle->end_date->format('Y-m-d'));
    }

    public function test_cycles_reject_overlapping_dates_and_non_manager_creation(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->createTeam($owner, 'Design team');
        $team->members()->attach($member->id, ['role' => 'member']);

        $component = Livewire::actingAs($owner)->test('teams.show', ['team' => $team->id]);
        $component->set('cycleName', 'Cycle 1')
            ->set('cycleStartDate', today()->addDays(2)->format('Y-m-d'))
            ->set('cycleEndDate', today()->addDays(10)->format('Y-m-d'))
            ->call('createCycle')
            ->assertHasNoErrors();

        $component->set('cycleName', 'Cycle 2')
            ->set('cycleStartDate', today()->addDays(8)->format('Y-m-d'))
            ->set('cycleEndDate', today()->addDays(15)->format('Y-m-d'))
            ->call('createCycle')
            ->assertHasErrors('cycleStartDate')
            ->assertSee('Cycle dates cannot overlap another cycle for this team.');

        Livewire::actingAs($member)
            ->test('teams.show', ['team' => $team->id])
            ->set('cycleName', 'Unauthorized cycle')
            ->set('cycleStartDate', today()->addDays(20)->format('Y-m-d'))
            ->set('cycleEndDate', today()->addDays(27)->format('Y-m-d'))
            ->call('createCycle')
            ->assertForbidden();
    }

    public function test_project_manager_can_plan_start_and_complete_independent_project_sprints_with_velocity_history(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Project sprint team');
        $project = $this->createProject($owner, $team);
        $teamCycle = $this->createCycle($team, 'Team-owned sprint');
        $teamCycle->update(['status' => 'active']);
        $start = today()->addDay();
        $end = today()->addDays(14);

        $component = Livewire::actingAs($owner)->test('projects.show', ['project' => $project->id]);
        $component->set('cycleName', 'Project Sprint 1')
            ->set('cycleGoal', 'Deliver project work')
            ->set('cycleStartDate', $start->format('Y-m-d'))
            ->set('cycleEndDate', $end->format('Y-m-d'))
            ->call('createCycle')
            ->assertHasNoErrors()
            ->assertSee('Project Sprint 1');

        $cycle = $project->cycles()->where('name', 'Project Sprint 1')->firstOrFail();
        $this->assertSame($project->id, $cycle->project_id);
        $this->assertNull($cycle->team_id);

        $component->set('cycleName', 'Overlapping project sprint')
            ->set('cycleStartDate', today()->addDays(10)->format('Y-m-d'))
            ->set('cycleEndDate', today()->addDays(20)->format('Y-m-d'))
            ->call('createCycle')
            ->assertHasErrors('cycleStartDate')
            ->assertSee('Sprint dates cannot overlap another sprint for this project.');

        $next = $project->cycles()->create([
            'name' => 'Project Sprint 2',
            'start_date' => today()->addDays(15),
            'end_date' => today()->addDays(28),
        ]);
        $todo = $project->statuses()->where('is_complete', false)->firstOrFail();
        $done = $project->statuses()->where('is_complete', true)->firstOrFail();
        $openTask = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $todo->id,
            'cycle_id' => $cycle->id,
            'title' => 'Open project sprint task',
            'priority' => 'medium',
            'story_points' => 3,
        ]);
        $completedTask = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $done->id,
            'cycle_id' => $cycle->id,
            'title' => 'Completed project sprint task',
            'priority' => 'medium',
            'story_points' => 5,
        ]);

        $component->set("cycleGoals.{$cycle->id}", 'Deliver project work')
            ->call('startCycle', $cycle->id)
            ->assertHasNoErrors()
            ->assertSee('Project Sprint 1 started.');

        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'status' => 'active', 'project_id' => $project->id, 'team_id' => null]);
        $this->assertDatabaseHas('cycles', ['id' => $teamCycle->id, 'status' => 'active']);
        $this->assertDatabaseHas('cycle_snapshots', ['cycle_id' => $cycle->id, 'snapshot_type' => 'start']);

        $component->set("cycleGoals.{$next->id}", 'Continue project work')
            ->call('startCycle', $next->id)
            ->assertSee('Complete the active project sprint before starting another one.');
        $this->assertDatabaseHas('cycles', ['id' => $next->id, 'status' => 'planned']);

        $component->set("rolloverTargets.{$cycle->id}", (string) $next->id)
            ->call('completeCycle', $cycle->id)
            ->assertHasNoErrors()
            ->assertSee('1 open task moved to the selected sprint.')
            ->assertSee('average delivered points');

        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['id' => $openTask->id, 'cycle_id' => $next->id]);
        $this->assertDatabaseHas('tasks', ['id' => $completedTask->id, 'cycle_id' => $cycle->id]);
        $this->assertDatabaseHas('cycle_snapshots', [
            'cycle_id' => $cycle->id,
            'snapshot_type' => 'completion',
            'total_tasks' => 2,
            'completed_tasks' => 1,
            'total_story_points' => 8,
            'completed_story_points' => 5,
        ]);
    }

    public function test_project_members_can_view_project_sprints_but_only_managers_can_plan_them(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->createTeam($owner, 'Project sprint access team');
        $project = $this->createProject($owner, $team);
        $project->members()->attach($member->id, ['role' => 'member']);
        $projectCycle = $project->cycles()->create([
            'name' => 'Visible project sprint',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(14),
        ]);
        $teamCycle = $this->createCycle($team, 'Hidden team sprint');
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $project->statuses()->where('is_complete', false)->value('id'),
            'title' => 'Project sprint assignment',
            'priority' => 'medium',
        ]);
        $taskWithHiddenTeamSprint = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $project->statuses()->where('is_complete', false)->value('id'),
            'cycle_id' => $teamCycle->id,
            'title' => 'Task with hidden team sprint',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($member)
            ->test('projects.board', ['project' => $project->id])
            ->set('filtersOpen', true)
            ->assertSee('Visible project sprint')
            ->assertDontSee('Hidden team sprint');
        Livewire::actingAs($member)
            ->test('projects.board', ['project' => $project->id])
            ->assertDontSee('Hidden team sprint');

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->assertSee('Visible project sprint')
            ->assertDontSee('Hidden team sprint');
        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id, 'task' => $taskWithHiddenTeamSprint->id])
            ->assertDontSee('Hidden team sprint');

        $taskForm = Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('cycleId', (string) $projectCycle->id)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'cycle_id' => $projectCycle->id]);

        $taskForm->set('cycleId', (string) $teamCycle->id)
            ->call('save')
            ->assertHasErrors('cycleId');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'cycle_id' => $projectCycle->id]);

        Livewire::actingAs($member)
            ->test('projects.show', ['project' => $project->id])
            ->set('cycleName', 'Unauthorized project sprint')
            ->set('cycleStartDate', today()->addDays(20)->format('Y-m-d'))
            ->set('cycleEndDate', today()->addDays(27)->format('Y-m-d'))
            ->call('createCycle')
            ->assertForbidden();

        $this->assertDatabaseHas('cycles', ['id' => $projectCycle->id, 'project_id' => $project->id, 'team_id' => null]);
        $this->assertDatabaseHas('cycles', ['id' => $teamCycle->id, 'team_id' => $team->id, 'project_id' => null]);
    }

    public function test_sprint_tasks_view_groups_project_tasks_by_accessible_sprint(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Sprint task view team');
        $project = $this->createProject($owner, $team);
        $projectCycle = $project->cycles()->create([
            'name' => 'Project sprint group',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(14),
        ]);
        $teamCycle = $this->createCycle($team, 'Team sprint group');
        $openStatus = $project->statuses()->where('is_complete', false)->firstOrFail();
        $project->tasks()->createMany([
            [
                'user_id' => $owner->id,
                'project_status_id' => $openStatus->id,
                'cycle_id' => $projectCycle->id,
                'title' => 'Project sprint task',
                'priority' => 'high',
            ],
            [
                'user_id' => $owner->id,
                'project_status_id' => $openStatus->id,
                'cycle_id' => $teamCycle->id,
                'title' => 'Team sprint task',
                'priority' => 'medium',
            ],
            [
                'user_id' => $owner->id,
                'project_status_id' => $openStatus->id,
                'title' => 'Backlog task',
                'priority' => 'low',
            ],
        ]);

        Livewire::actingAs($owner)
            ->test('projects.sprints', ['project' => $project->id])
            ->assertSee('<details', false)
            ->assertSee('<summary', false)
            ->assertSee('Project sprint group')
            ->assertSee('Team sprint group')
            ->assertSee('Project sprint task')
            ->assertSee('Team sprint task')
            ->assertSee('Backlog task')
            ->assertSee('1 work item');
    }

    public function test_sprint_tasks_view_hides_team_sprint_details_from_direct_project_members(): void
    {
        $owner = User::factory()->create();
        $directMember = User::factory()->create();
        $team = $this->createTeam($owner, 'Private sprint details team');
        $project = $this->createProject($owner, $team);
        $project->members()->attach($directMember->id, ['role' => 'member']);
        $teamCycle = $this->createCycle($team, 'Private team sprint name');
        $status = $project->statuses()->where('is_complete', false)->firstOrFail();
        $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'cycle_id' => $teamCycle->id,
            'title' => 'Visible project task with restricted sprint',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($directMember)
            ->test('projects.sprints', ['project' => $project->id])
            ->assertSee('Visible project task with restricted sprint')
            ->assertSee('Backlog / other tasks')
            ->assertDontSee('Private team sprint name');
    }

    public function test_team_sprint_velocity_reports_the_average_from_completed_sprint_snapshots(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Velocity team');

        foreach ([8, 13] as $index => $points) {
            $cycle = $team->cycles()->create([
                'name' => 'Completed sprint '.($index + 1),
                'start_date' => today()->subDays(30 - ($index * 15)),
                'end_date' => today()->subDays(16 - ($index * 15)),
                'status' => 'completed',
            ]);
            $cycle->snapshots()->create([
                'snapshot_date' => $cycle->end_date,
                'snapshot_type' => 'completion',
                'total_tasks' => 2,
                'completed_tasks' => 2,
                'total_story_points' => $points,
                'completed_story_points' => $points,
            ]);
        }

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->assertSee('10.5')
            ->assertSee('Completed sprint 1')
            ->assertSee('8 pts')
            ->assertSee('13 pts');
    }

    public function test_team_manager_can_start_a_planned_sprint_with_a_goal_and_only_one_sprint_can_be_active(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Product team');
        $first = $this->createCycle($team, 'Sprint 1');
        $second = $team->cycles()->create([
            'name' => 'Sprint 2',
            'start_date' => today()->addDays(15),
            'end_date' => today()->addDays(28),
        ]);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->set("cycleGoals.{$first->id}", 'Deliver the onboarding flow')
            ->call('startCycle', $first->id)
            ->assertHasNoErrors()
            ->assertSee('Sprint 1 started.');

        $this->assertDatabaseHas('cycles', [
            'id' => $first->id,
            'status' => 'active',
            'goal' => 'Deliver the onboarding flow',
        ]);
        $this->assertNotNull($first->fresh()->started_at);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->set("cycleGoals.{$second->id}", 'Ship the reporting page')
            ->call('startCycle', $second->id)
            ->assertSee('Complete the active sprint before starting another one.');

        $this->assertDatabaseHas('cycles', ['id' => $second->id, 'status' => 'planned']);
    }

    public function test_starting_a_sprint_records_its_baseline_scope(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Baseline team');
        $cycle = $this->createCycle($team, 'Sprint baseline');
        $project = $this->createProject($owner, $team);
        $todo = $project->statuses()->where('is_complete', false)->firstOrFail();
        $complete = $project->statuses()->where('is_complete', true)->firstOrFail();
        $cycle->tasks()->createMany([
            ['project_id' => $project->id, 'user_id' => $owner->id, 'project_status_id' => $todo->id, 'title' => 'Planned work', 'priority' => 'medium', 'story_points' => 5],
            ['project_id' => $project->id, 'user_id' => $owner->id, 'project_status_id' => $complete->id, 'title' => 'Already complete', 'priority' => 'medium', 'story_points' => 3],
        ]);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->set("cycleGoals.{$cycle->id}", 'Deliver the goal')
            ->call('startCycle', $cycle->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cycle_snapshots', [
            'cycle_id' => $cycle->id,
            'snapshot_date' => today()->toDateString(),
            'snapshot_type' => 'start',
            'total_tasks' => 2,
            'completed_tasks' => 1,
            'total_story_points' => 8,
            'completed_story_points' => 3,
        ]);
    }

    public function test_daily_snapshot_command_updates_one_snapshot_per_active_sprint_and_date(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Snapshot team');
        $active = $this->createCycle($team, 'Active snapshot sprint');
        $active->update(['status' => 'active']);
        $planned = $this->createCycle($team, 'Planned sprint');
        $project = $this->createProject($owner, $team);
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $project->statuses()->where('is_complete', false)->value('id'),
            'cycle_id' => $active->id,
            'title' => 'Active sprint task',
            'priority' => 'medium',
            'story_points' => 5,
        ]);

        $this->assertSame(0, Artisan::call('cycles:snapshot-active'));
        $task->update(['project_status_id' => $project->statuses()->where('is_complete', true)->value('id')]);
        $this->assertSame(0, Artisan::call('cycles:snapshot-active'));

        $this->assertSame(1, CycleSnapshot::query()->where('cycle_id', $active->id)->count());
        $this->assertDatabaseHas('cycle_snapshots', [
            'cycle_id' => $active->id,
            'snapshot_date' => today()->toDateString(),
            'snapshot_type' => 'daily',
            'total_tasks' => 1,
            'completed_tasks' => 0,
            'total_story_points' => 5,
            'completed_story_points' => 0,
        ]);
        $this->assertDatabaseMissing('cycle_snapshots', ['cycle_id' => $planned->id]);
    }

    public function test_completing_a_sprint_rolls_open_tasks_to_selected_cycle_and_keeps_completed_work(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Release team');
        $active = $this->createCycle($team, 'Sprint 1');
        $active->update(['status' => 'active', 'goal' => 'Ship search']);
        $next = $team->cycles()->create([
            'name' => 'Sprint 2',
            'start_date' => today()->addDays(15),
            'end_date' => today()->addDays(28),
        ]);
        $project = $this->createProject($owner, $team);
        $openStatus = $project->statuses()->where('is_complete', false)->firstOrFail();
        $doneStatus = $project->statuses()->where('is_complete', true)->firstOrFail();
        $openTask = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $openStatus->id,
            'cycle_id' => $active->id,
            'title' => 'Open carry-over task',
            'priority' => 'medium',
            'story_points' => 3,
        ]);
        $doneTask = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $doneStatus->id,
            'cycle_id' => $active->id,
            'title' => 'Completed sprint task',
            'priority' => 'medium',
            'story_points' => 5,
        ]);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->set("rolloverTargets.{$active->id}", (string) $next->id)
            ->call('completeCycle', $active->id)
            ->assertHasNoErrors()
            ->assertSee('1 open task moved to the selected sprint.');

        $this->assertDatabaseHas('cycles', ['id' => $active->id, 'status' => 'completed']);
        $this->assertNotNull($active->fresh()->completed_at);
        $this->assertDatabaseHas('tasks', ['id' => $openTask->id, 'cycle_id' => $next->id]);
        $this->assertDatabaseHas('tasks', ['id' => $doneTask->id, 'cycle_id' => $active->id]);
        $this->assertDatabaseHas('cycle_snapshots', [
            'cycle_id' => $active->id,
            'snapshot_date' => today()->toDateString(),
            'snapshot_type' => 'completion',
            'total_tasks' => 2,
            'completed_tasks' => 1,
            'total_story_points' => 8,
            'completed_story_points' => 5,
        ]);
    }

    public function test_completed_sprint_shows_its_historical_report_and_scope_change(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Reporting team');
        $cycle = $this->createCycle($team, 'Reported sprint');
        $cycle->update(['status' => 'completed']);
        $cycle->snapshots()->create([
            'snapshot_date' => today()->subDays(2),
            'snapshot_type' => 'start',
            'total_tasks' => 2,
            'completed_tasks' => 0,
            'total_story_points' => 8,
            'completed_story_points' => 0,
        ]);
        $cycle->snapshots()->create([
            'snapshot_date' => today()->subDay(),
            'snapshot_type' => 'daily',
            'total_tasks' => 3,
            'completed_tasks' => 1,
            'total_story_points' => 13,
            'completed_story_points' => 5,
        ]);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->assertSee('Sprint report · 5 points delivered')
            ->assertSee('+5')
            ->assertSee('Remaining');
    }

    public function test_only_team_managers_can_start_or_complete_a_sprint(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->createTeam($owner, 'Secure sprint team');
        $team->members()->attach($member->id, ['role' => 'member']);
        $cycle = $this->createCycle($team, 'Sprint 1');

        Livewire::actingAs($member)
            ->test('teams.show', ['team' => $team->id])
            ->call('startCycle', $cycle->id)
            ->assertForbidden();

        $cycle->update(['status' => 'active', 'goal' => 'Complete sprint work']);
        Livewire::actingAs($member)
            ->test('teams.show', ['team' => $team->id])
            ->call('completeCycle', $cycle->id)
            ->assertForbidden();

        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'status' => 'active']);
    }

    public function test_task_can_only_join_a_current_or_upcoming_cycle_for_its_project_team(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Engineering');
        $otherTeam = $this->createTeam($owner, 'Operations');
        $project = $this->createProject($owner, $team);
        $taskStatus = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $cycle = $this->createCycle($team, 'Engineering 1');
        $otherCycle = $this->createCycle($otherTeam, 'Operations 1');
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $taskStatus->id,
            'title' => 'Build deployment flow',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($owner)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('cycleId', (string) $cycle->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'cycle_id' => $cycle->id]);

        Livewire::actingAs($owner)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('cycleId', (string) $otherCycle->id)
            ->call('save')
            ->assertHasErrors('cycleId');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'cycle_id' => $cycle->id]);
    }

    public function test_direct_project_member_cannot_assign_or_read_team_cycles(): void
    {
        $owner = User::factory()->create();
        $directMember = User::factory()->create();
        $team = $this->createTeam($owner, 'Core team');
        $project = $this->createProject($owner, $team);
        $project->members()->attach($directMember->id, ['role' => 'member']);
        $cycle = $this->createCycle($team, 'Core cycle');
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'title' => 'Directly shared task',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($directMember)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->assertDontSee('Core cycle')
            ->set('cycleId', (string) $cycle->id)
            ->call('save')
            ->assertHasErrors('cycleId');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'cycle_id' => null]);
    }

    public function test_project_cannot_change_teams_while_tasks_still_belong_to_its_cycles(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Current team');
        $newTeam = $this->createTeam($owner, 'Next team');
        $project = $this->createProject($owner, $team);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $cycle = $this->createCycle($team, 'Current cycle');
        $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'cycle_id' => $cycle->id,
            'title' => 'Cycle-linked work',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($owner)
            ->test('projects.form', ['project' => $project->id])
            ->set('teamId', (string) $newTeam->id)
            ->call('save')
            ->assertHasErrors('teamId');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'team_id' => $team->id]);
    }

    public function test_project_can_change_teams_when_tasks_only_use_project_sprints(): void
    {
        $owner = User::factory()->create();
        $team = $this->createTeam($owner, 'Project sprint current team');
        $newTeam = $this->createTeam($owner, 'Project sprint next team');
        $project = $this->createProject($owner, $team);
        $cycle = $project->cycles()->create([
            'name' => 'Project-owned cycle',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(14),
        ]);
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $project->statuses()->where('name', 'To Do')->value('id'),
            'cycle_id' => $cycle->id,
            'title' => 'Project-owned sprint task',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($owner)
            ->test('projects.form', ['project' => $project->id])
            ->set('teamId', (string) $newTeam->id)
            ->call('saveField', 'teamId')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'team_id' => $newTeam->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'cycle_id' => $cycle->id]);
        $this->assertDatabaseHas('cycles', ['id' => $cycle->id, 'project_id' => $project->id, 'team_id' => null]);
    }

    private function createTeam(User $owner, string $name): Team
    {
        $team = Team::query()->create(['owner_id' => $owner->id, 'name' => $name]);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        return $team;
    }

    private function createProject(User $owner, Team $team): Project
    {
        $project = $owner->projects()->create(['name' => 'Team cycle project', 'team_id' => $team->id]);
        $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);

        return $project;
    }

    private function createCycle(Team $team, string $name)
    {
        return $team->cycles()->create([
            'name' => $name,
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(14),
        ]);
    }
}
