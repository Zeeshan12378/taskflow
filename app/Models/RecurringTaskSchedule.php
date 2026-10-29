<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTaskSchedule extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'template_name',
        'title',
        'description',
        'type',
        'priority',
        'story_points',
        'checklist',
        'weekday',
        'next_run_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'story_points' => 'integer',
            'checklist' => 'array',
            'weekday' => 'integer',
            'next_run_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
