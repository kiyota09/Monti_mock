<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivityCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public ?string $companyName,
        public ?string $opportunityTitle,
        public string $type,
        public ?string $summary,
        public ?string $wasAt,
        public ?string $meetingLink,
        public string $reason,
        public ?string $ownerName,
    ) {
    }

    public function build()
    {
        $label = ucfirst($this->type);

        return $this->subject("Monti Textiles — {$label} Cancelled: " . ($this->summary ?: 'Schedule change'))
            ->view('emails.activity-cancelled');
    }
}
