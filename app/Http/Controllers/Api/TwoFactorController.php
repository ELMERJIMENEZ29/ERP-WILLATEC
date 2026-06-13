<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    public function enable(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => json_encode(
                collect(range(1, 8))->map(fn() => Str::random(10))->all()
            ),
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => '2FA generado. Escanea el QR para confirmar.',
        ]);
    }

    public function qr(Request $request)
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => '2FA no iniciado'], 422);
        }

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($user->two_factor_secret);

        $qrText = $google2fa->getQRCodeUrl(
            config('app.name', 'ERP Willatec'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return response()->json([
            'svg' => $writer->writeString($qrText),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6|max:6',
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => '2FA no iniciado'], 422);
        }

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($user->two_factor_secret);

        if (! $google2fa->verifyKey($secret, $request->code)) {
            return response()->json(['message' => 'Código inválido'], 422);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'message' => '2FA activado correctamente',
            'recovery_codes' => json_decode($user->two_factor_recovery_codes, true),
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        if (! password_verify($request->password, $request->user()->password)) {
            return response()->json(['message' => 'Contraseña incorrecta'], 422);
        }

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => '2FA desactivado',
        ]);
    }
}
