<?php

namespace App\Mail;

use App\Models\Identity\Invitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $url,
        public readonly string $schoolName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You have been invited to {$this->schoolName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invitation');
    }
}
