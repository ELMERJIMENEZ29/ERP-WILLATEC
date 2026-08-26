<?php

namespace App\Console\Commands;

use App\Mail\HostingVencimientoReminder;
use App\Models\Hosting;
use App\Models\HostingAlertaEnviada;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarAlertasHostings extends Command
{
    protected $signature = 'hostings:enviar-alertas
        {--dry-run : Simula el proceso sin enviar correos}
        {--today= : Fecha de referencia para pruebas en formato YYYY-MM-DD}';

    protected $description = 'Envia alertas automaticas de vencimiento de hosting';

    public function handle(): int
    {
        $todayOption = $this->option('today');
        $today = $todayOption
            ? Carbon::createFromFormat('Y-m-d', (string) $todayOption)->startOfDay()
            : Carbon::today();
        $sent = 0;
        $skipped = 0;
        $dryRun = (bool) $this->option('dry-run');
        $internalRecipient = config('mail.hosting_alert_internal_recipient');

        Hosting::query()
            ->whereDate('fecha_renovacion', '>=', $today)
            ->whereDate('fecha_renovacion', '<=', $today->copy()->addDays(90))
            ->orderBy('fecha_renovacion')
            ->chunkById(100, function ($hostings) use ($today, $dryRun, $internalRecipient, &$sent, &$skipped): void {
                foreach ($hostings as $hosting) {
                    $fechaRenovacion = Carbon::parse($hosting->fecha_renovacion)->startOfDay();
                    $diasRestantes = (int) $today->diffInDays($fechaRenovacion, false);

                    if (! in_array($diasRestantes, $this->alertDaysFor($hosting), true)) {
                        $skipped++;

                        continue;
                    }

                    $alreadySent = HostingAlertaEnviada::query()
                        ->where('hosting_id', $hosting->id)
                        ->where('dias_antes', $diasRestantes)
                        ->exists();

                    if ($alreadySent) {
                        $skipped++;

                        continue;
                    }

                    $customerEmail = $hosting->correo_hosting ?: null;

                    if (! $customerEmail) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $message = Mail::mailer(config('mail.hosting_mailer', 'hosting'))
                            ->to($customerEmail);

                        if ($internalRecipient && strtolower($customerEmail) !== strtolower((string) $internalRecipient)) {
                            $message->bcc($internalRecipient);
                        }

                        $message->send(new HostingVencimientoReminder($hosting, $diasRestantes));

                        HostingAlertaEnviada::create([
                            'hosting_id' => $hosting->id,
                            'dias_antes' => $diasRestantes,
                            'correo_destino' => $customerEmail,
                            'correo_copia' => $internalRecipient,
                            'sent_at' => now(),
                        ]);
                    }

                    $sent++;
                }
            });

        $mode = $dryRun ? 'simulados' : 'enviados';

        $this->info("Recordatorios {$mode}: {$sent}");
        $this->info("Hostings omitidos: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return array<int>
     */
    private function alertDaysFor(Hosting $hosting): array
    {
        if ($hosting->suscripcion === 'ANUAL') {
            return [90, 60, 30, 15, 3, 2, 1, 0];
        }

        return [7, 4, 3, 2, 1, 0];
    }
}
