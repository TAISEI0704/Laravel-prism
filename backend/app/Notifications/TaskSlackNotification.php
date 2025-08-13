<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Slack\SlackChannel;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;

class TaskSlackNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private array $tasks) {}

    /** 利用チャンネルを新名前空間に統一 */
    public function via(object $notifiable): array
    {
        return [SlackChannel::class];
    }

    /** Slack への Block Kit メッセージを返す */
    public function toSlack(object $notifiable): SlackMessage
    {
        $message = (new SlackMessage)
            ->text('*【議事録から新しいタスクが抽出されました】*');

        foreach ($this->tasks as $task) {
            $message
                ->sectionBlock(function (SectionBlock $block) use ($task) {
                    $block->field("*タスク*\n{$task['description']}")->markdown();
                    $block->field("*担当*\n{$task['assignee']}")->markdown();
                    $block->field("*期限*\n".($task['due_at'] ?? '未設定'))->markdown();
                    $block->field("*優先度*\n".($task['priority'] ?? '3'))->markdown();
                })
                ->dividerBlock();
        }

        return $message;
    }
}
