<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Invitation $invitation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = $this->invitation->company;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject("You've been invited to join {$company->name}")
            ->greeting('Hello!')
            ->line("{$inviter->name} has invited you to join **{$company->name}** as a **{$this->invitation->role_name}**.")
            ->action('Accept Invitation', $this->invitation->acceptUrl())
            ->line("This invitation will expire on {$this->invitation->expires_at->format('F j, Y')}.")
            ->line('If you did not expect this invitation, you can ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'company_id' => $this->invitation->company_id,
            'company_name' => $this->invitation->company->name,
            'role' => $this->invitation->role_name,
        ];
    }
}
