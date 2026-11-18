<?php

namespace App\Console\Commands;

use App\Models\Cheque;
use App\Models\ChequeAlertaEnviada;
use App\Services\WorkflowNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarAlertasCheques extends Command
{
    protected $signature = 'cheques:enviar-alertas {--dry-run} {--today=}';

    protected $description = 'Genera notificaciones internas diarias para cheques que vencen en siete días o menos';

    public function handle(WorkflowNotificationService $notifications): int
    {
        $today = $this->option('today')
            ? Carbon::createFromFormat('Y-m-d', (string) $this->option('today'))->startOfDay()
            : Carbon::today('America/Lima');
        $sent = 0;

        Cheque::query()
            ->with(['banco', 'proveedor', 'cuentasPorPagar.comprobante'])
            ->whereIn('estado', ['registrado', 'entregado'])
            ->whereDate('fecha_cheque', '>=', $today->toDateString())
            ->whereDate('fecha_cheque', '<=', $today->copy()->addDays(7)->toDateString())
            ->orderBy('fecha_cheque')
            ->chunkById(100, function ($cheques) use ($today, $notifications, &$sent): void {
                foreach ($cheques as $cheque) {
                    $vencimiento = Carbon::parse($cheque->fecha_cheque)->startOfDay();
                    $days = (int) $today->diffInDays($vencimiento, false);
                    $alreadySent = ChequeAlertaEnviada::query()
                        ->where('cheque_id', $cheque->id)
                        ->whereDate('fecha_vencimiento', $vencimiento->toDateString())
                        ->where('dias_antes', $days)
                        ->exists();
                    if ($alreadySent) {
                        continue;
                    }
                    if (! $this->option('dry-run')) {
                        $document = $cheque->cuentasPorPagar->first()?->comprobante;
                        $notifications->contabilidad(
                            $days === 0 ? 'Cheque vence hoy' : "Cheque vence en {$days} días",
                            "El cheque {$cheque->numero} de {$cheque->banco?->nombre}, por {$cheque->monto}, vence el {$vencimiento->format('d/m/Y')}".($document ? " y corresponde a {$document->serie}-{$document->numero}." : '.'),
                            '/contabilidad/cheques',
                            'cheque_por_vencer',
                            ['cheque_id' => $cheque->id, 'dias_restantes' => $days]
                        );
                        ChequeAlertaEnviada::create([
                            'cheque_id' => $cheque->id,
                            'fecha_vencimiento' => $vencimiento,
                            'dias_antes' => $days,
                            'sent_at' => now(),
                        ]);
                    }
                    $sent++;
                }
            });

        $this->info("Alertas de cheques: {$sent}");

        return self::SUCCESS;
    }
}
