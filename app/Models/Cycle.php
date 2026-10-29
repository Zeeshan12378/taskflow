<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cycle extends Model
{
    protected $fillable = ['team_id', 'name', 'goal', 'start_date', 'end_date', 'status', 'started_at', 'completed_at'];

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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
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
