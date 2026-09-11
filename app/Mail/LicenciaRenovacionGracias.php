<?php

namespace App\Mail;

use App\Models\Licencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LicenciaRenovacionGracias extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Licencia $licencia,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                'LICENCIAS - WILLATEC S.A.C',
            ),
            subject: "Gracias por renovar su licencia - {$this->licencia->producto}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.licencias.renovacion-gracias',
        );
    }
}
