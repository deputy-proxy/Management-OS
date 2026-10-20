<?php

namespace App\Mail;

use App\Models\EmployeeEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmployeeEvaluationSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmployeeEvaluation $evaluation, public string $token) {}

    public function build(): self
    {
        return $this
            ->subject('Your employee evaluation is ready for acknowledgment')
            ->view('emails.employee-evaluations.submitted')
            ->with([
                'acknowledgmentUrl' => route('employee-evaluations.acknowledge.show', [
                    'evaluation' => $this->evaluation->id,
                    'token' => $this->token,
                ]),
            ]);
    }
}
