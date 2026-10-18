<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public ?string $contactPerson,
        public string $email,
        public string $password,
        public string $portalUrl,
    ) {
    }

    public function build()
    {
        return $this->subject("Welcome to Monti Textiles, {$this->companyName}!")
            ->view('emails.client-welcome');
    }
}
