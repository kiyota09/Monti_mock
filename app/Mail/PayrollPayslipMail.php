<?php

namespace App\Mail;

use App\Models\Hrm\Payroll;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayrollPayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payroll $payroll)
    {
    }

    public function build(): self
    {
        return $this->subject('Payslip for '.$this->payroll->period)
            ->view('emails.payroll-payslip');
    }
}
