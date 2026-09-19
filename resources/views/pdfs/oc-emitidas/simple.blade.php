@php

$ptSansRegularBase64 = base64_encode(
file_get_contents(public_path('fonts/PTSans-Regular.ttf'))
);

$ptSansBoldBase64 = base64_encode(
file_get_contents(public_path('fonts/PTSans-Bold.ttf'))
);

/*
|--------------------------------------------------------------------------
| Ángulo izquierdo del encabezado
|--------------------------------------------------------------------------
|
| Usamos SVG y no bordes CSS porque Dompdf interpreta mejor
| esta figura como una imagen.
|
*/

$orderAngleSvg = 'data:image/svg+xml;base64,' . base64_encode('
<svg xmlns="http://www.w3.org/2000/svg"
    width="34"
    height="68"
    viewBox="0 0 34 68">
    <polygon
        points="34,0 34,68 0,68"
        fill="#24246f" />
</svg>
');

@endphp

<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">

    <title>Orden de Compra {{ $ocEmitida->numero }}</title>

    <style>
        @font-face {
            font-family: "PT Sans";
            src: url("data:font/truetype;charset=utf-8;base64,{{ $ptSansRegularBase64 }}") format("truetype");
            font-weight: 400;
        }

        @font-face {
            font-family: "PT Sans";
            src: url("data:font/truetype;charset=utf-8;base64,{{ $ptSansBoldBase64 }}") format("truetype");
            font-weight: 700;
        }

        @page {
            margin: 10px 30px 64px 30px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body,
        table,
        tr,
        td,
        th,
        div,
        span {
            font-family: "PT Sans", DejaVu Sans, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            color: #17172d;
            font-size: 9.5px;
        }

        table {
            border-collapse: collapse;
        }

        /* =========================
       HEADER
    ========================= */

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            padding: 0;
            border: none;
            vertical-align: top;
        }

        .logo-area {
            width: 48%;
            padding-top: 5px !important;
        }

        .logo {
            width: 290px;
            height: auto;
        }

        .order-area {
            width: 52%;
            text-align: right;
        }

        .order-box {
            width: 100%;
            position: relative;
        }

        .order-title-table {
            width: 96%;
            margin-left: auto;
            border-collapse: collapse;
        }

        .order-title-table td {
            border: none !important;
            padding: 0 !important;
        }

        .order-angle-cell {
            width: 34px;
            height: 68px;

            padding: 0 !important;
            margin: 0;

            vertical-align: top;

            background: transparent;
        }

        .order-angle-img {
            display: block;

            width: 34px;
            height: 68px;

            margin: 0;
            padding: 0;
        }

        .order-title-main {
            height: 68px;
            background: #24246f;
            color: #ffffff;
            text-align: center;
            vertical-align: top;
            font-size: 26px;
            font-weight: 700;
            padding-top: 10px !important;
            letter-spacing: .2px;
        }

        .order-number-wrapper {
            width: 70%;
            margin: -18px auto 0 auto;
            text-align: center;
        }

        .order-number {
            display: block;
            width: 100%;
            padding: 8px 18px 10px;
            background: #5d47a6;
            color: #ffffff;
            border-radius: 24px;
            font-size: 19px;
            font-weight: 700;
            text-align: center;
        }

        .date-box {
            width: 96%;
            margin-left: auto;
            margin-top: 12px;
            text-align: center;
            font-size: 13px;
        }

        .date-icon-img {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 8px;
        }

        .date-text {
            display: inline-block;
            vertical-align: middle;
        }

        /* =========================
       LÍNEA MULTICOLOR
    ========================= */

        .color-line {
            width: 100%;
            margin-top: 14px;
            margin-bottom: 27px;
        }

        .color-line td {
            border: none;
            padding: 0;
            height: 2px;
        }

        .line-pink {
            width: 15%;
            background: #e50073;
        }

        .line-cyan {
            width: 18%;
            background: #009da5;
        }

        .line-green {
            width: 20%;
            background: #8ebc22;
        }

        .line-purple {
            width: 47%;
            background: #30277d;
        }

        /* =========================
       INFORMACIÓN
    ========================= */

        .info-layout {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-left: -10px;
            width: calc(100% + 20px);
        }

        .info-layout>tbody>tr>td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px;
            border: none;
        }

        .section-header {
            width: 94%;
            border-collapse: collapse;
            position: relative;
            z-index: 2;
        }

        .section-header td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .section-header-icon {
            width: 39px;
            height: 38px;
            text-align: center;
            border-radius: 8px;
        }

        .provider-header-icon {
            background: #49378d;
        }

        .billing-header-icon {
            background: #008f98;
        }

        .section-icon-img {
            width: 25px;
            height: 25px;
            object-fit: contain;
        }

        .section-header-title {
            height: 32px;
            padding-left: 6px !important;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .provider-header-title,
        .provider-header-tail {
            background: #30277d;
        }

        .billing-header-title,
        .billing-header-tail {
            background: #00939b;
        }

        .section-header-tail {
            width: 18px;
        }

        .info-card {
            position: relative;
            z-index: 1;
            margin-top: -3px;
            border: 1px solid #d7d7dd;
            border-radius: 8px;

            /* antes: 14px 16px 10px */
            padding: 14px 6px 10px 6px;

            min-height: 210px;
        }

        .info-row {
            width: 100%;

            /*
     * IMPORTANTE:
     * Dejamos que Dompdf ajuste las columnas por contenido.
     */
            table-layout: auto;
        }

        .info-row td {
            padding-top: 11px;
            padding-bottom: 11px;
            vertical-align: middle;
            border-bottom: 1px solid #e6e6e9;
        }

        .icon-cell {
            width: 27px;

            padding-left: 0 !important;
            padding-right: 3px !important;

            text-align: left;
            vertical-align: middle;
        }

        .body-icon {
            width: 22px;
            height: 22px;
            object-fit: contain;
            vertical-align: middle;
        }

        .label-cell {
            width: 64px;

            padding-left: 0 !important;
            padding-right: 5px !important;

            font-weight: 700;
            font-size: 10px;

            white-space: nowrap;
        }

        .value-cell {
            width: auto;

            padding-left: 0 !important;
            padding-right: 1px !important;

            font-size: 9.5px;
            line-height: 1.4;
            color: #17172d;
        }

        /* Recuperamos también los colores de las etiquetas */
        .provider-label {
            color: #49378d;
        }

        .billing-label {
            color: #008f98;
        }

        /* =========================
       PRODUCTOS
    ========================= */

        .products-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .products-table th {
            background: #44358d;
            color: white;
            border: 1px solid #7165aa;
            padding: 10px 6px;
            font-size: 9px;
            font-weight: 700;

            text-align: center !important;
            vertical-align: middle !important;
        }

        .products-table td {
            border: 1px solid #d7d7dd;
            padding: 11px 7px;
            font-size: 9.5px;

            text-align: center !important;
            vertical-align: middle !important;
        }

        .products-table .item {
            width: 7%;
            text-align: center !important;
        }

        .products-table .code {
            width: 16%;
            text-align: center !important;
        }

        .products-table .description {
            width: 36%;
            text-align: center !important;
        }

        .products-table .quantity {
            width: 11%;
            text-align: center !important;
        }

        .products-table .unit-price {
            width: 15%;
            text-align: center !important;
        }

        .products-table .total-price {
            width: 15%;
            text-align: center !important;
        }

        /* =========================
       INFERIOR
    ========================= */

        .bottom-layout {
            width: 100%;
            margin-top: 14px;
        }

        .bottom-layout>tbody>tr>td {
            border: none;
            vertical-align: top;
        }

        .bottom-left {
            width: 53%;
            padding-right: 22px;
        }

        .bottom-right {
            width: 47%;
        }

        .optional-title {
            color: #008f98;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .optional-line {
            min-height: 22px;
            border-bottom: 1px solid #dddde2;
            padding: 4px 0;
            line-height: 1.4;
        }

        .observations-box {
            margin-top: 11px;
            background: #f8f7fc;
            border: 1px solid #dddce5;
            border-left: 5px solid #5946a1;
            border-radius: 6px;
            padding: 9px 11px 10px 11px;
        }

        .observations-title {
            color: #392b80;
            font-weight: 700;
            font-size: 9px;
            margin: 0 0 5px 0;
            padding: 0;

            line-height: 1.2;
            text-transform: uppercase;
        }

        .observations-text {
            margin: 0;
            padding: 0;

            font-size: 9.5px;
            line-height: 1.35;

            color: #17172d;

            white-space: normal;
        }

        /* =========================
       MODALIDAD
    ========================= */

        .payment-box {
            margin-top: 16px;
            width: 100%;
        }

        .payment-table {
            width: auto;
            border-collapse: collapse;
        }

        .payment-table>tbody>tr>td {
            border: none !important;
            padding: 0;
            vertical-align: middle !important;
        }


        /*
 * Usamos una tabla dentro del círculo porque
 * vertical-align funciona mejor que line-height
 * para centrar "$" en Dompdf.
 */
        .payment-icon-table {
            width: 31px;
            height: 31px;
            border-collapse: separate;
            border-spacing: 0;

            border: 2px solid #e50073;
            border-radius: 50%;
        }

        .payment-icon-table td {
            width: 31px;
            height: 31px;

            padding: 0 !important;
            border: none !important;

            color: #e50073;

            text-align: center;
            vertical-align: middle !important;

            font-size: 18px;
            font-weight: 700;
            line-height: 1;
        }

        .payment-content-cell {
            padding-left: 7px !important;
            vertical-align: middle !important;
        }

        .payment-title {
            margin: 0;

            color: #e50073;

            font-size: 10px;
            font-weight: 700;
            line-height: 1.1;

            white-space: nowrap;
        }

        .payment-value {
            margin-top: 6px;

            color: #17172d;

            font-size: 9.5px;
            line-height: 1.25;
        }

        /* =========================
       TOTALES
    ========================= */

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d7d7dd;
        }

        .totals-table td {
            border: 1px solid #d7d7dd;
            padding: 10px 15px;
        }

        .total-label {
            font-size: 11px;
            font-weight: 700;
        }

        .total-value {
            text-align: right;
            font-size: 12px;
            font-weight: 700;
        }

        .grand-total td {
            background: #008e98;
            color: white;
            border-color: #45aeb5;
            padding-top: 10px;
            padding-bottom: 10px;
        }

        .grand-total .total-label {
            font-size: 16px;
        }

        .grand-total .total-value {
            font-size: 19px;
        }

        /* =========================
       FIRMA
    ========================= */

        .signature {
            margin-top: 28px;
            text-align: center;
            min-height: 140px;
        }

        .signature-text {
            font-size: 10px;
            margin-bottom: 2px;
        }

        .signature-img {
            height: 125px;
            width: auto;
            display: block;
            margin: 0 auto;
        }

        .signature-line {
            width: 185px;
            margin: 0 auto;
            border-top: 1px solid #008e98;
            padding-top: 4px;
        }

        .signature-name {
            font-size: 11px;
            line-height: 1.1;
            font-weight: 700;
        }

        .signature-role {
            font-size: 9px;
            line-height: 1.2;
            margin-top: 2px;
        }

        .signature-company {
            color: #008e98;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.2;
            margin-top: 2px;
        }

        /* =========================
       FOOTER
    ========================= */

        .footer {
            position: fixed;

            /*
     * Sacamos el footer del área útil y lo llevamos
     * hasta los bordes físicos de la página.
     */
            left: -30px;
            right: -30px;
            bottom: -64px;

            width: auto;
            height: 64px;

            background: #202665;
            color: #ffffff;

            /*
     * Compensamos los -30px para que el contenido
     * siga alineado con el resto del documento.
     */
            padding: 10px 30px;

            margin: 0;
        }

        .footer-table {
            width: 100%;
            height: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .footer-table>tbody>tr>td {

            color: #ffffff;

            padding: 0 8px !important;

            vertical-align: middle !important;
            height: 44px;
        }

        .footer-address {
            width: 29%;
            padding-left: 4px !important;
            padding-right: 6px !important;
        }

        .footer-phone {
            width: 23%;
            border-left: 1px solid rgba(255, 255, 255, .35) !important;
        }

        .footer-email {
            width: 24%;
            border-left: 1px solid rgba(255, 255, 255, .35) !important;
        }

        .footer-web {
            width: 24%;
            border-left: 1px solid rgba(255, 255, 255, .35) !important;
        }


        /* Separadores */

        .footer-phone,
        .footer-email,
        .footer-web {
            border-left: 1px solid rgba(255, 255, 255, .35) !important;
        }


        /* Estructura interna */

        .footer-inner {
            width: 100%;
            height: 44px;

            /* IMPORTANTE:
       no usar fixed aquí */
            table-layout: auto;

            border-collapse: collapse;
        }

        .footer-inner>tbody>tr {
            height: 44px;
        }

        .footer-inner td {
            border: none !important;
            padding: 0 !important;

            vertical-align: middle !important;
        }


        /* ICONO */

        .footer-inner-icon {
            width: 30px;
            min-width: 30px;

            padding: 0 !important;

            text-align: center;
            vertical-align: middle !important;
        }


        /* TEXTO */

        .footer-inner-text {
            padding-left: 4px !important;
            padding-right: 1px !important;

            text-align: left;
            vertical-align: middle !important;
        }


        /* Iconos */

        .footer-icon-img {
            display: block;

            width: 25px;
            height: 25px;

            margin: 0 auto;
        }


        /* Texto */

        .footer-title {
            margin: 0;
            padding: 0;

            font-size: 8.8px;
            font-weight: 700;
            line-height: 1.15;

            white-space: nowrap;
        }

        .footer-value {
            margin: 3px 0 0 0;
            padding: 0;

            font-size: 7.8px;
            line-height: 1.25;
        }

        .text-center {
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
        }
    </style>
</head>

<body>

    @php
    /*
    |--------------------------------------------------------------------------
    | CONFIGURACIÓN / DATOS PARA EL PDF
    |--------------------------------------------------------------------------
    */

    $fecha = $ocEmitida->fecha_emision
    ? \Carbon\Carbon::parse($ocEmitida->fecha_emision)
    ->locale('es')
    ->translatedFormat('l, j \d\e F \d\e Y')
    : null;

    $fecha = $fecha ? ucfirst($fecha) : '-';

    /*
    * Por ahora tu campo proveedor parece ser texto.
    * Estos campos quedan preparados para cuando tengas
    * proveedor_ruc, proveedor_direccion, etc.
    */

    $proveedorNombre = $ocEmitida->proveedor ?? '-';

    $proveedorRuc =
    $ocEmitida->proveedor_ruc
    ?? data_get($ocEmitida, 'proveedorRelacion.ruc')
    ?? '-';

    $proveedorDireccion =
    $ocEmitida->proveedor_direccion
    ?? data_get($ocEmitida, 'proveedorRelacion.direccion')
    ?? '-';

    $proveedorTelefono =
    $ocEmitida->proveedor_telefono
    ?? data_get($ocEmitida, 'proveedorRelacion.telefono')
    ?? '-';

    $proveedorContacto =
    $ocEmitida->proveedor_contacto
    ?? data_get($ocEmitida, 'proveedorRelacion.contacto')
    ?? '-';


    /*
    * DATOS DE WILLATEC
    *
    * Estos corresponden al diseño de referencia.
    * Luego podemos llevarlos a config/company.php para no
    * tenerlos quemados directamente en el Blade.
    */

    $empresa = [
    'ruc' => '20602503331',
    'direccion' => 'Jr. Jorge Chávez N° 1747 - Int. 1002 - Breña - Lima',
    'contacto' => 'Luis López',
    'celular' => '942834089',
    'email' => 'ventas@willatec.com',
    ];

    /*
    * Moneda
    */

    $currencyCode =
    $ocEmitida->moneda
    ?? data_get($ocEmitida, 'moneda.codigo')
    ?? 'PEN';

    $currencySymbol = in_array(strtoupper($currencyCode), ['USD', 'DOLAR', 'DÓLAR'])
    ? '$'
    : 'S/';

    /*
    * Modalidad de pago
    */

    $modalidadPago =
    $ocEmitida->modalidad_pago
    ?? $ocEmitida->condicion_pago
    ?? 'Aplicar línea de crédito';

    /*
    * Logo / firma
    *
    * Cambia estas rutas según dónde tengas tus imágenes.
    */

    $logoPath = public_path('img/logoWILLATEC-black.png');
    $firmaPath = public_path('img/firma/firma_gerente.png');
    $iconPhone = public_path('img/icons/footer-phone.png');
    $iconMail = public_path('img/icons/footer-email.png');
    $iconWeb = public_path('img/icons/footer-web.png');
    $iconMap = public_path('img/icons/footer-map.png');
    // Iconos del cuerpo de la Orden de Compra
    $iconAtencion = public_path('img/icons/icon-atencion.png');
    $iconDireccion = public_path('img/icons/icon-direccion.png');
    $iconEjecutivo = public_path('img/icons/icon-ejecutivo.png');
    $iconEmail = public_path('img/icons/icon-email.png');
    $iconEmpresa = public_path('img/icons/icon-empresa.png');
    $iconFecha = public_path('img/icons/icon-fecha.png');
    $iconRuc = public_path('img/icons/icon-ruc.png');
    $iconTelefono = public_path('img/icons/icon-telefono.png');

    $pdfImage = function (?string $path): ?string {
    if (!$path || !file_exists($path) || !is_file($path)) {
    return null;
    }

    $mime = mime_content_type($path) ?: 'image/png';

    return 'data:' . $mime . ';base64,' .
    base64_encode(file_get_contents($path));
    };

    $logoSrc = $pdfImage($logoPath);
    $firmaSrc = $pdfImage($firmaPath);

    $iconPhoneSrc = $pdfImage($iconPhone);
    $iconMailSrc = $pdfImage($iconMail);
    $iconWebSrc = $pdfImage($iconWeb);
    $iconMapSrc = $pdfImage($iconMap);
    $iconAtencionSrc = $pdfImage($iconAtencion);
    $iconDireccionSrc = $pdfImage($iconDireccion);
    $iconEjecutivoSrc = $pdfImage($iconEjecutivo);
    $iconEmailSrc = $pdfImage($iconEmail);
    $iconEmpresaSrc = $pdfImage($iconEmpresa);
    $iconFechaSrc = $pdfImage($iconFecha);
    $iconRucSrc = $pdfImage($iconRuc);
    $iconTelefonoSrc = $pdfImage($iconTelefono);
    @endphp


    {{-- =========================================================
     HEADER
========================================================= --}}

    <table class="header-table">
        <tr>
            <td class="logo-area">

                @if($logoSrc)
                <img
                    src="{{ $logoSrc }}"
                    class="logo"
                    alt="Willatec">
                @else
                <div style="font-size:30px;font-weight:700;">
                    WILLATEC
                </div>

                <div style="font-size:14px;margin-left:70px;">
                    Soluciones Digitales
                </div>
                @endif

            </td>

            <td class="order-area">

                <div class="order-box">

                    <table class="order-title-table">
                        <tr>

                            <td class="order-angle-cell">
                                <img
                                    src="{{ $orderAngleSvg }}"
                                    class="order-angle-img"
                                    alt="">
                            </td>

                            <td class="order-title-main">
                                ORDEN DE COMPRA
                            </td>

                        </tr>
                    </table>

                    <div class="order-number-wrapper">
                        <span class="order-number">
                            N° {{ $ocEmitida->numero }}
                        </span>
                    </div>

                    <div class="date-box">

                        @if($iconFechaSrc)
                        <img
                            src="{{ $iconFechaSrc }}"
                            class="date-icon-img"
                            alt="Fecha">
                        @endif

                        <span class="date-text">
                            {{ $fecha }}
                        </span>

                    </div>

                </div>

            </td>
        </tr>
    </table>


    {{-- Línea multicolor --}}

    <table class="color-line">
        <tr>
            <td class="line-pink"></td>
            <td class="line-cyan"></td>
            <td class="line-green"></td>
            <td class="line-purple"></td>
        </tr>
    </table>


    {{-- =========================================================
     PROVEEDOR / FACTURAR A
========================================================= --}}

    <table class="info-layout">
        <tr>

            {{-- PROVEEDOR --}}
            <td>

                <table class="section-header">
                    <tr>
                        <td class="section-header-icon provider-header-icon">
                            @if($iconEjecutivoSrc)
                            <img src="{{ $iconEjecutivoSrc }}" class="section-icon-img" alt="">
                            @endif
                        </td>

                        <td class="section-header-title provider-header-title">
                            DATOS DEL PROVEEDOR
                        </td>

                        <td class="section-header-tail provider-header-tail"></td>
                    </tr>
                </table>

                <div class="info-card">

                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconEmpresaSrc)
                                <img src="{{ $iconEmpresaSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell provider-label">
                                Señores:
                            </td>

                            <td class="value-cell">
                                {{ $proveedorNombre }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconRucSrc)
                                <img src="{{ $iconRucSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell provider-label">
                                RUC:
                            </td>

                            <td class="value-cell">
                                {{ $proveedorRuc }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconDireccionSrc)
                                <img src="{{ $iconDireccionSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell provider-label">
                                Dirección:
                            </td>

                            <td class="value-cell">
                                {{ $proveedorDireccion }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconTelefonoSrc)
                                <img src="{{ $iconTelefonoSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell provider-label">
                                Teléfono:
                            </td>

                            <td class="value-cell">
                                {{ $proveedorTelefono }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconAtencionSrc)
                                <img src="{{ $iconAtencionSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell provider-label">
                                Atención:
                            </td>

                            <td class="value-cell">
                                {{ $proveedorContacto }}
                            </td>
                        </tr>
                    </table>

                </div>

            </td>


            {{-- FACTURAR A --}}
            <td>

                <table class="section-header">
                    <tr>
                        <td class="section-header-icon billing-header-icon">
                            @if($iconEmpresaSrc)
                            <img src="{{ $iconEmpresaSrc }}" class="section-icon-img" alt="">
                            @endif
                        </td>

                        <td class="section-header-title billing-header-title">
                            FACTURAR A
                        </td>

                        <td class="section-header-tail billing-header-tail"></td>
                    </tr>
                </table>

                <div class="info-card">

                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconRucSrc)
                                <img src="{{ $iconRucSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell billing-label">
                                RUC:
                            </td>

                            <td class="value-cell">
                                {{ $empresa['ruc'] }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconDireccionSrc)
                                <img src="{{ $iconDireccionSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell billing-label">
                                Dirección:
                            </td>

                            <td class="value-cell">
                                {{ $empresa['direccion'] }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconEjecutivoSrc)
                                <img src="{{ $iconEjecutivoSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell billing-label">
                                Contacto:
                            </td>

                            <td class="value-cell">
                                {{ $empresa['contacto'] }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconTelefonoSrc)
                                <img src="{{ $iconTelefonoSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell billing-label">
                                Celular:
                            </td>

                            <td class="value-cell">
                                {{ $empresa['celular'] }}
                            </td>
                        </tr>
                    </table>


                    <table class="info-row">
                        <tr>
                            <td class="icon-cell">
                                @if($iconEmailSrc)
                                <img src="{{ $iconEmailSrc }}" class="body-icon" alt="">
                                @endif
                            </td>

                            <td class="label-cell billing-label">
                                Email:
                            </td>

                            <td class="value-cell">
                                {{ $empresa['email'] }}
                            </td>
                        </tr>
                    </table>

                </div>

            </td>

        </tr>
    </table>


    {{-- =========================================================
     ITEMS
========================================================= --}}

    <table class="products-table">

        <thead>
            <tr>
                <th class="item">
                    ITEM
                </th>

                <th class="code">
                    CÓDIGO
                </th>

                <th class="description">
                    DESCRIPCIÓN
                </th>

                <th class="quantity">
                    CANTIDAD
                </th>

                <th class="unit-price">
                    P.UNITARIO
                    {{ $currencySymbol }}
                </th>

                <th class="total-price">
                    P.TOTAL
                    {{ $currencySymbol }}
                </th>
            </tr>
        </thead>

        <tbody>

            @forelse($ocEmitida->items as $item)

            <tr>

                <td class="item">
                    {{ $loop->iteration }}
                </td>

                <td class="code">
                    {{ $item->codigo ?: '-' }}
                </td>

                <td class="description">
                    {{ $item->descripcion }}
                </td>

                <td class="quantity">
                    {{ number_format((float) $item->cantidad, 0) }}
                </td>

                <td class="unit-price">
                    {{ $currencySymbol }}
                    {{ number_format((float) $item->precio_unitario, 2) }}
                </td>

                <td class="total-price">
                    {{ $currencySymbol }}
                    {{ number_format((float) $item->subtotal, 2) }}
                </td>

            </tr>

            @empty

            <tr>
                <td colspan="6" class="text-center">
                    No existen productos registrados en la orden de compra.
                </td>
            </tr>

            @endforelse

        </tbody>
    </table>


    {{-- =========================================================
     DATOS OPCIONALES + TOTALES
========================================================= --}}

    <table class="bottom-layout">

        <tr>

            {{-- IZQUIERDA --}}
            <td class="bottom-left">

                <div class="optional-title">
                    DATOS OPCIONALES:
                </div>


                <div class="optional-line">

                    @if($ocEmitida->cotizacion?->numero)

                    <strong>Cotización:</strong>
                    {{ $ocEmitida->cotizacion->numero }}

                    @endif

                </div>


                <div class="optional-line">

                    @if($ocEmitida->cliente_nombre)

                    <strong>Cliente:</strong>

                    {{ $ocEmitida->cliente_nombre }}

                    @if($ocEmitida->cliente_ruc)
                    - RUC {{ $ocEmitida->cliente_ruc }}
                    @endif

                    @endif

                </div>


                @if(filled($ocEmitida->observaciones))
                <div class="observations-box">
                    <div class="observations-title">
                        Observaciones de la OC
                    </div>

                    <div class="observations-text">{{ trim((string) $ocEmitida->observaciones) }}</div>
                </div>
                @endif


                {{-- MODALIDAD DE PAGO --}}

                <div class="payment-box">

                    <table class="payment-table">
                        <tr>

                            <td class="payment-icon-cell">

                                <table class="payment-icon-table">
                                    <tr>
                                        <td>
                                            $
                                        </td>
                                    </tr>
                                </table>

                            </td>

                            <td class="payment-content-cell">

                                <div class="payment-title">
                                    MODALIDAD DE PAGO:
                                </div>

                                <div class="payment-value">
                                    {{ $modalidadPago }}
                                </div>

                            </td>

                        </tr>
                    </table>

                </div>

            </td>


            {{-- DERECHA --}}
            <td class="bottom-right">

                <table class="totals-table">

                    <tr>
                        <td class="total-label">
                            SUB TOTAL
                        </td>

                        <td class="total-value nowrap">
                            {{ $currencySymbol }}
                            {{ number_format((float) $ocEmitida->subtotal, 2) }}
                        </td>
                    </tr>


                    <tr>
                        <td class="total-label">
                            I.G.V 18%
                        </td>

                        <td class="total-value nowrap">
                            {{ $currencySymbol }}
                            {{ number_format((float) $ocEmitida->igv, 2) }}
                        </td>
                    </tr>


                    <tr class="grand-total">

                        <td class="total-label">
                            TOTAL
                        </td>

                        <td class="total-value nowrap">
                            {{ $currencySymbol }}
                            {{ number_format((float) $ocEmitida->total, 2) }}
                        </td>

                    </tr>

                </table>


                {{-- FIRMA --}}

                <div class="signature">

                    <div class="signature-text">
                        Atentamente,
                    </div>

                    @if($firmaSrc)
                    <img
                        src="{{ $firmaSrc }}"
                        class="signature-img"
                        alt="Firma">
                    @else
                    <div style="height:62px;"></div>
                    @endif

                    <!-- <div class="signature-line">

                        <div class="signature-name">
                            ING. LUIS LÓPEZ
                        </div>

                        <div class="signature-role">
                            GERENTE GENERAL
                        </div>

                        <div class="signature-company">
                            WILLATEC S.A.C.
                        </div>

                    </div> -->

                </div>

            </td>

        </tr>

    </table>


    {{-- =========================================================
     FOOTER
========================================================= --}}

    <div class="footer">

        <table class="footer-table">
            <tr>

                {{-- DIRECCIÓN --}}
                <td class="footer-address">
                    <table class="footer-inner">
                        <tr>
                            <td class="footer-inner-icon">
                                @if($iconMapSrc)
                                <img
                                    src="{{ $iconMapSrc }}"
                                    class="footer-icon-img"
                                    alt="">
                                @endif
                            </td>

                            <td class="footer-inner-text">
                                <div class="footer-title">
                                    Dirección Comercial:
                                </div>

                                <div class="footer-value">
                                    Jr. Jorge Chávez N° 1747 - Int. 1002<br>
                                    Breña - Lima
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>


                {{-- TELÉFONO --}}
                <td class="footer-phone">
                    <table class="footer-inner">
                        <tr>
                            <td class="footer-inner-icon">
                                @if($iconPhoneSrc)
                                <img
                                    src="{{ $iconPhoneSrc }}"
                                    class="footer-icon-img"
                                    alt="">
                                @endif
                            </td>

                            <td class="footer-inner-text">
                                <div class="footer-title">
                                    Central Telefónica:
                                </div>

                                <div class="footer-value">
                                    (511) 757 - 1253
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>


                {{-- EMAIL --}}
                <td class="footer-email">
                    <table class="footer-inner">
                        <tr>
                            <td class="footer-inner-icon">
                                @if($iconMailSrc)
                                <img
                                    src="{{ $iconMailSrc }}"
                                    class="footer-icon-img"
                                    alt="">
                                @endif
                            </td>

                            <td class="footer-inner-text">
                                <div class="footer-title">
                                    E-mail:
                                </div>

                                <div class="footer-value">
                                    ventas@willatec.com
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>


                {{-- WEB --}}
                <td class="footer-web">
                    <table class="footer-inner">
                        <tr>
                            <td class="footer-inner-icon">
                                @if($iconWebSrc)
                                <img
                                    src="{{ $iconWebSrc }}"
                                    class="footer-icon-img"
                                    alt="">
                                @endif
                            </td>

                            <td class="footer-inner-text">
                                <div class="footer-title">
                                    Web:
                                </div>

                                <div class="footer-value">
                                    www.willatec.com
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>

            </tr>
        </table>

    </div>

</body>

</html>