<?php

namespace App\Console\Commands;

use App\Mail\LicenciaVencimientoReminder;
use App\Models\Licencia;
use App\Models\LicenciaAlertaEnviada;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EnviarAlertasLicencias extends Command
{
    protected $signature = 'licencias:enviar-alertas
        {--dry-run : Simula el proceso sin enviar correos}
        {--today= : Fecha de referencia para pruebas en formato YYYY-MM-DD}';

    protected $description = 'Envia alertas automaticas de vencimiento de licencias';

    private const INTERNAL_RECIPIENT = 'luis.lopez@willatec.com';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $todayOption = $this->option('today');
        $today = $todayOption
            ? Carbon::createFromFormat('Y-m-d', (string) $todayOption)->startOfDay()
            : Carbon::today();
        $sent = 0;
        $skipped = 0;
        $dryRun = (bool) $this->option('dry-run');

        Licencia::query()
            ->whereDate('fecha_renovacion', '>=', $today)
            ->whereDate('fecha_renovacion', '<=', $today->copy()->addDays(90))
            ->orderBy('fecha_renovacion')
            ->chunkById(100, function ($licencias) use ($today, $dryRun, &$sent, &$skipped): void {
                foreach ($licencias as $licencia) {
                    $fechaRenovacion = Carbon::parse($licencia->fecha_renovacion)->startOfDay();
                    $diasRestantes = (int) $today->diffInDays($fechaRenovacion, false);

                    if (! in_array($diasRestantes, $this->alertDaysFor($licencia), true)) {
                        $skipped++;

                        continue;
                    }

                    $alreadySent = LicenciaAlertaEnviada::query()
                        ->where('licencia_id', $licencia->id)
                        ->where('dias_antes', $diasRestantes)
                        ->exists();

                    if ($alreadySent) {
                        $skipped++;

                        continue;
                    }

                    $customerEmail = $licencia->correo_licencia ?: null;
                    $recipients = collect([$customerEmail, self::INTERNAL_RECIPIENT])
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if (! $dryRun) {
                        Mail::to($recipients)->send(
                            new LicenciaVencimientoReminder($licencia, $diasRestantes)
                        );

                        LicenciaAlertaEnviada::create([
                            'licencia_id' => $licencia->id,
                            'dias_antes' => $diasRestantes,
                            'correo_destino' => $customerEmail,
                            'correo_copia' => self::INTERNAL_RECIPIENT,
                            'sent_at' => now(),
                        ]);
                    }

                    $sent++;
                }
            });

        $mode = $dryRun ? 'simulados' : 'enviados';

        $this->info("Recordatorios {$mode}: {$sent}");
        $this->info("Licencias omitidas: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return array<int>
     */
    private function alertDaysFor(Licencia $licencia): array
    {
        if ((int) $licencia->suscripcion_meses >= 12) {
            return [90, 60, 30, 15, 3, 2, 1, 0];
        }

        return [7, 4, 3, 2, 1, 0];
    }
}
