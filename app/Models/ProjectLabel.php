<?php

namespace App\Models;

use Database\Factories\ProjectLabelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectLabel extends Model
{
    /** @use HasFactory<ProjectLabelFactory> */
    use HasFactory;

    public const COLORS = ['slate', 'blue', 'emerald', 'amber', 'red', 'violet', 'rose'];

    protected $fillable = [
        'project_id',
        'name',
        'color',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)->withTimestamps();
    }
}
