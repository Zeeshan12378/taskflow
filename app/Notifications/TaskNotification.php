<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public int $taskId,
        public int $projectId,
        public string $taskTitle,
        public string $projectName,
        public int $actorId,
        public string $actorName,
    ) {}

    /** @return array<int, string> */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase($notifiable): array
    {
        $message = match ($this->kind) {
            'assigned' => 'assigned a task to you',
            'review_assigned' => 'requested you to review a task',
            'commented' => 'commented on a task you are involved in',
            default => 'updated a task you are involved in',
        };

        return [
            'kind' => $this->kind,
            'task_id' => $this->taskId,
            'project_id' => $this->projectId,
            'task_title' => $this->taskTitle,
            'project_name' => $this->projectName,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
            'message' => $message,
        ];
    }
}
