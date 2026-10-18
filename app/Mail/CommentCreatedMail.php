<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommentCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $details)
    {
    }

    public function build(): self
    {
        return $this->subject($this->details['subject'])
            ->view('emails.comment-created')
            ->with(['details' => $this->details]);
    }
}
