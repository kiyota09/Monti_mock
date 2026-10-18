<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivityScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public ?string $companyName,
        public ?string $opportunityTitle,
        public string $type,
        public ?string $summary,
        public ?string $dueAt,
        public ?string $meetingLink,
        public ?string $notes,
        public ?string $ownerName,
    ) {
    }

    public function build()
    {
        $label = ucfirst($this->type);

        return $this->subject("Monti Textiles — {$label} Scheduled: " . ($this->summary ?: 'Your Appointment'))
            ->view('emails.activity-scheduled');
    }
}
