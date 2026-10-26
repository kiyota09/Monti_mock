<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public ?string $companyName,
        public string $registrationUrl,
        public ?string $senderName = null,
        public ?string $expiresAt = null,
    ) {
    }

    public function build()
    {
        return $this->subject('Complete your Monti Textiles partner registration')
            ->view('emails.registration-invite');
    }
}
