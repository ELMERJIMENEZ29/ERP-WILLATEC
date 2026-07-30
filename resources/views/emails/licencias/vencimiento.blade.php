<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recordatorio de renovacion de licencia</title>
</head>
<body style="margin:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#0f172a;padding:22px 28px;">
                            <div style="font-size:20px;font-weight:700;color:#ffffff;">WILLATEC</div>
                            <div style="margin-top:4px;font-size:13px;color:#bfdbfe;">Recordatorio de renovacion de licencia</div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.5;">
                                Estimado cliente,
                            </p>

                            @if ($diasRestantes === 0)
                                <p style="margin:0 0 18px;font-size:16px;line-height:1.5;">
                                    Le recordamos que la licencia <strong>{{ $licencia->producto }}</strong> vence el dia de hoy.
                                </p>
                            @else
                                <p style="margin:0 0 18px;font-size:16px;line-height:1.5;">
                                    Le recordamos que la licencia <strong>{{ $licencia->producto }}</strong> vence en <strong>{{ $diasRestantes }} dia(s)</strong>.
                                </p>
                            @endif

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:20px 0;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="background:#f8fafc;padding:10px 14px;font-size:13px;color:#64748b;width:38%;">Empresa</td>
                                    <td style="padding:10px 14px;font-size:14px;font-weight:600;">{{ $licencia->empresa }}</td>
                                </tr>
                                <tr>
                                    <td style="background:#f8fafc;padding:10px 14px;font-size:13px;color:#64748b;">Producto</td>
                                    <td style="padding:10px 14px;font-size:14px;">{{ $licencia->producto }}</td>
                                </tr>
                                <tr>
                                    <td style="background:#f8fafc;padding:10px 14px;font-size:13px;color:#64748b;">Cantidad</td>
                                    <td style="padding:10px 14px;font-size:14px;">{{ $licencia->cantidad }}</td>
                                </tr>
                                <tr>
                                    <td style="background:#f8fafc;padding:10px 14px;font-size:13px;color:#64748b;">Fecha de inicio</td>
                                    <td style="padding:10px 14px;font-size:14px;">{{ optional($licencia->fecha_inicio)->format('d/m/Y') }}</td>
                                </tr>
                                <tr>
                                    <td style="background:#f8fafc;padding:10px 14px;font-size:13px;color:#64748b;">Fecha de vencimiento</td>
                                    <td style="padding:10px 14px;font-size:14px;font-weight:700;color:#b91c1c;">{{ optional($licencia->fecha_renovacion)->format('d/m/Y') }}</td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                                Para evitar interrupciones en el servicio, recomendamos gestionar la renovacion con anticipacion.
                            </p>

                            <p style="margin:24px 0 0;font-size:15px;line-height:1.6;">
                                Atentamente,<br>
                                <strong>Equipo Willatec</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f8fafc;padding:16px 28px;font-size:12px;color:#64748b;">
                            Este es un recordatorio automatico generado por el sistema ERP Willatec.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
