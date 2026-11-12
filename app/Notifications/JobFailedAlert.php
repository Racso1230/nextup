<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Short ops email for a failed queue job. Carries no payload, only names and a time. */
class JobFailedAlert extends Notification
{
    public function __construct(
        public readonly string $jobName,
        public readonly string $exceptionClass,
        public readonly string $failedAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Queue job failed: '.$this->jobName)
            ->line('Job: '.$this->jobName)
            ->line('Exception: '.$this->exceptionClass)
            ->line('Time: '.$this->failedAt)
            ->line('Check the application logs for details.');
    }
}
