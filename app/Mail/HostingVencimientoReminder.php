<?php

namespace App\Mail;

use App\Models\Hosting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HostingVencimientoReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Hosting $hosting,
        public int $diasRestantes,
    ) {}

    public function envelope(): Envelope
    {
        $subjectPrefix = $this->diasRestantes === 0
            ? 'Hosting vence hoy'
            : "Recordatorio de renovacion de hosting (Vence en: {$this->diasRestantes} dias)";

        return new Envelope(
            from: new Address(
                config('mail.hosting_from.address', config('mail.from.address')),
                config('mail.hosting_from.name', 'HOSTING - WILLATEC S.A.C'),
            ),
            subject: "NO RESPONDER | {$subjectPrefix} - {$this->hosting->dominio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.hostings.vencimiento',
        );
    }
}
