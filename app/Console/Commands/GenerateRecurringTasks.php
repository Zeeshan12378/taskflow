<?php

namespace App\Console\Commands;

use App\Models\RecurringTaskSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tasks:generate-recurring')]
#[Description('Generate tasks for due weekly project schedules')]
class GenerateRecurringTasks extends Command
{
    public function handle(): int
    {
        $today = today();
        $tasksCreated = 0;

        RecurringTaskSchedule::query()
            ->where('is_active', true)
            ->whereDate('next_run_on', '<=', $today)
            ->chunkById(100, function ($schedules) use ($today, &$tasksCreated): void {
                foreach ($schedules as $schedule) {
                    DB::transaction(function () use ($schedule, $today, &$tasksCreated): void {
                        $lockedSchedule = RecurringTaskSchedule::query()
                            ->whereKey($schedule->id)
                            ->where('is_active', true)
                            ->lockForUpdate()
                            ->first();

                        if ($lockedSchedule === null || $lockedSchedule->next_run_on->isAfter($today)) {
                            return;
                        }

                        $project = $lockedSchedule->project;
                        $status = $project->statuses()->firstOrFail();
                        $occurrenceDate = $lockedSchedule->next_run_on->copy();

                        while ($occurrenceDate->lte($today)) {
                            $project->tasks()->create([
                                'user_id' => $lockedSchedule->created_by,
                                'project_status_id' => $status->id,
                                'title' => $lockedSchedule->title,
                                'description' => $lockedSchedule->description,
                                'type' => $lockedSchedule->type,
                                'priority' => $lockedSchedule->priority,
                                'story_points' => $lockedSchedule->story_points,
                                'checklist' => array_map(
                                    fn (array $item): array => ['text' => $item['text'], 'completed' => false],
                                    $lockedSchedule->checklist ?? [],
                                ),
                                'due_date' => $occurrenceDate->toDateString(),
                            ]);

                            $tasksCreated++;
                            $occurrenceDate->addWeek();
                        }

                        $lockedSchedule->update(['next_run_on' => $occurrenceDate->toDateString()]);
                    });
                }
            });

        $this->info("Generated {$tasksCreated} recurring tasks.");

        return self::SUCCESS;
    }
}
