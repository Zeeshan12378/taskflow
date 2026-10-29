<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_tab_shows_project_metrics_and_task_distributions(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Reports workspace']);
        $statuses = $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);
        $open = $statuses->firstWhere('name', 'To Do');
        $complete = $statuses->firstWhere('name', 'Complete');

        $this->actingAs($user);

        $olderTask = $project->tasks()->create([
            'user_id' => $user->id,
            'project_status_id' => $open->id,
            'title' => 'Older report task',
        ]);
        $olderTimestamp = now()->subDays(10);
        $project->tasks()->whereKey($olderTask->id)->update([
            'created_at' => $olderTimestamp,
            'updated_at' => $olderTimestamp,
        ]);
        TaskActivity::query()->where('task_id', $olderTask->id)->update([
            'created_at' => $olderTimestamp,
            'updated_at' => $olderTimestamp,
        ]);

        $project->tasks()->create([
            'user_id' => $user->id,
            'assignee_id' => $user->id,
            'project_status_id' => $open->id,
            'title' => 'Open report task',
            'type' => 'bug',
            'priority' => 'high',
            'due_date' => today()->addDays(2),
        ]);
        $completedTask = $project->tasks()->create([
            'user_id' => $user->id,
            'project_status_id' => $open->id,
            'title' => 'Completed report task',
            'type' => 'feature',
            'priority' => 'urgent',
        ]);

        $completedTask->update(['project_status_id' => $complete->id]);

        Livewire::actingAs($user)
            ->test('projects.reports', ['project' => $project->id])
            ->assertSet('period', '30')
            ->assertSee('Reports workspace')
            ->assertSee('1 Completed')
            ->assertSee('1 Updated')
            ->assertSee('3 Created')
            ->assertSee('1 Due soon')
            ->assertSee('Work items by status')
            ->assertSee('Work items by type')
            ->assertSee('Work items by assignee')
            ->assertSee('Unassigned')
            ->assertSee('Bug')
            ->assertSee('Feature')
            ->assertSee('Work item creation trend')
            ->assertSee('Work item completion trend')
            ->assertSee('Work item cycle and lead time')
            ->assertSee('Open report task')
            ->assertSee('Completed report task')
            ->set('period', '7')
            ->assertSet('period', '7')
            ->assertSee('in the last 7 days')
            ->assertSee('2 Created');
    }

    public function test_guest_is_redirected_from_project_reports(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'Private reports']);

        $this->get(route('projects.reports', $project))
            ->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_reports_for_an_inaccessible_project(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'Private reports']);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.reports', $project))
            ->assertNotFound();
    }
}
