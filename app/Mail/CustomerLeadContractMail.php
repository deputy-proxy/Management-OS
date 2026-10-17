<?php

namespace App\Mail;

use App\Models\CustomerLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerLeadContractMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CustomerLead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contract for '.$this->lead->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-lead-contract',
            with: ['leadName' => $this->lead->full_name],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->lead->contract_document)
                ->as('contract.pdf')
                ->withMime('application/pdf'),
        ];
    }
}