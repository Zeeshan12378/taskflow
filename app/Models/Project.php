<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    public const DEFAULT_WORKFLOW_STATUSES = [
        ['name' => 'To Do', 'position' => 0, 'is_complete' => false, 'color' => 'slate'],
        ['name' => 'In Progress', 'position' => 1, 'is_complete' => false, 'color' => 'blue'],
        ['name' => 'Complete', 'position' => 2, 'is_complete' => true, 'color' => 'emerald'],
        ['name' => 'Verify', 'position' => 3, 'is_complete' => false, 'color' => 'amber'],
        ['name' => 'Closed', 'position' => 4, 'is_complete' => true, 'color' => 'gray'],
        ['name' => 'Blocked', 'position' => 5, 'is_complete' => false, 'color' => 'red'],
    ];

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'team_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(ProjectStatus::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class)->orderByDesc('start_date');
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class)->orderBy('name');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereHas('team.members', fn (Builder $members) => $members->whereKey($user->id))
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->team_id && $user->teams()->whereKey($this->team_id)->exists()) {
            return true;
        }

        return $this->members()->whereKey($user->id)->exists();
    }

    public function canManage(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->team && $this->team->isManager($user)) {
            return true;
        }

        return $this->members()->whereKey($user->id)->wherePivotIn('role', ['admin'])->exists();
    }

    public function accessibleMembers()
    {
        $this->loadMissing(['user', 'team.members', 'members']);

        $users = collect([$this->user]);

        if ($this->team) {
            $users = $users->concat($this->team->members);
        }

        return $users->concat($this->members)->unique('id')->sortBy('name')->values();
    }

    public function accessibleMemberIds(): array
    {
        return $this->accessibleMembers()->pluck('id')->all();
    }
}
