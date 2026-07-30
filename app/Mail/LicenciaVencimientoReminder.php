<?php

namespace App\Mail;

use App\Models\Licencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
            : "Recordatorio de renovacion de licencia ({$this->diasRestantes} dias)";

        return new Envelope(
            subject: "{$subjectPrefix} - {$this->licencia->producto}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.licencias.vencimiento',
        );
    }
}
