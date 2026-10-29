<?php

namespace App\Models;

use Database\Factories\CycleSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleSnapshot extends Model
{
    /** @use HasFactory<CycleSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'cycle_id',
        'snapshot_date',
        'snapshot_type',
        'total_tasks',
        'completed_tasks',
        'total_story_points',
        'completed_story_points',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_tasks' => 'integer',
            'completed_tasks' => 'integer',
            'total_story_points' => 'integer',
            'completed_story_points' => 'integer',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }
}
