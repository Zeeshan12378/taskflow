<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_and_task_updates_notify_the_assignee_in_the_inbox(): void
    {
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($assignee->id, ['role' => 'member']);
        $todo = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $inProgress = $project->statuses()->where('name', 'In Progress')->firstOrFail();

        Livewire::actingAs($owner)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Implement sign in')
            ->set('statusId', (string) $todo->id)
            ->set('assigneeId', (string) $assignee->id)
            ->call('save')
            ->assertHasNoErrors();

        $task = $project->tasks()->where('title', 'Implement sign in')->firstOrFail();
        $assignmentNotification = $assignee->notifications()->firstOrFail();
        $this->assertSame('assigned', $assignmentNotification->data['kind']);
        $this->assertSame($task->id, $assignmentNotification->data['task_id']);
        $this->assertCount(0, $owner->notifications);

        Livewire::actingAs($owner)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('statusId', (string) $inProgress->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, $assignee->notifications()->count());
        $this->assertTrue($assignee->notifications()->whereJsonContains('data->kind', 'updated')->exists());

        $this->actingAs($assignee)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Implement sign in')
            ->assertSee('assigned a task to you');
    }

    public function test_comment_notification_goes_to_creator_and_can_be_marked_read(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($member->id, ['role' => 'member']);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'title' => 'Review API design',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('commentBody', 'The endpoint shape looks good.')
            ->call('addComment')
            ->assertHasNoErrors();

        $notification = $owner->notifications()->firstOrFail();
        $this->assertSame('commented', $notification->data['kind']);
        $this->assertCount(0, $member->notifications);

        Livewire::actingAs($owner)
            ->test('notifications.index')
            ->call('markAsRead', $notification->id)
            ->assertHasNoErrors();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_reviewer_is_notified_when_a_review_is_assigned(): void
    {
        $owner = User::factory()->create();
        $reviewer = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($reviewer->id, ['role' => 'member']);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'title' => 'Review pagination behavior',
            'priority' => 'medium',
        ]);

        Livewire::actingAs($owner)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('reviewerId', (string) $reviewer->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('review_assigned', $reviewer->notifications()->firstOrFail()->data['kind']);
    }

    public function test_inbox_hides_notifications_after_project_access_is_removed(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($member->id, ['role' => 'member']);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'title' => 'Private after removal',
            'priority' => 'medium',
        ]);

        $member->notify(new TaskNotification(
            kind: 'assigned',
            taskId: $task->id,
            projectId: $project->id,
            taskTitle: $task->title,
            projectName: $project->name,
            actorId: $owner->id,
            actorName: $owner->name,
        ));
        $project->members()->detach($member->id);

        $this->actingAs($member)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('You’re all caught up')
            ->assertDontSee('Private after removal');
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->createProject($owner);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $task = $project->tasks()->create([
            'user_id' => $owner->id,
            'project_status_id' => $status->id,
            'title' => 'Owner only task',
            'priority' => 'medium',
        ]);

        $owner->notify(new TaskNotification(
            kind: 'updated',
            taskId: $task->id,
            projectId: $project->id,
            taskTitle: $task->title,
            projectName: $project->name,
            actorId: $otherUser->id,
            actorName: $otherUser->name,
        ));
        $notificationId = $owner->notifications()->firstOrFail()->id;

        Livewire::actingAs($otherUser)
            ->test('notifications.index')
            ->call('markAsRead', $notificationId)
            ->assertNotFound();
    }

    private function createProject(User $owner): Project
    {
        $project = $owner->projects()->create(['name' => 'Notifications test project']);
        $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);

        return $project;
    }
}
