<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Channels\SlackWebhookChannel;

class TaskSlackNotification extends Notification
{
    use Queueable;

    public function __construct(private array $tasks)
    {
    }

    public function via($notifiable): array
    {
        return [SlackWebhookChannel::class];
    }

    public function toSlack($notifiable): SlackMessage|array
    {
        $message = new SlackMessage;
        $message->content("*【議事録から新しいタスクが抽出されました】*");

        foreach ($this->tasks as $task) {
            $message->attachment(function ($attachment) use ($task) {
                $attachment
                    ->title($task['description'])
                    ->fields([
                        '担当者' => $task['assignee'],
                        '期限' => $task['due_at'] ? $task['due_at'] : '未設定',
                        '優先度' => $task['priority'] ?? '3'
                    ]);
            });
        }

        return $message;
    }
}