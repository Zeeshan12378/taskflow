<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RecurringTaskSchedule;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecurringTaskSchedulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_can_schedule_a_template_for_the_next_selected_weekday(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 29)->setTime(12, 0));
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $template = $this->createTemplate($project, $user);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('recurringTemplateId', (string) $template->id)
            ->set('recurringWeekday', '1')
            ->call('saveRecurringTaskSchedule')
            ->assertHasNoErrors()
            ->assertSee('Weekly task schedule created.');

        $schedule = $project->recurringTaskSchedules()->firstOrFail();
        $this->assertDatabaseHas('recurring_task_schedules', [
            'project_id' => $project->id,
            'created_by' => $user->id,
            'template_name' => 'Weekly report',
            'title' => 'Prepare weekly report',
            'weekday' => 1,
            'next_run_on' => '2026-11-02 00:00:00',
            'is_active' => 1,
        ]);
        $this->assertSame([
            ['text' => 'Review the release notes', 'completed' => false],
        ], $schedule->checklist);
        $this->assertSame(0, $project->tasks()->count());
    }

    public function test_command_generates_each_due_occurrence_and_does_not_duplicate_on_repeated_runs(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $schedule = $this->createSchedule($project, $user, [
            'weekday' => 1,
            'next_run_on' => '2026-10-26',
            'checklist' => [['text' => 'Review the release notes', 'completed' => true]],
        ]);

        $this->artisan('tasks:generate-recurring')
            ->expectsOutput('Generated 2 recurring tasks.')
            ->assertExitCode(0);

        $tasks = $project->tasks()->orderBy('due_date')->get();
        $this->assertCount(2, $tasks);
        $this->assertSame(['2026-10-26', '2026-11-02'], $tasks->map(
            fn (Task $task): string => $task->due_date->toDateString(),
        )->all());
        $this->assertSame($user->id, $tasks->first()->user_id);
        $this->assertSame('To Do', $tasks->first()->status->name);
        $this->assertSame([
            ['text' => 'Review the release notes', 'completed' => false],
        ], $tasks->first()->checklist);
        $this->assertSame('2026-11-09', $schedule->refresh()->next_run_on->toDateString());

        $this->artisan('tasks:generate-recurring')
            ->expectsOutput('Generated 0 recurring tasks.')
            ->assertExitCode(0);

        $this->assertSame(2, $project->tasks()->count());
    }

    public function test_command_skips_paused_schedules(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $schedule = $this->createSchedule($project, $user, [
            'next_run_on' => '2026-11-02',
            'is_active' => false,
        ]);

        $this->artisan('tasks:generate-recurring')
            ->expectsOutput('Generated 0 recurring tasks.')
            ->assertExitCode(0);

        $this->assertSame(0, $project->tasks()->count());
        $this->assertSame('2026-11-02', $schedule->refresh()->next_run_on->toDateString());
    }

    public function test_project_member_cannot_create_or_manage_a_recurring_schedule(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($member->id, ['role' => 'member']);
        $template = $this->createTemplate($project, $owner);
        $schedule = $this->createSchedule($project, $owner);

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id])
            ->set('recurringTemplateId', (string) $template->id)
            ->set('recurringWeekday', '1')
            ->call('saveRecurringTaskSchedule')
            ->assertForbidden();

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id])
            ->call('toggleRecurringTaskSchedule', $schedule->id)
            ->assertForbidden();

        $this->assertSame(1, $project->recurringTaskSchedules()->count());
        $this->assertTrue($schedule->refresh()->is_active);
    }

    public function test_recurring_schedule_cannot_use_another_project_template(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user);
        $template = $this->createTemplate($otherProject, $user);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('recurringTemplateId', (string) $template->id)
            ->set('recurringWeekday', '1')
            ->call('saveRecurringTaskSchedule')
            ->assertHasErrors(['recurringTemplateId']);

        $this->assertSame(0, $project->recurringTaskSchedules()->count());
    }

    public function test_project_manager_can_pause_resume_and_delete_a_recurring_schedule(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $schedule = $this->createSchedule($project, $user, ['next_run_on' => '2026-11-02']);

        $component = Livewire::actingAs($user)->test('tasks.form', ['project' => $project->id]);
        $component->call('toggleRecurringTaskSchedule', $schedule->id)
            ->assertHasNoErrors()
            ->assertSee('Weekly task schedule paused.');

        $this->assertFalse($schedule->refresh()->is_active);
        $this->assertSame('2026-11-09', $schedule->next_run_on->toDateString());

        $component->call('toggleRecurringTaskSchedule', $schedule->id)
            ->assertHasNoErrors()
            ->assertSee('Weekly task schedule resumed.');

        $this->assertTrue($schedule->refresh()->is_active);

        $this->artisan('tasks:generate-recurring')
            ->expectsOutput('Generated 0 recurring tasks.')
            ->assertExitCode(0);

        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(12, 0));
        $this->artisan('tasks:generate-recurring')
            ->expectsOutput('Generated 1 recurring tasks.')
            ->assertExitCode(0);

        $component->call('deleteRecurringTaskSchedule', $schedule->id)
            ->assertHasNoErrors()
            ->assertSee('Weekly task schedule deleted.');

        $this->assertSame(1, $project->tasks()->count());
        $this->assertDatabaseMissing('recurring_task_schedules', ['id' => $schedule->id]);
    }

    private function createProject(User $owner): Project
    {
        $project = $owner->projects()->create(['name' => 'Recurring work']);
        $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);

        return $project;
    }

    private function createTemplate(Project $project, User $creator): TaskTemplate
    {
        return $project->taskTemplates()->create([
            'created_by' => $creator->id,
            'name' => 'Weekly report',
            'title' => 'Prepare weekly report',
            'description' => '<p>Summarize weekly progress.</p>',
            'type' => 'task',
            'priority' => 'medium',
            'story_points' => 3,
            'checklist' => [['text' => 'Review the release notes', 'completed' => true]],
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createSchedule(Project $project, User $creator, array $attributes = []): RecurringTaskSchedule
    {
        return $project->recurringTaskSchedules()->create(array_merge([
            'created_by' => $creator->id,
            'template_name' => 'Weekly report',
            'title' => 'Prepare weekly report',
            'description' => '<p>Summarize weekly progress.</p>',
            'type' => 'task',
            'priority' => 'medium',
            'story_points' => 3,
            'checklist' => [['text' => 'Review the release notes', 'completed' => false]],
            'weekday' => 1,
            'next_run_on' => today()->addWeek()->toDateString(),
            'is_active' => true,
        ], $attributes));
    }
}
