<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Cycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);
        $doneTask = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $doneStatus->id,
            'cycle_id' => $active->id,
            'title' => 'Completed sprint task',
            'priority' => 'medium',
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
            ->assertHasNoErrors();

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
