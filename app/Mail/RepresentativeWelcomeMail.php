<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RepresentativeWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $representativeName,
        public string $companyName,
        public string $email,
        public string $password,
        public string $portalUrl,
    ) {
    }

    public function build()
    {
        return $this->subject('Welcome to Monti Textiles — Your partner portal access is ready')
            ->view('emails.representative-welcome');
    }
}
