<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    protected $fillable = ['task_id', 'actor_id', 'action', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function summary(): string
    {
        if ($this->action === 'created') {
            return 'created this task';
        }

        if ($this->action === 'commented') {
            return 'left a comment';
        }

        $labels = [
            'title' => 'title',
            'description' => 'description',
            'project_status_id' => 'status',
            'priority' => 'priority',
            'type' => 'type',
            'due_date' => 'due date',
            'assignee_id' => 'assignee',
            'reviewer_id' => 'reviewer',
            'cycle_id' => 'cycle',
            'story_points' => 'estimate',
            'blocked_reason' => 'blocked reason',
            'github_url' => 'GitHub reference',
            'checklist' => 'checklist',
            'labels' => 'labels',
        ];

        return collect($this->details ?? [])
            ->map(function (array $change, string $field) use ($labels): string {
                $label = $labels[$field] ?? $field;
                $from = $change['from'] ?? null;
                $to = $change['to'] ?? null;

                if ($from === null) {
                    return "set {$label} to ".($to ?? 'none');
                }

                if ($to === null) {
                    return "cleared {$label}";
                }

                return "changed {$label} from {$from} to {$to}";
            })
            ->implode('; ');
    }
}
