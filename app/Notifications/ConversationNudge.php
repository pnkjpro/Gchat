<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConversationNudge extends Notification
{
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
