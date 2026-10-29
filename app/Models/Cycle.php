<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cycle extends Model
{
    protected $fillable = ['team_id', 'project_id', 'name', 'goal', 'start_date', 'end_date', 'status', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CycleSnapshot::class)->orderBy('snapshot_date');
    }

    public function recordSnapshot(CarbonInterface $date, string $type): CycleSnapshot
    {
        $metrics = $this->tasks()
            ->join('project_statuses', 'tasks.project_status_id', '=', 'project_statuses.id')
            ->selectRaw('COUNT(tasks.id) AS total_tasks')
            ->selectRaw('SUM(CASE WHEN project_statuses.is_complete = 1 THEN 1 ELSE 0 END) AS completed_tasks')
            ->selectRaw('COALESCE(SUM(tasks.story_points), 0) AS total_story_points')
            ->selectRaw('COALESCE(SUM(CASE WHEN project_statuses.is_complete = 1 THEN tasks.story_points ELSE 0 END), 0) AS completed_story_points')
            ->first();

        CycleSnapshot::query()->insertOrIgnore([[
            'cycle_id' => $this->id,
            'snapshot_date' => $date->toDateString(),
            'snapshot_type' => $type,
            'total_tasks' => (int) $metrics->total_tasks,
            'completed_tasks' => (int) $metrics->completed_tasks,
            'total_story_points' => (int) $metrics->total_story_points,
            'completed_story_points' => (int) $metrics->completed_story_points,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        return $this->snapshots()
            ->whereDate('snapshot_date', $date->toDateString())
            ->where('snapshot_type', $type)
            ->firstOrFail();
    }

    public function state(): string
    {
        if ($this->status === 'completed') {
            return 'completed';
        }

        if ($this->status === 'active') {
            return 'current';
        }

        if ($this->start_date->isFuture()) {
            return 'upcoming';
        }

        if ($this->end_date->lt(today())) {
            return 'past';
        }

        return 'current';
    }
}
