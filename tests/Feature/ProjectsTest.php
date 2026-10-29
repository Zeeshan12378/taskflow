<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_projects(): void
    {
        $this->get(route('projects.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_sees_only_their_projects(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $user->projects()->create(['name' => 'My Alpha project']);
        $otherUser->projects()->create(['name' => 'Private Beta project']);

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('My Alpha project')
            ->assertDontSee('Private Beta project');
    }

    public function test_project_workspace_tabs_render_and_list_and_calendar_are_project_scoped(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Workspace project']);
        $status = $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES)->first();
        $project->tasks()->create([
            'user_id' => $user->id,
            'project_status_id' => $status->id,
            'title' => 'Due this month',
            'priority' => 'high',
            'due_date' => today()->format('Y-m-d'),
        ]);
        $otherProject = $otherUser->projects()->create(['name' => 'Private workspace']);

        foreach (['projects.show', 'projects.board', 'projects.list', 'projects.calendar', 'projects.reports', 'projects.edit'] as $view) {
            $response = $this->actingAs($user)->get(route($view, $project));
            $response->assertOk()->assertSee('Overview')->assertSee('Board')->assertSee('List')->assertSee('Calendar')->assertSee('Reports')->assertSee('Settings');
            $response->assertDontSee('Private workspace', false);
        }

        $this->get(route('projects.list', $project))->assertSee('Due this month');
        $this->get(route('projects.calendar', $project))->assertSee('Due this month');

        $this->get(route('projects.list', $otherProject))->assertNotFound();
        $this->get(route('projects.calendar', $otherProject))->assertNotFound();
    }

    public function test_overview_shows_project_summary_and_board_stays_a_kanban_view(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Health dashboard']);
        $statuses = $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);
        $open = $statuses->firstWhere('is_complete', false);
        $done = $statuses->firstWhere('is_complete', true);

        $overdueTask = $project->tasks()->create([
            'user_id' => $user->id,
            'project_status_id' => $open->id,
            'title' => 'Overdue implementation',
            'priority' => 'urgent',
            'due_date' => today()->subDay(),
            'story_points' => 3,
        ]);
        $project->tasks()->create([
            'user_id' => $user->id,
            'project_status_id' => $done->id,
            'title' => 'Completed implementation',
            'priority' => 'low',
            'story_points' => 2,
        ]);
        $project->tasks()->create([
            'user_id' => $user->id,
            'assignee_id' => $user->id,
            'project_status_id' => $open->id,
            'title' => 'Due soon implementation',
            'priority' => 'high',
            'type' => 'bug',
            'due_date' => today()->addDays(3),
            'story_points' => 5,
        ]);

        $this->actingAs($user);
        $overdueTask->update(['project_status_id' => $done->id]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Project summary')
            ->assertSee('total tasks')
            ->assertSee('1 Completed')
            ->assertSee('1 Updated')
            ->assertSee('3 Created')
            ->assertSee('1 Due soon')
            ->assertSee('Status overview')
            ->assertSee('Recent activity')
            ->assertSee('Priority breakdown')
            ->assertSee('Types of work')
            ->assertSee('Team workload')
            ->assertSee($user->name)
            ->assertSee('Story point progress')
            ->assertSee('50%')
            ->assertSee('Overdue implementation')
            ->assertDontSee('Danger zone')
            ->assertDontSee('Project access');

        $this->get(route('projects.board', $project))
            ->assertOk()
            ->assertSee('Task board columns')
            ->assertDontSee('Project summary')
            ->assertDontSee('Status overview');

        $this->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('Project access')
            ->assertSee('Workflow order and completion')
            ->assertSee('Danger zone');
    }

    public function test_workflow_configuration_is_updated_from_project_settings(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Workflow project']);
        $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);

        Livewire::actingAs($user)
            ->test('projects.form', ['project' => $project->id])
            ->set('statusName', 'Code Review')
            ->set('statusColor', 'violet')
            ->call('saveStatus')
            ->assertHasNoErrors()
            ->assertSee('Workflow column added.');

        $this->assertDatabaseHas('project_statuses', [
            'project_id' => $project->id,
            'name' => 'Code Review',
            'color' => 'violet',
        ]);
    }

    public function test_project_profile_fields_can_be_edited_and_cancelled_individually(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Original name', 'description' => 'Original description']);

        Livewire::actingAs($user)
            ->test('projects.form', ['project' => $project->id])
            ->call('startEditingField', 'name')
            ->set('name', '')
            ->call('saveField', 'name')
            ->assertHasErrors(['name' => 'required'])
            ->call('cancelEditingField', 'name')
            ->assertSet('name', 'Original name')
            ->call('startEditingField', 'description')
            ->set('description', 'Updated description only')
            ->call('saveField', 'description')
            ->assertHasNoErrors()
            ->assertSet('editingField', null);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Original name',
            'description' => 'Updated description only',
        ]);
    }

    public function test_project_creation_also_creates_default_workflow_statuses(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('projects.form')
            ->set('name', 'Website refresh')
            ->set('description', 'Refresh the product website.')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $project = $user->projects()->where('name', 'Website refresh')->firstOrFail();

        $this->assertSame(
            array_column(Project::DEFAULT_WORKFLOW_STATUSES, 'name'),
            $project->statuses()->orderBy('position')->pluck('name')->all(),
        );
        $this->assertGreaterThan(0, $project->statuses()->where('is_complete', true)->count());
        $this->assertDatabaseMissing('project_statuses', [
            'project_id' => $project->id,
            'name' => 'Overdue',
        ]);
        $this->assertDatabaseHas('project_statuses', [
            'project_id' => $project->id,
            'name' => 'Blocked',
            'is_complete' => false,
        ]);
    }

    public function test_project_form_validates_required_name_and_status(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test('projects.form')
            ->set('name', '')
            ->set('status', 'paused')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'status' => 'in']);
    }

    public function test_owner_can_update_a_project(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Old name']);

        Livewire::actingAs($user)
            ->test('projects.form', ['project' => $project->id])
            ->set('name', 'New name')
            ->set('description', 'Updated description')
            ->set('status', 'archived')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'user_id' => $user->id,
            'name' => 'New name',
            'description' => 'Updated description',
            'status' => 'archived',
        ]);
    }

    public function test_users_cannot_open_another_users_project(): void
    {
        $otherProject = User::factory()->create()->projects()->create(['name' => 'Private project']);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.show', $otherProject))
            ->assertNotFound();
    }

    public function test_users_cannot_edit_another_users_project(): void
    {
        $otherProject = User::factory()->create()->projects()->create(['name' => 'Private project']);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.edit', $otherProject))
            ->assertNotFound();
    }

    public function test_user_can_delete_an_owned_project_but_not_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = $owner->projects()->create(['name' => 'Remove me']);

        Livewire::actingAs($owner)
            ->test('projects.index')
            ->call('deleteProject', $project->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);

        $otherProject = User::factory()->create()->projects()->create(['name' => 'Keep me']);

        Livewire::actingAs($owner)
            ->test('projects.index')
            ->call('deleteProject', $otherProject->id)
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $otherProject->id]);
    }

    public function test_deleting_the_only_project_on_a_later_page_returns_to_a_populated_page(): void
    {
        $user = User::factory()->create();
        $projects = collect(range(1, 10))->map(function (int $number) use ($user) {
            $project = $user->projects()->create(['name' => "Project {$number}"]);
            $project->forceFill(['updated_at' => now()->addSeconds($number)])->save();

            return $project;
        });
        $oldestProject = $projects->first();

        Livewire::actingAs($user)
            ->test('projects.index')
            ->call('setPage', 2)
            ->assertSee($oldestProject->name)
            ->call('deleteProject', $oldestProject->id)
            ->assertSee('Project 10')
            ->assertDontSee('No projects yet');
    }

    public function test_project_search_matches_only_the_signed_in_users_projects(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->projects()->create(['name' => 'Launch plan']);
        $otherUser->projects()->create(['name' => 'Launch plan private']);

        Livewire::actingAs($user)
            ->test('projects.index')
            ->set('search', 'Launch')
            ->assertSee('Launch plan')
            ->assertDontSee('Launch plan private');
    }

    public function test_project_status_filter_limits_the_list(): void
    {
        $user = User::factory()->create();
        $user->projects()->create(['name' => 'Active project', 'status' => 'active']);
        $user->projects()->create(['name' => 'Archived project', 'status' => 'archived']);

        Livewire::actingAs($user)
            ->test('projects.index')
            ->set('statusFilter', 'archived')
            ->assertSee('Archived project')
            ->assertDontSee('Active project');
    }

    public function test_dashboard_shows_owned_project_count_and_recent_projects(): void
    {
        $user = User::factory()->create();
        $user->projects()->create(['name' => 'Dashboard project']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 active')
            ->assertSee('Dashboard project');
    }
}
