<?php

namespace App\Mail;

use App\Models\Ekyc\OwnerEkyc;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EkycDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OwnerEkyc $ekyc,
        public string $ownerName,
        public string $decision
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->decision === 'approved' 
            ? 'eKYC Application Approved' 
            : 'eKYC Application Update';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ekyc-decision',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
