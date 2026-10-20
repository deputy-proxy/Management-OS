<?php

namespace App\Mail;

use App\Models\EmployeeEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmployeeEvaluationInitiationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmployeeEvaluation $evaluation) {}

    public function build(): self
    {
        return $this
            ->subject('Annual employee evaluation started')
            ->view('emails.employee-evaluations.initiation');
    }
}
