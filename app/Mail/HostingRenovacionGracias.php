<?php

namespace App\Mail;

use App\Models\Hosting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HostingRenovacionGracias extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Hosting $hosting,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.hosting_from.address', config('mail.from.address')),
                config('mail.hosting_from.name', 'HOSTING - WILLATEC S.A.C'),
            ),
            subject: "Gracias por renovar su hosting - {$this->hosting->dominio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hostings.renovacion-gracias',
        );
    }
}
