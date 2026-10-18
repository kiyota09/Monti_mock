<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MeetingRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public ?string $companyName,
        public ?string $opportunityTitle,
        public string $type,
        public ?string $summary,
        public ?string $proposedAt,
        public ?string $meetingLink,
        public ?string $notes,
        public ?string $ownerName,
        public string $respondUrl,
    ) {
    }

    public function build()
    {
        $label = ucfirst($this->type);

        return $this->subject("Monti Textiles — {$label} Request: " . ($this->summary ?: 'Please confirm your schedule'))
            ->view('emails.meeting-request');
    }
}
