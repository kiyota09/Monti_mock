<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CrmReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $subjectLine,
        public string $bodyText,
        public ?string $senderName = null,
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.crm-reply');
    }
}
