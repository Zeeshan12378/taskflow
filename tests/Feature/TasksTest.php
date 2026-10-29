<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_project_board_and_task_list(): void
    {
        $project = $this->createProject(User::factory()->create());

        $this->get(route('projects.board', $project))->assertRedirect(route('login'));
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }

    public function test_project_owner_can_create_a_task_with_project_owned_status(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Prepare release notes')
            ->set('description', 'Summarize the work for this release.')
            ->set('statusId', (string) $status->id)
            ->set('priority', 'high')
            ->set('type', 'bug')
            ->set('dueDate', today()->addDays(3)->format('Y-m-d'))
            ->set('githubUrl', 'https://github.com/taskflow/app/pull/42')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('projects.board', $project));

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_status_id' => $status->id,
            'title' => 'Prepare release notes',
            'priority' => 'high',
            'type' => 'bug',
            'github_url' => 'https://github.com/taskflow/app/pull/42',
        ]);
    }

    public function test_project_owner_can_create_and_assign_project_labels_and_remove_them_later(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        $component = Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('labelName', '  Needs review  ')
            ->set('labelColor', 'violet')
            ->call('createProjectLabel')
            ->assertHasNoErrors()
            ->assertSee('Project label created.')
            ->set('title', 'Review the release')
            ->set('statusId', (string) $status->id)
            ->call('save')
            ->assertHasNoErrors();

        $label = $project->labels()->where('name', 'Needs review')->firstOrFail();
        $task = $project->tasks()->where('title', 'Review the release')->firstOrFail();

        $this->assertSame('violet', $label->color);
        $this->assertDatabaseHas('project_label_task', [
            'project_label_id' => $label->id,
            'task_id' => $task->id,
        ]);
        $this->assertSame(
            ['from' => null, 'to' => 'Needs review'],
            $task->activities()->where('action', 'updated')->latest()->firstOrFail()->details['labels'],
        );

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('selectedLabelIds', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('project_label_task', [
            'project_label_id' => $label->id,
            'task_id' => $task->id,
        ]);
        $this->assertSame('cleared labels', $task->activities()->where('action', 'updated')->latest()->firstOrFail()->summary());

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->call('deleteProjectLabel', $label->id)
            ->assertHasNoErrors()
            ->assertSee('Project label deleted.');

        $this->assertDatabaseMissing('project_labels', ['id' => $label->id]);
    }

    public function test_task_form_rejects_labels_from_another_project_and_duplicate_label_names(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user, 'Other project');
        $foreignLabel = $otherProject->labels()->create(['name' => 'Foreign', 'color' => 'red']);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('labelName', 'Local')
            ->call('createProjectLabel')
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Must not save')
            ->set('selectedLabelIds', [$foreignLabel->id])
            ->call('save')
            ->assertHasErrors(['selectedLabelIds.0']);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('labelName', 'Local')
            ->call('createProjectLabel')
            ->assertHasErrors(['labelName']);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('labelName', 'Invalid color')
            ->set('labelColor', 'not-a-color')
            ->call('createProjectLabel')
            ->assertHasErrors(['labelColor']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Must not save']);
        $this->assertSame(1, $project->labels()->where('name', 'Local')->count());
    }

    public function test_project_members_can_add_and_apply_labels_but_cannot_delete_them(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($member->id, ['role' => 'member']);
        $label = $project->labels()->create(['name' => 'Backend', 'color' => 'blue']);
        $task = $this->createTask($owner, $project, ['title' => 'Update service']);

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id])
            ->set('labelName', 'New label')
            ->assertSee('Add label')
            ->call('createProjectLabel')
            ->assertHasNoErrors()
            ->assertSee('Project label created.');

        $newLabel = $project->labels()->where('name', 'New label')->firstOrFail();

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id])
            ->call('deleteProjectLabel', $label->id)
            ->assertForbidden();

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('selectedLabelIds', [$label->id, $newLabel->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('project_label_task', [
            'project_label_id' => $label->id,
            'task_id' => $task->id,
        ]);
        $this->assertDatabaseHas('project_label_task', [
            'project_label_id' => $newLabel->id,
            'task_id' => $task->id,
        ]);
    }

    public function test_labels_render_on_task_views_and_filter_board_and_global_tasks(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $label = $project->labels()->create(['name' => 'Customer visible', 'color' => 'emerald']);
        $labeledTask = $this->createTask($user, $project, ['title' => 'Labeled task']);
        $unlabeledTask = $this->createTask($user, $project, ['title' => 'Unlabeled task']);
        $labeledTask->labels()->attach($label);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->set('labelFilter', (string) $label->id)
            ->assertSee('Labeled task')
            ->assertSee('Customer visible')
            ->assertDontSee('Unlabeled task')
            ->call('sortTask', $unlabeledTask->id, 0, $project->statuses()->where('name', 'To Do')->value('id'))
            ->assertSee('Clear board filters before reordering tasks.');

        Livewire::actingAs($user)
            ->test('projects.list', ['project' => $project->id])
            ->assertSee('Customer visible');

        Livewire::actingAs($user)
            ->test('projects.sprints', ['project' => $project->id])
            ->assertSee('Customer visible');

        Livewire::actingAs($user)
            ->test('tasks.index')
            ->set('projectFilter', (string) $project->id)
            ->set('labelFilter', (string) $label->id)
            ->assertSee('Labeled task')
            ->assertSee('Customer visible')
            ->assertDontSee('Unlabeled task');
    }

    public function test_task_checklist_items_are_saved_and_completion_changes_are_recorded(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $checklist = [
            ['text' => 'Confirm the behavior', 'completed' => false],
            ['text' => 'Review the changes', 'completed' => false],
        ];

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Task with checklist')
            ->set('statusId', (string) $status->id)
            ->set('checklistItems', $checklist)
            ->call('save')
            ->assertHasNoErrors();

        $task = Task::query()->where('title', 'Task with checklist')->firstOrFail();
        $this->assertSame($checklist, $task->checklist);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id, 'modal' => true])
            ->set('checklistItems.0.completed', true)
            ->call('saveChecklist')
            ->assertHasNoErrors();

        $task->refresh();
        $this->assertSame([
            ['text' => 'Confirm the behavior', 'completed' => true],
            ['text' => 'Review the changes', 'completed' => false],
        ], $task->checklist);
        $this->assertSame(
            [
                'from' => '0 of 2 checklist items complete',
                'to' => '1 of 2 checklist items complete',
            ],
            $task->activities()->latest()->firstOrFail()->details['checklist'],
        );
    }

    public function test_task_checklist_rejects_unexpected_item_keys_and_overlong_lists(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Invalid checklist')
            ->set('checklistItems', [[
                'text' => 'Approved step',
                'completed' => false,
                'is_admin' => true,
            ]])
            ->call('save')
            ->assertHasErrors(['checklistItems.0']);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Too many steps')
            ->set('checklistItems', array_fill(0, 31, ['text' => 'Step', 'completed' => false]))
            ->call('save')
            ->assertHasErrors(['checklistItems']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid checklist']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Too many steps']);
    }

    public function test_checklist_item_text_is_escaped_in_the_task_form(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, [
            'checklist' => [['text' => '<script>alert(1)</script>', 'completed' => false]],
        ]);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id, 'modal' => true])
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_project_owner_can_save_and_apply_a_task_template_without_overwriting_task_specific_fields(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('templateName', 'Regression test')
            ->set('title', 'Run regression tests')
            ->set('description', '<p>Cover the release-critical behavior.</p>')
            ->set('type', 'bug')
            ->set('priority', 'high')
            ->set('storyPoints', '8')
            ->set('checklistItems', [['text' => 'Review the release notes', 'completed' => true]])
            ->call('saveTaskTemplate')
            ->assertHasNoErrors()
            ->assertSee('Task template saved.');

        $template = $project->taskTemplates()->firstOrFail();
        $this->assertDatabaseHas('task_templates', [
            'id' => $template->id,
            'created_by' => $user->id,
            'name' => 'Regression test',
            'title' => 'Run regression tests',
            'type' => 'bug',
            'priority' => 'high',
            'story_points' => 8,
        ]);
        $this->assertSame([
            ['text' => 'Review the release notes', 'completed' => false],
        ], $template->checklist);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('selectedTaskTemplate', (string) $template->id)
            ->set('statusId', (string) $status->id)
            ->set('dueDate', today()->addDays(3)->format('Y-m-d'))
            ->call('applyTaskTemplate')
            ->assertHasNoErrors()
            ->assertSet('title', 'Run regression tests')
            ->assertSet('description', '<p>Cover the release-critical behavior.</p>')
            ->assertSet('type', 'bug')
            ->assertSet('priority', 'high')
            ->assertSet('storyPoints', '8')
            ->assertSet('checklistItems', [['text' => 'Review the release notes', 'completed' => false]])
            ->assertSet('statusId', (string) $status->id)
            ->assertSet('dueDate', today()->addDays(3)->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_status_id' => $status->id,
            'title' => 'Run regression tests',
            'priority' => 'high',
            'type' => 'bug',
            'story_points' => 8,
            'due_date' => today()->addDays(3)->startOfDay()->toDateTimeString(),
        ]);
        $this->assertSame([
            ['text' => 'Review the release notes', 'completed' => false],
        ], Task::query()->where('title', 'Run regression tests')->firstOrFail()->checklist);
    }

    public function test_project_member_can_apply_but_cannot_manage_project_task_templates(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($member->id, ['role' => 'member']);
        $template = $project->taskTemplates()->create([
            'created_by' => $owner->id,
            'name' => 'Bug report',
            'title' => 'Investigate reported bug',
            'description' => 'Reproduce the issue.',
            'type' => 'bug',
            'priority' => 'high',
            'story_points' => null,
        ]);

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id])
            ->set('selectedTaskTemplate', (string) $template->id)
            ->call('applyTaskTemplate')
            ->assertHasNoErrors()
            ->assertSet('title', 'Investigate reported bug')
            ->call('saveTaskTemplate')
            ->assertForbidden();

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id])
            ->call('deleteTaskTemplate', $template->id)
            ->assertForbidden();

        $this->assertDatabaseHas('task_templates', ['id' => $template->id, 'name' => 'Bug report']);
    }

    public function test_project_task_template_names_must_be_unique_within_the_project(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $project->taskTemplates()->create([
            'created_by' => $user->id,
            'name' => 'Bug report',
            'title' => 'Investigate reported bug',
            'description' => null,
            'type' => 'bug',
            'priority' => 'high',
            'story_points' => null,
        ]);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('templateName', 'Bug report')
            ->set('title', 'Another bug report')
            ->call('saveTaskTemplate')
            ->assertHasErrors(['templateName'])
            ->assertSee('The template name has already been taken.');

        $this->assertSame(1, $project->taskTemplates()->count());
    }

    public function test_project_manager_can_delete_a_project_task_template(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $template = $project->taskTemplates()->create([
            'created_by' => $user->id,
            'name' => 'Bug report',
            'title' => 'Investigate reported bug',
            'description' => null,
            'type' => 'bug',
            'priority' => 'high',
            'story_points' => null,
        ]);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->call('deleteTaskTemplate', $template->id)
            ->assertHasNoErrors()
            ->assertSee('Task template deleted.');

        $this->assertDatabaseMissing('task_templates', ['id' => $template->id]);
    }

    public function test_project_task_template_cannot_be_applied_to_another_project(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user, 'Other project');
        $template = $otherProject->taskTemplates()->create([
            'created_by' => $user->id,
            'name' => 'Private template',
            'title' => 'Other project task',
            'description' => null,
            'type' => 'task',
            'priority' => 'medium',
            'story_points' => null,
        ]);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('selectedTaskTemplate', (string) $template->id)
            ->call('applyTaskTemplate')
            ->assertHasErrors(['selectedTaskTemplate']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Other project task']);
    }

    public function test_task_github_reference_rejects_non_github_and_non_https_urls(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Unsafe reference')
            ->set('githubUrl', 'https://github.com.attacker.example/taskflow/app/pull/42')
            ->call('save')
            ->assertHasErrors(['githubUrl'])
            ->assertSee('The GitHub reference must be an https://github.com URL.')
            ->set('githubUrl', 'http://github.com/taskflow/app/pull/42')
            ->call('save')
            ->assertHasErrors(['githubUrl']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Unsafe reference']);
    }

    public function test_task_form_rejects_status_from_another_project(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user, 'Second project');
        $foreignStatus = $otherProject->statuses()->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Invalid task')
            ->set('statusId', (string) $foreignStatus->id)
            ->call('save')
            ->assertHasErrors(['statusId']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid task']);
    }

    public function test_task_can_be_assigned_to_a_project_member_and_listed_for_them(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::query()->create(['owner_id' => $owner->id, 'name' => 'Design team']);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $team->members()->attach($member->id, ['role' => 'member']);
        $project = $this->createProject($owner, 'Assigned project');
        $project->update(['team_id' => $team->id]);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($owner)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Task for a teammate')
            ->set('statusId', (string) $status->id)
            ->set('priority', 'high')
            ->set('assigneeId', (string) $member->id)
            ->call('save')
            ->assertHasNoErrors();

        $task = Task::query()->where('title', 'Task for a teammate')->firstOrFail();
        $this->assertSame($member->id, $task->assignee_id);

        $this->actingAs($member)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task for a teammate')
            ->assertSee($member->name);
    }

    public function test_task_cannot_be_assigned_to_a_person_without_project_access(): void
    {
        $user = User::factory()->create();
        $outsider = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Invalid assignment')
            ->set('statusId', (string) $status->id)
            ->set('assigneeId', (string) $outsider->id)
            ->call('save')
            ->assertHasErrors(['assigneeId']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid assignment']);
    }

    public function test_task_form_validates_priority_and_title(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', '')
            ->set('priority', 'critical')
            ->set('type', 'incident')
            ->call('save')
            ->assertHasErrors(['title' => 'required', 'priority' => 'in', 'type' => 'in']);
    }

    public function test_owner_can_update_task_details_and_status(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Draft']);
        $targetStatus = $project->statuses()->where('name', 'Verify')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('title', 'Review final draft')
            ->set('statusId', (string) $targetStatus->id)
            ->set('reviewerId', (string) $user->id)
            ->set('priority', 'low')
            ->set('type', 'feature')
            ->set('dueDate', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $targetStatus->id,
            'title' => 'Review final draft',
            'priority' => 'low',
            'type' => 'feature',
            'due_date' => null,
            'reviewer_id' => $user->id,
        ]);
    }

    public function test_task_modal_can_create_without_navigating_away_from_board(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'modal' => true])
            ->set('title', 'Created in board modal')
            ->set('statusId', (string) $status->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('task-modal-saved')
            ->assertNoRedirect();

        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Created in board modal']);
    }

    public function test_task_modal_opens_from_board_and_inline_fields_save_and_validate(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Old title', 'description' => 'Old description']);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('openTask', $task->id)
            ->assertSet('taskModalOpen', true)
            ->assertSet('modalTaskId', $task->id);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id, 'modal' => true])
            ->assertSee('Old title')
            ->assertSee('Old description')
            ->call('editInlineField', 'title')
            ->set('title', '')
            ->call('saveInlineField', 'title')
            ->assertHasErrors(['title' => 'required'])
            ->call('cancelInlineField', 'title')
            ->set('title', 'Renamed inline')
            ->call('saveInlineField', 'title')
            ->assertHasNoErrors()
            ->assertDispatched('task-modal-refresh')
            ->assertSet('editingTitle', false)
            ->set('description', 'Updated inline description')
            ->call('saveInlineField', 'description')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Renamed inline', 'description' => 'Updated inline description']);
    }

    public function test_task_modal_details_use_compact_rows_and_edit_one_value_at_a_time(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Details panel task']);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id, 'modal' => true])
            ->assertSee('Details')
            ->assertSee('Assignee')
            ->assertSee('Priority')
            ->assertSee('Reporter')
            ->call('editDetailField', 'priority')
            ->set('priority', 'urgent')
            ->call('saveDetailField', 'priority')
            ->assertHasNoErrors()
            ->assertSet('editingDetailField', null)
            ->assertDispatched('task-modal-refresh');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'priority' => 'urgent']);
    }

    public function test_task_github_reference_can_be_added_cleared_and_is_recorded_in_activity(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Link the pull request']);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id, 'modal' => true])
            ->assertSee('GitHub reference')
            ->call('editDetailField', 'githubUrl')
            ->set('githubUrl', 'https://github.com/taskflow/app/pull/42')
            ->call('saveDetailField', 'githubUrl')
            ->assertHasNoErrors()
            ->assertSee('https://github.com/taskflow/app/pull/42')
            ->assertSee('set GitHub reference to https://github.com/taskflow/app/pull/42')
            ->call('editDetailField', 'githubUrl')
            ->set('githubUrl', '')
            ->call('saveDetailField', 'githubUrl')
            ->assertHasNoErrors()
            ->assertSee('cleared GitHub reference');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'github_url' => null]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'action' => 'updated',
        ]);
    }

    public function test_task_story_point_estimates_validate_and_are_editable_in_the_modal(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Estimate sprint work']);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id, 'modal' => true])
            ->assertSee('Estimate')
            ->call('editDetailField', 'storyPoints')
            ->set('storyPoints', '8')
            ->call('saveDetailField', 'storyPoints')
            ->assertHasNoErrors()
            ->assertDispatched('task-modal-refresh');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'story_points' => 8]);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id, 'modal' => true])
            ->set('storyPoints', '4')
            ->call('save')
            ->assertHasErrors('storyPoints');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'story_points' => 8]);
    }

    public function test_project_board_filters_combine_task_fields_and_date_ranges(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($member->id, ['role' => 'member']);
        $todo = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $blocked = $project->statuses()->where('name', 'Blocked')->firstOrFail();
        $bug = $this->createTask($owner, $project, [
            'title' => 'Assigned bug due soon',
            'type' => 'bug',
            'assignee_id' => $member->id,
            'reviewer_id' => $owner->id,
            'due_date' => today()->addDays(2),
        ]);
        $this->createTask($owner, $project, ['title' => 'Unassigned feature', 'type' => 'feature']);
        $blockedTask = $this->createTask($owner, $project, [
            'title' => 'Blocked bug',
            'type' => 'bug',
            'project_status_id' => $blocked->id,
            'blocked_reason' => 'Waiting on an API key',
        ]);

        $board = Livewire::actingAs($owner)->test('projects.board', ['project' => $project->id]);
        $board->set('typeFilter', 'bug')
            ->set('assigneeFilter', (string) $member->id)
            ->set('reviewerFilter', (string) $owner->id)
            ->set('reporterFilter', (string) $owner->id)
            ->set('dueFilter', 'next_7_days')
            ->set('dueFrom', today()->addDay()->format('Y-m-d'))
            ->set('dueTo', today()->addDays(3)->format('Y-m-d'))
            ->assertSee('Assigned bug due soon')
            ->assertDontSee('Unassigned feature')
            ->assertDontSee('Blocked bug');

        $board->call('clearFilters')
            ->set('statusFilter', (string) $blocked->id)
            ->assertSee('Blocked bug')
            ->assertDontSee('Assigned bug due soon');
        $this->assertCount(1, $board->get('visibleStatuses'));

        $board->set('statusFilter', 'all')
            ->set('blockedOnly', true)
            ->assertSee('Blocked bug')
            ->assertDontSee('Assigned bug due soon')
            ->assertSet('dueFrom', '')
            ->assertSet('dueTo', '');

        $this->assertSame($todo->id, $bug->fresh()->project_status_id);
        $this->assertSame($blocked->id, $blockedTask->fresh()->project_status_id);
    }

    public function test_due_filter_distinguishes_next_week_from_later_upcoming_tasks(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $this->createTask($user, $project, ['title' => 'Due soon', 'due_date' => today()->addDays(3)]);
        $this->createTask($user, $project, ['title' => 'Due later', 'due_date' => today()->addDays(10)]);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->set('dueFilter', 'next_7_days')
            ->assertSee('Due soon')
            ->assertDontSee('Due later');

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->set('dueFilter', 'upcoming')
            ->assertSee('Due later')
            ->assertDontSee('Due soon');

        Livewire::actingAs($user)
            ->test('tasks.index')
            ->set('dueFilter', 'upcoming')
            ->assertSee('Due later')
            ->assertDontSee('Due soon');
    }

    public function test_project_board_cycle_filter_only_uses_the_current_team_cycles(): void
    {
        $owner = User::factory()->create();
        $team = Team::query()->create(['owner_id' => $owner->id, 'name' => 'Board filters team']);
        $team->members()->attach($owner->id, ['role' => 'owner']);
        $project = $this->createProject($owner);
        $project->update(['team_id' => $team->id]);
        $cycle = $team->cycles()->create(['name' => 'Current sprint', 'start_date' => today(), 'end_date' => today()->addDays(14)]);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $this->createTask($owner, $project, ['title' => 'In sprint', 'cycle_id' => $cycle->id, 'project_status_id' => $status->id]);
        $this->createTask($owner, $project, ['title' => 'Outside sprint', 'project_status_id' => $status->id]);

        Livewire::actingAs($owner)
            ->test('projects.board', ['project' => $project->id])
            ->assertSee('Current sprint')
            ->set('cycleFilter', (string) $cycle->id)
            ->assertSee('In sprint')
            ->assertDontSee('Outside sprint')
            ->set('cycleFilter', 'none')
            ->assertSee('Outside sprint')
            ->assertDontSee('In sprint');
    }

    public function test_urgent_priority_can_be_saved_and_filtered(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Production outage')
            ->set('statusId', (string) $status->id)
            ->set('priority', 'urgent')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'Production outage', 'priority' => 'urgent']);

        Livewire::actingAs($user)
            ->test('tasks.index')
            ->set('priorityFilter', 'urgent')
            ->assertSee('Production outage');
    }

    public function test_task_creation_and_updates_are_recorded_in_activity_history(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id])
            ->set('title', 'Add audit events')
            ->set('statusId', (string) $status->id)
            ->call('save')
            ->assertHasNoErrors();

        $task = $project->tasks()->where('title', 'Add audit events')->firstOrFail();
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'action' => 'created',
        ]);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('priority', 'urgent')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'action' => 'updated',
        ]);

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->assertSee('Activity')
            ->assertSee('changed priority from Medium to Urgent');
    }

    public function test_project_member_can_comment_and_comment_is_recorded_in_activity(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $project = $this->createProject($owner);
        $project->members()->attach($member->id, ['role' => 'member']);
        $task = $this->createTask($owner, $project, ['title' => 'Discuss implementation']);

        Livewire::actingAs($member)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('commentBody', 'I can take this review.')
            ->call('addComment')
            ->assertHasNoErrors()
            ->assertSee('I can take this review.')
            ->assertSee('left a comment');

        $this->assertDatabaseHas('task_comments', [
            'task_id' => $task->id,
            'user_id' => $member->id,
            'body' => 'I can take this review.',
        ]);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $member->id,
            'action' => 'commented',
        ]);
    }

    public function test_board_groups_tasks_into_status_columns_and_moves_tasks(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Board task']);
        $task->update(['reviewer_id' => $user->id]);
        $targetStatus = $project->statuses()->where('name', 'Verify')->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->assertSee('Board task')
            ->assertSee('To Do')
            ->call('moveTask', $task->id, $targetStatus->id)
            ->assertHasNoErrors()
            ->assertSee('Task moved to Verify.');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_status_id' => $targetStatus->id]);
    }

    public function test_board_tasks_can_be_reordered_vertically_and_order_is_persisted(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $firstTask = $this->createTask($user, $project, ['title' => 'First card']);
        $secondTask = $this->createTask($user, $project, ['title' => 'Second card']);
        $thirdTask = $this->createTask($user, $project, ['title' => 'Third card']);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->assertSee('delayOnTouchOnly: true', false)
            ->assertDontSee('wire:sort:handle', false)
            ->call('sortTask', $thirdTask->id, 0, $status->id)
            ->assertSee('Task order updated.');

        $this->assertSame(
            [$thirdTask->id, $firstTask->id, $secondTask->id],
            $project->tasks()->where('project_status_id', $status->id)->orderBy('board_position')->pluck('id')->all(),
        );
    }

    public function test_board_dragging_a_task_to_another_column_saves_its_position(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $sourceStatus = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $targetStatus = $project->statuses()->where('name', 'In Progress')->firstOrFail();
        $movedTask = $this->createTask($user, $project, ['title' => 'Move this card']);
        $existingTargetTask = $this->createTask($user, $project, [
            'title' => 'Keep this card first',
            'project_status_id' => $targetStatus->id,
        ]);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('sortTask', $movedTask->id, 1, $targetStatus->id)
            ->assertSee('Task moved to In Progress.');

        $this->assertSame(
            [$existingTargetTask->id, $movedTask->id],
            $project->tasks()->where('project_status_id', $targetStatus->id)->orderBy('board_position')->pluck('id')->all(),
        );
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $movedTask->id,
            'actor_id' => $user->id,
            'action' => 'updated',
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $movedTask->id,
            'project_status_id' => $targetStatus->id,
            'board_position' => 1,
        ]);
    }

    public function test_board_dragging_into_review_without_a_reviewer_keeps_task_in_its_column(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Needs review']);
        $sourceStatusId = $task->project_status_id;
        $reviewStatus = $project->statuses()->where('name', 'Verify')->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('sortTask', $task->id, 0, $reviewStatus->id)
            ->assertSee('Assign a reviewer in task details before moving this task into review.');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $sourceStatusId,
            'board_position' => 0,
        ]);
        $this->assertDatabaseMissing('task_activities', [
            'task_id' => $task->id,
            'action' => 'updated',
        ]);
    }

    public function test_board_dragging_into_blocked_without_a_reason_keeps_task_in_its_column(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Needs investigation']);
        $sourceStatusId = $task->project_status_id;
        $blockedStatus = $project->statuses()->whereRaw('lower(name) = ?', ['blocked'])->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('sortTask', $task->id, 0, $blockedStatus->id)
            ->assertSee('Add a blocked reason in task details before moving this task to Blocked.');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $sourceStatusId,
            'blocked_reason' => null,
        ]);
    }

    public function test_board_sort_rejects_a_status_from_another_project(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user, 'Other project');
        $task = $this->createTask($user, $project);
        $foreignStatus = $otherProject->statuses()->where('name', 'In Progress')->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('sortTask', $task->id, 0, $foreignStatus->id)
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $task->project_status_id,
        ]);
    }

    public function test_board_reordering_is_rejected_while_filters_are_active(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $task = $this->createTask($user, $project);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->set('search', 'Test')
            ->call('sortTask', $task->id, 0, $status->id)
            ->assertSee('Clear board filters before reordering tasks.');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $status->id,
            'board_position' => 0,
        ]);
    }

    public function test_board_quick_add_creates_task_in_selected_column_and_records_activity(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('startQuickAddTask', $status->id)
            ->set('quickAddTitle', 'Write release notes')
            ->call('createQuickTask', $status->id)
            ->assertHasNoErrors()
            ->assertSee('Task created in To Do.');

        $task = $project->tasks()->where('title', 'Write release notes')->firstOrFail();
        $this->assertSame($status->id, $task->project_status_id);
        $this->assertSame($user->id, $task->user_id);
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'actor_id' => $user->id,
            'action' => 'created',
        ]);
    }

    public function test_board_quick_add_requires_a_title(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('startQuickAddTask', $status->id)
            ->set('quickAddTitle', '   ')
            ->call('createQuickTask', $status->id)
            ->assertHasErrors(['quickAddTitle' => 'required']);

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => '',
        ]);
    }

    public function test_board_quick_add_rejects_a_status_from_another_project(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user, 'Other project');
        $foreignStatus = $otherProject->statuses()->where('name', 'To Do')->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->set('quickAddTitle', 'Out of scope')
            ->call('createQuickTask', $foreignStatus->id)
            ->assertNotFound();

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Out of scope',
        ]);
        $this->assertDatabaseMissing('tasks', [
            'project_id' => $otherProject->id,
            'title' => 'Out of scope',
        ]);
    }

    public function test_board_quick_add_requires_reviewer_for_review_status(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->where('name', 'Verify')->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('startQuickAddTask', $status->id)
            ->set('quickAddTitle', 'Review the patch')
            ->call('createQuickTask', $status->id)
            ->assertSee('Use the full task form to assign a reviewer before creating a task in Verify.');

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Review the patch',
        ]);
    }

    public function test_board_quick_add_requires_a_reason_for_blocked_status(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $status = $project->statuses()->whereRaw('lower(name) = ?', ['blocked'])->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('startQuickAddTask', $status->id)
            ->set('quickAddTitle', 'Investigate outage')
            ->call('createQuickTask', $status->id)
            ->assertSee('Use the full task form to add a blocked reason before creating a task in this column.');

        $this->assertDatabaseMissing('tasks', [
            'project_id' => $project->id,
            'title' => 'Investigate outage',
        ]);
    }

    public function test_task_must_have_a_project_reviewer_before_entering_verify(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project);
        $verify = $project->statuses()->where('name', 'Verify')->firstOrFail();

        Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('statusId', (string) $verify->id)
            ->call('save')
            ->assertHasErrors(['reviewerId' => 'required']);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('moveTask', $task->id, $verify->id)
            ->assertSee('Assign a reviewer in task details before moving this task into review.');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_status_id' => $task->project_status_id]);
    }

    public function test_blocked_tasks_require_a_reason_and_clear_it_when_unblocked(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project);
        $blockedStatus = $project->statuses()->whereRaw('lower(name) = ?', ['blocked'])->firstOrFail();
        $todoStatus = $project->statuses()->where('name', 'To Do')->firstOrFail();

        $form = Livewire::actingAs($user)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->set('statusId', (string) $blockedStatus->id)
            ->call('save')
            ->assertHasErrors(['blockedReason' => 'required']);

        $form->set('blockedReason', 'Waiting for the API credentials.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $blockedStatus->id,
            'blocked_reason' => 'Waiting for the API credentials.',
        ]);

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('moveTask', $task->id, $todoStatus->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $todoStatus->id,
            'blocked_reason' => null,
        ]);
    }

    public function test_board_does_not_move_a_task_into_blocked_without_a_reason(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project);
        $blockedStatus = $project->statuses()->whereRaw('lower(name) = ?', ['blocked'])->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('moveTask', $task->id, $blockedStatus->id)
            ->assertSee('Add a blocked reason in task details before moving this task to Blocked.');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_status_id' => $task->project_status_id,
            'blocked_reason' => null,
        ]);
    }

    public function test_task_cannot_be_moved_to_a_status_from_a_different_project(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user, 'Another project');
        $task = $this->createTask($user, $project);
        $foreignStatus = $otherProject->statuses()->firstOrFail();

        Livewire::actingAs($user)
            ->test('projects.board', ['project' => $project->id])
            ->call('moveTask', $task->id, $foreignStatus->id)
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_status_id' => $task->project_status_id]);
    }

    public function test_user_cannot_view_or_update_another_users_task(): void
    {
        $owner = User::factory()->create();
        $project = $this->createProject($owner);
        $task = $this->createTask($owner, $project);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->get(route('projects.board', $project))->assertNotFound();

        Livewire::actingAs($otherUser)
            ->test('tasks.form', ['project' => $project->id, 'task' => $task->id])
            ->assertNotFound();
    }

    public function test_owner_can_add_rename_reorder_and_choose_a_completion_column(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);

        $component = Livewire::actingAs($user)->test('projects.form', ['project' => $project->id]);

        $component->set('statusName', 'Quality Check')
            ->set('statusColor', 'violet')
            ->call('saveStatus')
            ->assertHasNoErrors();

        $newStatus = $project->statuses()->where('name', 'Quality Check')->firstOrFail();

        $component->call('startEditingStatus', $newStatus->id)
            ->set('statusName', 'Review')
            ->call('saveStatus')
            ->assertHasNoErrors();

        $newStatus->refresh();
        $positionBeforeMove = $newStatus->position;
        $component->call('moveStatus', $newStatus->id, 'up');
        $this->assertLessThan($positionBeforeMove, $newStatus->fresh()->position);

        $component->set('completionStatusId', (string) $newStatus->id)
            ->call('setCompletionStatus')
            ->assertHasNoErrors();

        $this->assertSame(1, $project->statuses()->where('is_complete', true)->count());
        $this->assertTrue($newStatus->fresh()->is_complete);
    }

    public function test_completion_column_must_belong_to_project_and_last_completion_column_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $otherProject = $this->createProject($user, 'Other project');
        $foreignStatus = $otherProject->statuses()->firstOrFail();
        $completionStatus = $project->statuses()->where('name', 'Closed')->firstOrFail();

        $component = Livewire::actingAs($user)->test('projects.form', ['project' => $project->id]);

        $component->set('completionStatusId', (string) $foreignStatus->id)
            ->call('setCompletionStatus')
            ->assertHasErrors('completionStatusId');

        $component->set('completionStatusId', (string) $completionStatus->id)
            ->call('setCompletionStatus')
            ->assertHasNoErrors()
            ->call('deleteStatus', $completionStatus->id)
            ->assertSee('Choose another completion column before deleting this one.');

        $this->assertDatabaseHas('project_statuses', ['id' => $completionStatus->id, 'is_complete' => true]);
    }

    public function test_status_with_tasks_cannot_be_deleted_and_empty_status_can(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $occupiedStatus = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $emptyStatus = $project->statuses()->create(['name' => 'Later', 'position' => 20]);
        $this->createTask($user, $project, ['project_status_id' => $occupiedStatus->id]);

        $component = Livewire::actingAs($user)->test('projects.form', ['project' => $project->id]);
        $component->call('deleteStatus', $occupiedStatus->id)
            ->assertSee('Move this column’s tasks to another column before deleting it.');
        $this->assertDatabaseHas('project_statuses', ['id' => $occupiedStatus->id]);

        $component->call('deleteStatus', $emptyStatus->id);
        $this->assertDatabaseMissing('project_statuses', ['id' => $emptyStatus->id]);
    }

    public function test_task_list_search_and_filters_are_owner_scoped(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Write launch notes']);

        $otherUser = User::factory()->create();
        $otherProject = $this->createProject($otherUser);
        $this->createTask($otherUser, $otherProject, ['title' => 'Write private launch notes']);

        Livewire::actingAs($user)
            ->test('tasks.index')
            ->set('search', 'launch')
            ->set('priorityFilter', $task->priority)
            ->assertSee('Write launch notes')
            ->assertDontSee('Write private launch notes');
    }

    public function test_task_list_filters_by_type(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $this->createTask($user, $project, ['title' => 'Broken login', 'type' => 'bug']);
        $this->createTask($user, $project, ['title' => 'Polish dashboard', 'type' => 'feature']);

        Livewire::actingAs($user)
            ->test('tasks.index')
            ->set('typeFilter', 'bug')
            ->assertSee('Broken login')
            ->assertDontSee('Polish dashboard');
    }

    public function test_my_tasks_shows_only_accessible_tasks_assigned_to_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $teammate = User::factory()->create();
        $project = $this->createProject($user);
        $this->createTask($user, $project, ['title' => 'My assigned task', 'assignee_id' => $user->id]);
        $this->createTask($user, $project, ['title' => 'Teammate assigned task', 'assignee_id' => $teammate->id]);
        $this->createTask($user, $project, ['title' => 'My review task', 'assignee_id' => $teammate->id, 'reviewer_id' => $user->id]);

        $privateOwner = User::factory()->create();
        $privateProject = $this->createProject($privateOwner, 'Private work');
        $this->createTask($privateOwner, $privateProject, ['title' => 'Inaccessible assigned task', 'assignee_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('tasks.mine'))
            ->assertOk()
            ->assertSee('My assigned task')
            ->assertSee('My review task')
            ->assertDontSee('Teammate assigned task')
            ->assertDontSee('Inaccessible assigned task');
    }

    public function test_my_tasks_can_focus_on_review_requests_and_shows_a_work_summary(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $verify = $project->statuses()->where('name', 'Verify')->firstOrFail();
        $todo = $project->statuses()->where('name', 'To Do')->firstOrFail();
        $this->createTask($user, $project, [
            'title' => 'Assigned to me',
            'assignee_id' => $user->id,
            'due_date' => today()->addDays(2),
        ]);
        $this->createTask($user, $project, [
            'title' => 'Waiting for my review',
            'project_status_id' => $verify->id,
            'reviewer_id' => $user->id,
        ]);
        $this->createTask($user, $project, [
            'title' => 'Not my work',
            'project_status_id' => $todo->id,
        ]);

        $this->actingAs($user)
            ->get(route('tasks.mine', ['workScope' => 'reviewing']))
            ->assertOk()
            ->assertSee('My Tasks')
            ->assertSee('Review requests')
            ->assertSee('Assigned to me')
            ->assertSee('Waiting for my review')
            ->assertDontSee('Not my work');
    }

    public function test_task_list_opens_create_and_detail_modals(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project, ['title' => 'Open from my work list']);

        $component = Livewire::actingAs($user)->test('tasks.index')
            ->call('openCreateTask')
            ->assertSet('taskModalOpen', true)
            ->assertSet('modalProjectId', $project->id)
            ->assertSet('modalTaskId', null)
            ->call('closeTaskModal')
            ->call('openTask', $task->id)
            ->assertSet('taskModalOpen', true)
            ->assertSet('modalProjectId', $project->id)
            ->assertSet('modalTaskId', $task->id);

        $component->call('closeTaskModal')->assertSet('taskModalOpen', false);
    }

    public function test_database_rejects_unsupported_task_types(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);

        $this->expectException(QueryException::class);

        $this->createTask($user, $project, ['type' => 'incident']);
    }

    public function test_database_rejects_story_point_values_outside_the_supported_scale(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);

        $this->expectException(QueryException::class);

        $this->createTask($user, $project, ['story_points' => 4]);
    }

    public function test_dashboard_counts_tasks_from_workflow_status_and_due_date(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $completeStatus = $project->statuses()->where('is_complete', true)->firstOrFail();
        $activeStatus = $project->statuses()->where('is_complete', false)->firstOrFail();

        $this->createTask($user, $project, [
            'title' => 'Finished work',
            'project_status_id' => $completeStatus->id,
            'due_date' => today()->subDays(2),
        ]);
        $this->createTask($user, $project, [
            'title' => 'Past due work',
            'project_status_id' => $activeStatus->id,
            'due_date' => today()->subDay(),
        ]);
        $this->createTask($user, $project, [
            'title' => 'Upcoming work',
            'project_status_id' => $activeStatus->id,
            'due_date' => today()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('3')
            ->assertSee('Upcoming work')
            ->assertSee('Upcoming tasks');
    }

    public function test_project_deletion_removes_its_tasks_without_affecting_other_projects(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $task = $this->createTask($user, $project);

        Livewire::actingAs($user)
            ->test('projects.index')
            ->call('deleteProject', $project->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('project_statuses', ['project_id' => $project->id]);
    }

    public function test_deleting_the_only_task_on_a_later_page_returns_to_a_populated_page(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user);
        $tasks = collect(range(1, 16))->map(function (int $number) use ($user, $project) {
            $task = $this->createTask($user, $project, ['title' => "Task {$number}"]);
            $task->forceFill(['updated_at' => now()->addSeconds($number)])->save();

            return $task;
        });
        $oldestTask = $tasks->first();

        Livewire::actingAs($user)
            ->test('tasks.index')
            ->call('setPage', 2)
            ->assertSee($oldestTask->title)
            ->call('deleteTask', $oldestTask->id)
            ->assertSee('Task 16')
            ->assertDontSee('No tasks found');
    }

    private function createProject(User $owner, string $name = 'Phase 4 project'): Project
    {
        $project = $owner->projects()->create(['name' => $name]);
        $project->statuses()->createMany(Project::DEFAULT_WORKFLOW_STATUSES);

        return $project;
    }

    /** @param array<string, mixed> $attributes */
    private function createTask(User $owner, Project $project, array $attributes = []): Task
    {
        $statusId = $attributes['project_status_id'] ?? $project->statuses()->where('name', 'To Do')->value('id');

        return $project->tasks()->create(array_merge([
            'user_id' => $owner->id,
            'project_status_id' => $statusId,
            'title' => 'Test task',
            'priority' => 'medium',
            'due_date' => null,
        ], $attributes));
    }
}
