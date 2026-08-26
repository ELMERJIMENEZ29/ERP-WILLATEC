<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de renovacion de hosting</title>
</head>
@php
    $dias = (int) $diasRestantes;
    $diasTexto = $dias === 1 ? '1 dia' : "{$dias} dias";
    $venceHoy = $dias === 0;
    $esUrgente = $dias <= 7;

    $textoDestacado = $venceHoy ? '#dc2626' : ($esUrgente ? '#F7941D' : '#0A3B87');
    $alertaFondo = $venceHoy ? '#fef2f2' : '#fff8f0';
    $fechaInicio = optional($hosting->fecha_inicio)->format('d/m/Y') ?: '-';
    $fechaVencimiento = optional($hosting->fecha_renovacion)->format('d/m/Y') ?: '-';
    $inicio = $hosting->fecha_inicio ? $hosting->fecha_inicio->copy()->startOfDay() : null;
    $vencimiento = $hosting->fecha_renovacion ? $hosting->fecha_renovacion->copy()->startOfDay() : null;
    $hoy = $vencimiento ? $vencimiento->copy()->subDays(max(0, $dias))->startOfDay() : now()->startOfDay();
    $porcentajeVigencia = 0;

    if ($inicio && $vencimiento) {
        $totalDiasVigencia = max(1, $inicio->diffInDays($vencimiento, false));
        $diasTranscurridos = max(0, min($totalDiasVigencia, $inicio->diffInDays($hoy, false)));
        $porcentajeVigencia = $hoy->greaterThanOrEqualTo($vencimiento)
            ? 100
            : (int) round(($diasTranscurridos / $totalDiasVigencia) * 100);
    }

    $porcentajeTimeline = $porcentajeVigencia > 0 ? max(3, $porcentajeVigencia) : 0;
@endphp
<body style="margin:0;padding:0;background:#e8edf5;font-family:Arial,Helvetica,sans-serif;color:#1a2847;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#e8edf5;padding:28px 14px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:580px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:0;">
                <tr>
                    <td style="background:#0A3B87;padding:22px 28px 18px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="vertical-align:middle;">
                                    <div style="font-size:15px;font-weight:700;color:rgba(255,255,255,.72);">Renovacion de hosting y dominio</div>
                                </td>
                                <td style="text-align:right;vertical-align:middle;">
                                    <div style="font-size:21px;font-weight:700;color:#ffffff;letter-spacing:1px;line-height:1;margin-bottom:6px;">WILLATEC</div>
                                    <div style="font-size:10px;color:rgba(255,255,255,.55);letter-spacing:3px;text-transform:uppercase;font-weight:700;">Soluciones digitales</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:30px 28px 0;">
                        <p style="margin:0 0 8px;font-size:13px;color:#6b82a8;">Estimado cliente,</p>

                        <p style="margin:0 0 18px;font-size:16px;line-height:1.65;color:#1a2847;text-align:justify;">
                            <strong style="color:#111827;">WILLATEC S.A.C.</strong> le informa que el servicio de hosting asociado a su dominio
                            @if($venceHoy)
                                <strong style="color:#dc2626;">vence el dia de hoy.</strong>
                            @else
                                esta proximo a vencer.
                            @endif
                            Le recomendamos gestionar la <strong>renovacion con anticipacion</strong> para evitar interrupciones en el servicio.
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:18px;border-top:1px solid #f0f4fa;border-bottom:1px solid #f0f4fa;table-layout:fixed;">
                            <tr>
                                <td style="width:25%;text-align:center;padding:18px 8px 10px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:10px;color:#6b82a8;text-transform:uppercase;letter-spacing:1.3px;font-weight:700;">Empresa</div>
                                </td>
                                <td style="width:25%;text-align:center;padding:18px 8px 10px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:10px;color:#6b82a8;text-transform:uppercase;letter-spacing:1.3px;font-weight:700;">Dominio</div>
                                </td>
                                <td style="width:25%;text-align:center;padding:18px 8px 10px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:10px;color:#6b82a8;text-transform:uppercase;letter-spacing:1.3px;font-weight:700;">Plan</div>
                                </td>
                                <td style="width:25%;text-align:center;padding:18px 8px 10px;vertical-align:middle;">
                                    <div style="font-size:10px;color:{{ $textoDestacado }};text-transform:uppercase;letter-spacing:1.3px;font-weight:700;">Suscripcion</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:25%;text-align:center;padding:8px 8px 20px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:12px;font-weight:700;color:#0A3B87;line-height:1.35;">{{ $hosting->empresa }}</div>
                                </td>
                                <td style="width:25%;text-align:center;padding:8px 8px 20px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:12px;font-weight:700;color:#0A3B87;line-height:1.35;">{{ $hosting->dominio }}</div>
                                </td>
                                <td style="width:25%;text-align:center;padding:8px 8px 20px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:12px;font-weight:700;color:#0A3B87;line-height:1.35;">{{ $hosting->plan }}</div>
                                </td>
                                <td style="width:25%;text-align:center;padding:8px 8px 20px;vertical-align:middle;">
                                    <div style="font-size:14px;font-weight:700;color:{{ $textoDestacado }};">{{ $hosting->suscripcion }}</div>
                                </td>
                            </tr>
                        </table>

                        <div style="margin-bottom:22px;border:1px solid #f0b4a7;border-top:1px solid #ef8d7f;border-radius:8px;padding:12px 14px;background:#fffefe;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:8px;">
                                <tr>
                                    <td colspan="2" style="font-size:14px;font-weight:700;color:#ef4444;text-transform:uppercase;">Periodo:</td>
                                </tr>
                                <tr>
                                    <td style="font-size:11px;color:#6b82a8;">Inicio: {{ $fechaInicio }}</td>
                                    <td style="text-align:right;font-size:11px;font-weight:700;color:{{ $textoDestacado }};">Vencimiento: {{ $fechaVencimiento }}</td>
                                </tr>
                            </table>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#e8edf5;border-radius:4px;height:8px;margin:0;">
                                <tr>
                                    <td style="height:8px;font-size:0;line-height:0;border-radius:4px;">
                                        @if($porcentajeTimeline > 0)
                                            <table role="presentation" width="{{ $porcentajeTimeline }}%" cellspacing="0" cellpadding="0" style="background:#ef2b2d;border-radius:4px;height:8px;margin:0;">
                                                <tr>
                                                    <td style="height:8px;font-size:0;line-height:0;border-radius:4px;">&nbsp;</td>
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                            <div style="text-align:center;margin-top:10px;">
                                <span style="background:{{ $alertaFondo }};color:{{ $textoDestacado }};font-size:11px;font-weight:700;padding:4px 13px;border-radius:100px;letter-spacing:1px;text-transform:uppercase;">{{ $venceHoy ? 'Vence hoy' : 'Faltan '.$diasTexto }}</span>
                            </div>
                        </div>

                        <p style="margin:0 0 4px;font-size:13px;color:#1a2847;">Atentamente,</p>
                        <p style="margin:0 0 26px;font-size:13px;font-weight:700;color:#0A3B87;">Equipo de Hosting y Dominios - Willatec S.A.C</p>
                    </td>
                </tr>

                <tr>
                    <td style="background:#f4f7fc;border-top:1px solid #dce5f2;padding:14px 28px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="font-size:11px;color:#6b82a8;line-height:1.6;">
                                    <strong style="display:block;color:#0A3B87;margin-bottom:4px;">Necesitas ayuda con tu renovacion?</strong>
                                    <div>
                                        Escribenos al correo:
                                        <a href="mailto:{{ config('mail.hosting_support_email', 'ventas@willatec.com') }}" style="color:#0A3B87;font-weight:700;text-decoration:none;">{{ config('mail.hosting_support_email', 'ventas@willatec.com') }}</a>
                                        &nbsp;o comunicate al WhatsApp&nbsp;
                                        <a href="https://wa.me/51934577815" style="color:#0A3B87;font-weight:700;text-decoration:none;">934 577 815</a>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="background:#0A3B87;padding:14px 28px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="font-size:10px;color:rgba(255,255,255,.55);line-height:1.6;">Sistema ERP Willatec - Aviso Automatico</td>
                                <td style="text-align:right;font-size:10px;color:rgba(255,255,255,.65);line-height:1.6;">
                                    © {{ date('Y') }} Willatec S.A.C.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
