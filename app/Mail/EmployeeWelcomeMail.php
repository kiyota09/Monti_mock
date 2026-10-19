<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmployeeWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $employeeName;
    public ?string $employeeId;
    public ?string $position;
    public ?string $department;
    public string $companyEmail;
    public string $tempPassword;
    public ?string $trainingStartDate;
    public string $portalUrl;

    public function __construct(
        string $employeeName,
        ?string $employeeId,
        ?string $position,
        ?string $department,
        string $companyEmail,
        string $tempPassword,
        ?string $trainingStartDate,
        string $portalUrl
    ) {
        $this->employeeName = $employeeName;
        $this->employeeId = $employeeId;
        $this->position = $position;
        $this->department = $department;
        $this->companyEmail = $companyEmail;
        $this->tempPassword = $tempPassword;
        $this->trainingStartDate = $trainingStartDate;
        $this->portalUrl = $portalUrl;
    }

    public function build()
    {
        $from = config('mail.from.address');

        return $this->subject("Welcome to Monti Corporation, {$this->employeeName}!")
            ->when($from, fn ($mail) => $mail->replyTo($from, config('mail.from.name')))
            ->view('emails.employee-welcome')
            ->text('emails.employee-welcome-text');
    }
}
