<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicantOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public int $expiresMinutes;

    public function __construct(string $code, int $expiresMinutes = 10)
    {
        $this->code = $code;
        $this->expiresMinutes = $expiresMinutes;
    }

    public function build()
    {
        return $this->subject("Your Monti verification code: {$this->code}")
            ->view('emails.applicant-otp');
    }
}
