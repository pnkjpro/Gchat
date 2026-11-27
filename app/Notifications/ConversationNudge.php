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
            ->subject('Take a little break with espace')
            ->view(['html' => 'emails.nudge', 'text' => 'emails.nudge-text'], [
                'spaceUrl' => route('chat', ['conversation' => $this->conversationId]),
            ]);
    }
}
