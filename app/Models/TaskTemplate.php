<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplate extends Model
{
    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'title',
        'description',
        'type',
        'priority',
        'story_points',
        'checklist',
    ];

    protected function casts(): array
    {
        return [
            'story_points' => 'integer',
            'checklist' => 'array',
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
