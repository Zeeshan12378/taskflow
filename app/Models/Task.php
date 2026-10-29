<?php

namespace App\Models;

use App\Notifications\TaskNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Task $task): void {
            $task->recordActivity('created', ['title' => ['to' => $task->title]]);

            if ($task->assignee_id !== null) {
                $task->notifyParticipants('assigned', [$task->assignee_id]);
            }

            if ($task->reviewer_id !== null) {
                $task->notifyParticipants('review_assigned', [$task->reviewer_id]);
            }
        });

        static::updated(function (Task $task): void {
            $previous = $task->getPrevious();
            $changes = $task->getChanges();
            $tracked = ['title', 'description', 'project_status_id', 'priority', 'type', 'due_date', 'assignee_id', 'reviewer_id', 'cycle_id', 'story_points', 'blocked_reason', 'github_url', 'checklist'];
            $details = [];

            foreach ($tracked as $field) {
                if (! array_key_exists($field, $changes)) {
                    continue;
                }

                $from = $task->activityValue($field, $previous[$field] ?? null);
                $to = $task->activityValue($field, $changes[$field]);

                if ($field === 'checklist' && $from === $to) {
                    continue;
                }

                $details[$field] = [
                    'from' => $from,
                    'to' => $to,
                ];
            }

            if ($details !== []) {
                $task->recordActivity('updated', $details);

                if (array_key_exists('assignee_id', $details) && $task->assignee_id !== null) {
                    $task->notifyParticipants('assigned', [$task->assignee_id]);
                }

                if (array_key_exists('reviewer_id', $details) && $task->reviewer_id !== null) {
                    $task->notifyParticipants('review_assigned', [$task->reviewer_id]);
                }

                if (array_intersect(array_keys($details), ['title', 'project_status_id', 'priority', 'type', 'due_date', 'cycle_id', 'story_points']) !== []) {
                    $task->notifyParticipants('updated', [$task->user_id, $task->assignee_id]);
                }
            }
        });
    }

    public const TYPES = ['task', 'bug', 'feature'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'project_id',
        'user_id',
        'assignee_id',
        'reviewer_id',
        'cycle_id',
        'story_points',
        'project_status_id',
        'type',
        'blocked_reason',
        'title',
        'description',
        'priority',
        'due_date',
        'github_url',
        'checklist',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'story_points' => 'integer',
            'checklist' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function recordActivity(string $action, array $details = []): void
    {
        $this->activities()->create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'details' => $details,
        ]);
    }

    /** @param array<int, int|null> $userIds */
    public function notifyParticipants(string $kind, array $userIds): void
    {
        $actor = Auth::user();

        if ($actor === null) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', array_filter(array_unique($userIds)))
            ->whereKeyNot($actor->id)
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new TaskNotification(
                kind: $kind,
                taskId: $this->id,
                projectId: $this->project_id,
                taskTitle: $this->title,
                projectName: $this->project()->value('name') ?? 'Project',
                actorId: $actor->id,
                actorName: $actor->name,
            ));
        }
    }

    private function activityValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'checklist' => $this->checklistSummary($value),
            'project_status_id' => ProjectStatus::query()->whereKey($value)->value('name') ?? 'Unknown status',
            'assignee_id' => User::query()->whereKey($value)->value('name') ?? 'Unknown person',
            'reviewer_id' => User::query()->whereKey($value)->value('name') ?? 'Unknown person',
            'cycle_id' => Cycle::query()->whereKey($value)->value('name') ?? 'Unknown cycle',
            'priority', 'type' => ucfirst((string) $value),
            'story_points' => (string) $value.' points',
            'due_date' => (string) $value,
            default => (string) $value,
        };
    }

    private function checklistSummary(mixed $value): string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return '0 checklist items';
        }

        $completedItems = count(array_filter(
            $value,
            fn (mixed $item): bool => is_array($item) && (bool) ($item['completed'] ?? false),
        ));

        return $completedItems.' of '.count($value).' checklist items complete';
    }

    public function isBlocked(): bool
    {
        return strtolower(trim((string) $this->status?->name)) === 'blocked';
    }
}
