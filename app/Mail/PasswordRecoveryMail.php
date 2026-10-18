<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $newPassword,
        public string $portal,
        public string $loginUrl,
    ) {
    }

    public function build()
    {
        return $this->subject('Your Monti Textile password has been reset')
            ->view('emails.password-recovery');
    }
}
