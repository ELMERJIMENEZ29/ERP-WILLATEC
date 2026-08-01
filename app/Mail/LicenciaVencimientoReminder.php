<?php

namespace App\Mail;

use App\Models\Licencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LicenciaVencimientoReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Licencia $licencia,
        public int $diasRestantes,
    ) {}

    public function envelope(): Envelope
    {
        $subjectPrefix = $this->diasRestantes === 0
            ? 'Licencia vence hoy'
            : "Recordatorio de renovación de licencia (Vence en: {$this->diasRestantes} días)";

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                'LICENCIAS - WILLATEC S.A.C',
            ),
            subject: "NO RESPONDER | {$subjectPrefix} - {$this->licencia->producto}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.licencias.vencimiento',
        );
    }
}
