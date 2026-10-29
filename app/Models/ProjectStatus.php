<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStatus extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'position',
        'is_complete',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_complete' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_status_id');
    }

    public function requiresReviewer(): bool
    {
        return in_array(strtolower(trim($this->name)), ['verify', 'in review', 'qa', 'quality assurance'], true);
    }

    public function accentHex(): string
    {
        return match ($this->color) {
            'blue' => '#60A5FA',
            'emerald' => '#34D399',
            'amber' => '#FBBF24',
            'red' => '#F87171',
            'gray' => '#9CA3AF',
            'violet' => '#A78BFA',
            'rose' => '#FB7185',
            default => '#94A3B8',
        };
    }
}
