<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gracias por renovar su hosting</title>
</head>
@php
    $fechaInicio = optional($hosting->fecha_inicio)->format('d/m/Y') ?: '-';
    $fechaVencimiento = optional($hosting->fecha_renovacion)->format('d/m/Y') ?: '-';
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
                                    <div style="font-size:15px;font-weight:700;color:rgba(255,255,255,.72);">Renovacion confirmada</div>
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
                            Gracias por renovar con <strong style="color:#111827;">WILLATEC S.A.C.</strong>. Confirmamos que la renovacion de su servicio de hosting fue procesada correctamente.
                        </p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:22px;border-top:1px solid #f0f4fa;border-bottom:1px solid #f0f4fa;table-layout:fixed;">
                            <tr>
                                <td style="width:33.33%;text-align:center;padding:18px 8px 10px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:10px;color:#6b82a8;text-transform:uppercase;letter-spacing:1.3px;font-weight:700;">Empresa</div>
                                </td>
                                <td style="width:33.33%;text-align:center;padding:18px 8px 10px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:10px;color:#6b82a8;text-transform:uppercase;letter-spacing:1.3px;font-weight:700;">Dominio</div>
                                </td>
                                <td style="width:33.33%;text-align:center;padding:18px 8px 10px;vertical-align:middle;">
                                    <div style="font-size:10px;color:#6b82a8;text-transform:uppercase;letter-spacing:1.3px;font-weight:700;">Plan</div>
                                </td>
                            </tr>
                            <tr>
                                <td style="width:33.33%;text-align:center;padding:8px 8px 20px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:12px;font-weight:700;color:#0A3B87;line-height:1.35;">{{ $hosting->empresa }}</div>
                                </td>
                                <td style="width:33.33%;text-align:center;padding:8px 8px 20px;border-right:1px solid #e8edf5;vertical-align:middle;">
                                    <div style="font-size:12px;font-weight:700;color:#0A3B87;line-height:1.35;">{{ $hosting->dominio }}</div>
                                </td>
                                <td style="width:33.33%;text-align:center;padding:8px 8px 20px;vertical-align:middle;">
                                    <div style="font-size:12px;font-weight:700;color:#0A3B87;line-height:1.35;">{{ $hosting->plan }}</div>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-bottom:22px;border:1px solid #dce5f2;border-radius:8px;padding:12px 14px;background:#f8fbff;">
                            <div style="font-size:14px;font-weight:700;color:#0A3B87;text-transform:uppercase;margin-bottom:8px;">Nuevo periodo</div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="font-size:12px;color:#6b82a8;">Inicio: <strong style="color:#1a2847;">{{ $fechaInicio }}</strong></td>
                                    <td style="text-align:right;font-size:12px;color:#6b82a8;">Vencimiento: <strong style="color:#1a2847;">{{ $fechaVencimiento }}</strong></td>
                                </tr>
                            </table>
                        </div>
                        <p style="margin:0 0 4px;font-size:13px;color:#1a2847;">Atentamente,</p>
                        <p style="margin:0 0 26px;font-size:13px;font-weight:700;color:#0A3B87;">Equipo de Hosting y Dominios - Willatec S.A.C</p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#0A3B87;padding:14px 28px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="font-size:10px;color:rgba(255,255,255,.55);line-height:1.6;">Sistema ERP Willatec - Aviso Automatico</td>
                                <td style="text-align:right;font-size:10px;color:rgba(255,255,255,.65);line-height:1.6;">&copy; {{ date('Y') }} Willatec S.A.C.</td>
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
