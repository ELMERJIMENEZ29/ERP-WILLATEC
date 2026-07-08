<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WooCommerceSyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WooCommerceWebhookController extends Controller
{
    public function orders(Request $request)
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

        return response()->json([
            'message' => 'Webhook recibido; procesamiento de stock pendiente de configuracion final',
            'log_id' => $log->id,
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
