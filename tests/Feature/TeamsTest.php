<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_creation_adds_the_creator_as_owner(): void
    {
        $owner = User::factory()->create();

        Livewire::actingAs($owner)
            ->test('teams.index')
            ->set('name', 'Product team')
            ->set('description', 'The people building the product.')
            ->call('createTeam')
            ->assertHasNoErrors()
            ->assertRedirect();

        $team = Team::query()->where('name', 'Product team')->firstOrFail();

        $this->assertSame($owner->id, $team->owner_id);
        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_team_members_inherit_access_to_team_projects_and_tasks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->createTeam($owner);
        $project = $this->createProject($owner, $team);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'title' => 'Shared team task',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->set('memberEmail', $member->email)
            ->call('addMember')
            ->assertHasNoErrors()
            ->assertSee('was added to the team');

        $this->actingAs($member)
            ->get(route('projects.board', $project))
            ->assertOk()
            ->assertSee('Shared team task');

        $this->actingAs($member)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Shared team task');
    }

    public function test_removing_a_team_member_removes_inherited_project_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->createTeam($owner);
        $team->members()->attach($member->id, ['role' => 'member']);
        $project = $this->createProject($owner, $team);

        $this->actingAs($member)->get(route('projects.show', $project))->assertOk();

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->call('removeMember', $member->id)
            ->assertSee('was removed from the team');

        $this->actingAs($member)->get(route('projects.show', $project))->assertNotFound();
    }

    public function test_direct_project_member_can_only_access_the_shared_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $sharedProject = $this->createProject($owner, null, 'Shared project');
        $privateProject = $this->createProject($owner, null, 'Private project');

        Livewire::actingAs($owner)
            ->test('projects.form', ['project' => $sharedProject->id])
            ->set('memberEmail', $member->email)
            ->call('addMember')
            ->assertHasNoErrors();

        $this->actingAs($member)->get(route('projects.show', $sharedProject))->assertOk();
        $this->actingAs($member)->get(route('projects.show', $privateProject))->assertNotFound();
    }

    public function test_team_overview_counts_open_overdue_unassigned_and_member_workload(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->createTeam($owner);
        $team->members()->attach($member->id, ['role' => 'member']);
        $project = $this->createProject($owner, $team);
        $todo = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $complete = $project->statuses()->where('is_complete', true)->firstOrFail();

        $project->tasks()->create([
            'user_id' => $owner->id,
            'assignee_id' => $member->id,
            'project_status_id' => $todo->id,
            'title' => 'Overdue assigned work',
            'priority' => 'medium',
            'due_date' => today()->subDay(),
        ]);
        $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $todo->id,
            'title' => 'Unassigned work',
            'priority' => 'medium',
        ]);
        $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $complete->id,
            'title' => 'Completed work',
            'priority' => 'medium',
            'due_date' => today()->subDays(2),
        ]);

        Livewire::actingAs($owner)
            ->test('teams.show', ['team' => $team->id])
            ->assertSee('Team workload overview')
            ->assertSee('Open tasks')
            ->assertSee('Overdue')
            ->assertSee('Unassigned open tasks')
            ->assertSee('1 open · 1 overdue');
    }

    private function createTeam(User $owner): Team
    {
        $team = Team::query()->create(['owner_id' => $owner->id, 'name' => 'Test team']);
        $team->members()->attach($owner->id, ['role' => 'owner']);

        return $team;
    }

    private function createProject(User $owner, ?Team $team, string $name = 'Team project'): Project
    {
        $project = $owner->projects()->create(['name' => $name, 'team_id' => $team?->id]);
        $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);

        return $project;
    }
}
