<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ModelEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $details) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) $this->details['subject']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.model-event-notification', with: ['details' => $this->details]);
    }
}
