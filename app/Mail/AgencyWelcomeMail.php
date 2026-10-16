<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AgencyWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $userName;
    public string $agencyName;
    public string $loginEmail;
    public string $tempPassword;
    public string $portalUrl;
    public bool $isReset;

    public function __construct(
        string $userName,
        string $agencyName,
        string $loginEmail,
        string $tempPassword,
        string $portalUrl,
        bool $isReset = false
    ) {
        $this->userName = $userName;
        $this->agencyName = $agencyName;
        $this->loginEmail = $loginEmail;
        $this->tempPassword = $tempPassword;
        $this->portalUrl = $portalUrl;
        $this->isReset = $isReset;
    }

    public function build()
    {
        $subject = $this->isReset
            ? "Your {$this->agencyName} portal password was reset"
            : "Your {$this->agencyName} portal login was created";

        return $this->subject($subject)
            ->view('emails.agency-welcome');
    }
}
