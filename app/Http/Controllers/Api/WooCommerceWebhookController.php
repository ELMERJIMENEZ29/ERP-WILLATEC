<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WooCommerceSyncLog;
use App\Services\WooCommerce\WooCommercePedidoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WooCommerceWebhookController extends Controller
{
    public function orders(Request $request, WooCommercePedidoService $pedidoService)
    {
        $signatureValid = $this->validarFirma($request);

        $log = WooCommerceSyncLog::create([
            'tipo' => 'pedido_webhook',
            'direccion' => 'woocommerce_to_erp',
            'endpoint' => '/api/woocommerce/webhook/orders',
            'payload' => $request->all(),
            'estado' => $signatureValid ? 'pendiente' : 'error',
            'mensaje_error' => $signatureValid ? null : 'Firma WooCommerce invalida o ausente.',
            'referencia_tipo' => 'woocommerce_order',
            'referencia_id' => $request->integer('id') ?: null,
        ]);

        if (! $signatureValid) {
            Log::warning('Webhook WooCommerce rechazado por firma invalida', [
                'log_id' => $log->id,
            ]);

            return response()->json([
                'message' => 'Firma invalida',
                'log_id' => $log->id,
            ], 401);
        }

        try {
            $pedido = $pedidoService->importarPedido($request->all());
            $log->forceFill([
                'estado' => 'exitoso',
                'referencia_tipo' => 'woocommerce_pedido',
                'referencia_id' => $pedido->id,
            ])->save();
        } catch (\Throwable $exception) {
            $log->forceFill([
                'estado' => 'error',
                'mensaje_error' => $exception->getMessage(),
            ])->save();

            Log::warning('Webhook WooCommerce recibido pero no procesado', [
                'log_id' => $log->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Webhook recibido, pero no se pudo registrar el pedido',
                'error' => $exception->getMessage(),
                'log_id' => $log->id,
            ], 422);
        }

        return response()->json([
            'message' => 'Pedido WooCommerce recibido y registrado',
            'log_id' => $log->id,
            'pedido_id' => $pedido->id,
        ]);
    }

    private function validarFirma(Request $request): bool
    {
        $secret = config('services.woocommerce.webhook_secret');

        if (! filled($secret)) {
            return true;
        }

        $signature = $request->header('X-WC-Webhook-Signature');

        if (! $signature) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), (string) $secret, true));

        return hash_equals($expected, $signature);
    }
}
