<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationNudge extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $senderName, public int $conversationId) {}

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->senderName.' nudged you on Gchat')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->senderName.' would like your attention in your private conversation.')
            ->action('Open conversation', route('chat', ['conversation' => $this->conversationId]));
    }
}
