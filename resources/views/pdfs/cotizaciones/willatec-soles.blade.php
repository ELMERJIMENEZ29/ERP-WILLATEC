@extends('pdfs.layouts.pdf-base')

@section('content')
@php
use Carbon\Carbon;

$primerNombre = explode(' ', trim($cotizacion->user->nombres ?? ''))[0] ?? '';
$primerApellido = explode(' ', trim($cotizacion->user->apellidos ?? ''))[0] ?? '';
$telefonoCreador = trim((string) data_get($cotizacion, 'user.profile.telefono', ''));
$whatsappEmisor = $telefonoCreador !== '' ? $telefonoCreador : '934 577 815';
$validezDias = (int) ($cotizacion->validez_dias ?? 10);
$fechaEmision = Carbon::parse($cotizacion->fecha)->format('d/m/Y');
$fechaValidez = Carbon::parse($cotizacion->fecha)
->addDays($validezDias)
->format('d/m/Y');
$simbolo = $cotizacion->moneda->simbolo ?? '$';
$codigoMoneda = $cotizacion->moneda->codigo ?? 'USD';
$nombreMoneda = $codigoMoneda === 'PEN' ? 'Soles Peruanos (PEN)' : 'Dolares Americanos (USD)';
$incluyeIgv = (bool) ($cotizacion->plantilla->incluye_igv ?? false);
$textoIgv = $incluyeIgv ? 'SI incluyen IGV' : 'NO incluyen IGV';
$textoCuotaIgv = $incluyeIgv ? 'incl. IGV' : 'sin IGV';
$formaPago = $cotizacion->forma_pago ?? 'AL CONTADO';
$formaPagoCalendario = in_array($formaPago, ['CRÉDITO 15 DÍAS', 'CRÉDITO 30 DÍAS', 'CRÃ‰DITO 15 DÃAS', 'CRÃ‰DITO 30 DÃAS'], true) ? ' calendario, contados a partir de la fecha de emisión de la factura.' : '';
$adelantoPorcentajeRaw = $cotizacion->adelanto_porcentaje ?? null;
$adelantoPorcentaje = $adelantoPorcentajeRaw !== null ? rtrim(rtrim(number_format((float) $adelantoPorcentajeRaw, 2, '.', ''), '0'), '.') : null;
if ((bool) ($cotizacion->adelanto ?? false) && $adelantoPorcentaje !== null && (float) $adelantoPorcentajeRaw > 0) {
    $formaPago = 'Adelanto del '.$adelantoPorcentaje.'%, lo restante '.$formaPago;
}
$destinoEntrega = $cotizacion->entrega_provincia && filled($cotizacion->entrega_destino)
? $cotizacion->entrega_destino
: 'Lima Metropolitana';
$esMultidestino = (bool) ($cotizacion->entrega_multidestino ?? false);
$condicionEntrega = $esMultidestino
? 'Incluye entrega en oficinas del cliente.'
: 'Incluye entrega en oficinas del cliente, ' . $destinoEntrega . '.';
$direccionEmpresaConfigurada = trim((string) data_get($empresaConfiguracion ?? null, 'direccion', ''));
$direccionEmpresaDetalle = $direccionEmpresaConfigurada !== '' ? $direccionEmpresaConfigurada : 'Jr. Jorge Chavez Nro. 1747 - Of.1002 - Breña - Lima';
$direccionEmpresaFooter = $direccionEmpresaConfigurada !== '' ? $direccionEmpresaConfigurada : 'Jr. Jorge Chavez Nro. 1747 - Of.1002 - Breña - Lima';
$logoBancoNacion = public_path('img/banco-nacion-logo.png');
$logoWillatec = public_path('img/logoWILLATEC-black.png');
$logoHomologado = public_path('img/logo-homologado.png');
$logoHomologado2 = public_path('img/logo-homologado-2.png');
$logoBcp = public_path('img/bcp-logo.png');
$logoYape = public_path('img/yape-logo.png');
$qrYape = public_path('img/yape-qr.jpeg');
$firmaGerente = public_path('img/firma/firma_gerente.png');
$pdfImage = function (?string $path): ?string {
if (! $path || ! file_exists($path) || ! is_file($path)) {
return null;
}

$realPath = realpath($path);
$publicPath = realpath(public_path());
$publicStoragePath = realpath(storage_path('app/public'));

if (
! $realPath ||
(
(! $publicPath || ! str_starts_with($realPath, $publicPath . DIRECTORY_SEPARATOR)) &&
(! $publicStoragePath || ! str_starts_with($realPath, $publicStoragePath . DIRECTORY_SEPARATOR))
)
) {
return null;
}

$mime = mime_content_type($realPath) ?: 'image/png';

return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($realPath));
};
$cotizacionItemImagePath = function (?string $path): ?string {
if (! $path) {
return null;
}

$path = parse_url($path, PHP_URL_PATH) ?: $path;
$path = ltrim(str_replace('\\', '/', $path), '/');

if (str_starts_with($path, 'storage/app/public/')) {
$path = substr($path, strlen('storage/app/public/'));
} elseif (str_starts_with($path, 'public/storage/')) {
$path = substr($path, strlen('public/storage/'));
} elseif (str_starts_with($path, 'storage/')) {
$path = substr($path, strlen('storage/'));
}

if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\')) {
return null;
}

if (! preg_match('#^(productos|cotizacion-items)/[A-Za-z0-9._/-]+$#', $path)) {
return null;
}

$storagePath = storage_path('app/public/' . $path);

if (file_exists($storagePath)) {
return $storagePath;
}

$publicPath = public_path('storage/' . $path);

return file_exists($publicPath) ? $publicPath : null;
};
$ptSansRegularBase64 = base64_encode(file_get_contents(public_path('fonts/PTSans-Regular.ttf')));
$ptSansBoldBase64 = base64_encode(file_get_contents(public_path('fonts/PTSans-Bold.ttf')));
$logoDell = public_path('img/aliados/dell.png');
$logoHp = public_path('img/aliados/hp.png');
$logoMicrosoft = public_path('img/aliados/microsoft.png');
$logoSynology = public_path('img/aliados/synology.png');
$logoLenovo = public_path('img/aliados/lenovo.jpg');
$iconPhone = public_path('img/icons/footer-phone.png');
$iconMail = public_path('img/icons/footer-email.png');
$iconWeb = public_path('img/icons/footer-web.png');
$iconMap = public_path('img/icons/footer-map.png');
$logoFooter = public_path('img/logoWILLATEC-white.png');
@endphp

<style>
    @font-face {
        font-family: "PT Sans";
        src: url("data:font/truetype;charset=utf-8;base64,{{ $ptSansRegularBase64 }}") format("truetype");
        font-weight: 400;
        font-style: normal;
    }

    @font-face {
        font-family: "PT Sans";
        src: url("data:font/truetype;charset=utf-8;base64,{{ $ptSansBoldBase64 }}") format("truetype");
        font-weight: 700;
        font-style: normal;
    }

    html,
    body,
    table,
    thead,
    tbody,
    tr,
    td,
    th,
    div,
    span,
    b,
    strong,
    p {
        font-family: "PT Sans" !important;
    }

    body {
        color: #2C2C4A;
        font-size: 9.2px;
        margin: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    td,
    th {
        padding: 0;
        vertical-align: top;
    }

    .wrap {
        border-collapse: collapse;
        page-break-inside: auto;
    }

    .wrap tr,
    .wrap td {
        page-break-inside: auto;
    }

    .strip {
        width: 7px;
        background: #1565C0;
    }

    .body {
        padding: 0;
    }

    .header {
        border-bottom: 2px solid #1565c0;
    }

    .header-left {
        width: 55%;
        padding: 16px 14px 13px 16px;
    }

    .header-right {
        width: 45%;
        padding: 16px 16px 13px 14px;
        text-align: right;
    }

    .header-left-new {
        width: 28%;
        padding: 10px 2px 8px 12px;
        text-align: left;
        vertical-align: middle;
    }

    .header-center-new {
        width: 42%;
        padding: 6px 5px 8px 5px;
        text-align: center;
        border-left: none;
        border-right: none;
        vertical-align: top;
    }

    .header-center-box {
        border-left: 1px solid #E6ECF5;
        border-right: 1px solid #E6ECF5;
        padding: 4px 10px 7px 10px;

        margin-bottom: 5px;
    }

    .header-right-new {
        width: 30%;
        padding: 8px 10px 8px 6px;
        text-align: right;
        vertical-align: top;
    }

    .logo-willatec {
        height: 68px;
        width: auto;
        display: block;
        margin: 20px 0 0 0;
    }

    .logo-homologado-main {
        height: 65px;
        width: auto;
    }

    .logo-homologado-left {
        width: 125px;
        height: auto;
        max-width: none;
        max-height: none;

        position: relative;
        left: -8px;
    }

    .logo-homologado-right {
        max-height: 102px;
        max-width: 72px;
    }

    .mini-line {
        width: 30px;
        border-top: 2px solid #1565C0;
        margin: 2px 0 6px;
    }

    .tag {
        font-size: 9.2px;
        font-weight: 700;
        color: #5f6f86;

        letter-spacing: 1.1px;
        text-transform: uppercase;

        text-align: center;

        margin-top: 1px;
        margin-bottom: 12px;

        white-space: nowrap;
    }

    .company {
        font-size: 15px;
        font-weight: 700;
        color: #1A1A2E;
        letter-spacing: 1px;
    }

    .title {
        font-size: 20px;
        font-weight: 700;
        color: #1565C0;
        letter-spacing: 1.2px;
        line-height: 1;
        margin-bottom: 6px;
        text-align: right;
    }

    .meta {
        width: auto;
        margin-left: auto;
        font-size: 7.6px;
    }

    .meta td {
        padding: 1px 2px;
        color: #76849a;
        line-height: 1.15;
        vertical-align: middle;
    }

    .meta .value {
        padding-left: 6px;
        color: #26354d;
        font-weight: 700;
        text-align: right;
    }

    .badge {
        display: inline-block;
        background: #E8F4FF;
        color: #1565C0;
        font-size: 7px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 10px;
        margin-top: 4px;
    }

    .info {
        border-bottom: 1px solid #E0E6F0;
    }

    .info-cell {
        width: 50%;
        padding: 8px 14px;
    }

    .info-left {
        border-right: none;
    }

    .info-content-left {
        border-right: 1px solid #E0E6F0;

        padding-right: 14px;
    }

    .section-title {
        font-size: 8.4px;
        font-weight: 700;
        letter-spacing: 1.7px;
        text-transform: uppercase;
        color: #1565C0;
        border-bottom: 1px solid #E3F2FD;
        padding-bottom: 3px;
        margin-bottom: 6px;
    }

    .name {
        font-size: 14px;
        font-weight: 700;
    }

    .detail {
        font-size: 9.5px;
        color: #6B7A99;
        line-height: 1.45;
    }

    .detail b {
        color: #2C2C4A;
    }

    .items-wrap {
        padding: 8px 14px;
    }

    .items {
        font-size: 8.8px;
    }

    .items thead tr {
        background: #1565C0;
        color: #fff;
    }

    .items th {
        font-size: 7.6px;
        padding: 5px 5px;
    }

    .items td {
        padding: 4px 5px;
    }

    .items .even {
        background: #F5F7FA;
    }

    .center {
        text-align: center;
    }

    .right {
        text-align: right;
    }

    .strong {
        font-weight: 700;
        color: #1A1A2E;
        font-size: 9.2px;
    }

    .muted {
        color: #6B7A99;
        font-size: 8px;
    }

    .prod-img {
        width: 55px;
        height: 55px;
        object-fit: contain;
    }

    .stock {
        background: #E8F5E9;
        color: #2E7D32;
        padding: 4px 5px;
        border-radius: 3px;
        font-size: 7.6px;
        font-weight: 600;
        line-height: 1.35;
        text-align: center;
    }

    .import {
        background: #FFF3CD;
        color: #856404;
        padding: 4px 5px;
        border-radius: 3px;
        font-size: 7.6px;
        font-weight: 600;
        line-height: 1.35;
        text-align: center;
    }

    .bottom {
        /* page-break-inside: avoid; */
        padding: 2px 14px 8px;
    }

    .conditions {
        width: auto;
        padding-right: 12px;
    }

    .totals-cell {
        width: 235px;
    }

    .condition {
        font-size: 8.6px;
        color: #6B7A99;
        line-height: 1.35;
        margin-bottom: 2px;
    }

    .condition span {
        color: #42A5F5;
        font-weight: 700;
        font-size: 11px;
    }

    .note {
        margin-top: 7px;
        background: #FFF8E1;
        border-left: 3px solid #F9A825;
        padding: 5px 7px;
        font-size: 8px;
        color: #795548;
        line-height: 1.45;
    }

    .totals {
        border: 1px solid #E0E6F0;
        font-size: 9.5px;
    }

    .totals td {
        padding: 6px 10px;
        border-bottom: 1px solid #E0E6F0;
    }

    .totals .label {
        color: #6B7A99;
    }

    .totals .amount {
        text-align: right;
        font-weight: 700;
        color: #2C2C4A;
    }

    .total-final td {
        background: #1565C0;
        color: #fff;
        border-bottom: 0;
        padding: 6px 10px;
        font-weight: 600;
    }

    .total-final .total-label {
        font-size: 10px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .total-final .total-amount {
        font-size: 13px;
        text-align: right;
        font-weight: 600;
    }

    .currency td {
        background: #1565C0;
        color: rgba(255, 255, 255, 0.75);
        border-bottom: 0;
        font-size: 8px;
        text-align: right;
        padding-top: 0;
    }

    .payment {
        padding: 2px 14px 2px;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .payment-table {
        table-layout: fixed;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .payment-col {
        width: 33.33%;
        padding: 0 5px;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .payment-card {
        border: 1px solid #E0E6F0;
        border-radius: 4px;
        padding: 5px 7px;
        height: 84px;
        overflow: hidden;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .payment-table tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .pay-title {
        font-size: 7.6px;
    }

    .payment-left {
        padding-right: 8px;
    }

    .payment-right {
        width: 165px;
    }

    .payment-detraccion {
        width: 155px;
        padding: 0 8px;
    }

    .pay-logo {
        height: 32px;
        width: auto;
    }

    .pay-logo-bcp {
        height: 24px;
        width: auto;
    }

    .pay-logo-nacion {
        height: 28px;
        width: auto;
    }

    .qr-yape {
        height: 55px;
        width: 55px;
        display: block;
        margin: 0 auto;
    }

    .pay-row td {
        padding: 1px 0;
        font-size: 7px;
        line-height: 1.12;
    }

    .pay-tag {
        font-weight: 700;
        color: #2C2C4A;
        white-space: nowrap;
        width: 36px;
    }

    .pay-num {
        padding-left: 4px !important;
        font-weight: 700;
        font-size: 7px;
    }

    .yape {
        text-align: center;
    }

    .yape-number {
        font-size: 10px;
        font-weight: 700;
    }

    .yape-card {
        text-align: center;
    }

    .yape-qr-wrap {
        text-align: center;
        margin-bottom: 2px;
    }

    .yape-bottom {
        width: 100%;
        table-layout: fixed;
    }

    .yape-logo-cell {
        width: 40%;
        text-align: right;
        vertical-align: middle;
        padding-right: 4px;
    }

    .yape-data-cell {
        width: 60%;
        text-align: left;
        vertical-align: middle;
    }

    .signature-img {
        height: 62px;
        width: auto;
        display: block;
        margin: 0 auto 1px;
    }

    .signature {
        padding: 4px 14px 0;
        /* page-break-inside: avoid; */
    }

    .signature-cell {
        width: 42%;
        text-align: center;
        padding: 0 20px 6px;
    }

    .signature-line {
        border-top: 1.2px solid #1A1A2E;
        width: 150px;
        margin: 0 auto;
    }

    .signature-date {
        font-size: 8.8px;
    }

    .left-strip-fixed {
        position: fixed;
        top: -20px;
        left: -24px;
        bottom: -50px;
        width: 8px;
        background: #1565C0;
    }

    .strip {
        display: none;
    }

    @page {
        margin: 12px 16px 62px 24px;
    }

    .footer {
        position: fixed;
        bottom: -58px;
        left: -24px;
        width: 110%;
        height: 40px;
        background: #1565C0;
        color: #ffffff;
    }

    .footer td {
        /* padding: 4px 6px;
        font-size: 8.8px; */
        color: #ffffff;
        vertical-align: middle;
    }

    .footer-notch {
        position: fixed;
        bottom: -8px;
        left: 335px;
        width: 120px;
        height: 18px;
        background: #1565C0;
        text-align: center;
    }

    .footer-notch:before {
        content: "";
        position: absolute;
        left: -12px;
        top: 0;
        border-right: 12px solid #1565C0;
        border-top: 18px solid transparent;
    }

    .footer-notch:after {
        content: "";
        position: absolute;
        right: -12px;
        top: 0;
        border-left: 12px solid #1565C0;
        border-top: 18px solid transparent;
    }

    .footer-notch img {
        height: 25px;
        width: auto;
        margin-top: 2px;
    }

    /* .footer-logo-row {
        height: 13px;
        text-align: center;
    }

    .footer-logo-row td {
        padding: 0;
    } */
    /* 
    .footer-logo {
        height: 12px;
        width: auto;
    } */

    .footer-icon {
        font-weight: 700;
        margin-right: 4px;
    }

    /* .footer-info-row {
        height: 45px;
    } */
    /* 
    .footer-top-logo {
        position: fixed;
        bottom: -12px;
        left: 0;
        right: 0;
        text-align: center;
    }

    .footer-top-logo img {
        height: 16px;
        width: auto;
    } */

    .footer-item {
        padding: 9px 8px 5px;
        vertical-align: middle;
    }

    .footer-icon-img {
        width: 22px;
        height: 22px;
        vertical-align: middle;
        /* margin-right: 4px; */
    }

    .footer-text {
        display: inline-block;
        vertical-align: middle;
        margin-left: 5px;
    }

    .footer-label {
        display: block;
        font-weight: 700;
        font-size: 8px;
        /* letter-spacing: .5px; */
        color: #ffffff;
        line-height: 1;
        /* margin-bottom: 1px; */
    }

    .footer-value {
        font-size: 7.2px;
        line-height: 1.1;
        display: block;
        color: #fff;
    }

    .footer-sep {
        width: 1px;
        border-left: 1px solid rgba(255, 255, 255, .45);
    }

    .items tr {
        page-break-inside: avoid !important;
    }

    .items thead {
        display: table-header-group;
    }

    /* Un producto puede continuar en otra página */
    .items tbody.item-group {
        page-break-inside: auto !important;
    }

    /*
 * Estas celdas simulan el rowspan:
 * #, Producto, Imagen y Disponibilidad.
 */
    .items .item-merge-cell {
        border-bottom: none !important;
        vertical-align: middle !important;
    }

    /* Las filas siguientes quedan vacías en esas columnas */
    .items .item-merge-empty {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    /* La última fila vuelve a cerrar visualmente el bloque */
    .items tr.item-row-last .item-merge-cell {
        border-bottom: 1px solid #E0E6F0 !important;
    }

    .partners {
        margin-top: 2px;
        margin-bottom: 1px;
    }

    .partners td {
        vertical-align: middle;
    }

    .partners-line {
        width: 30%;
        border-top: 1.2px solid #1565C0;
    }

    .partners-title {
        width: 40%;
        text-align: center;
        color: #1565C0;
        font-size: 7.5px;
        font-weight: 700;
        letter-spacing: 2px;
        white-space: nowrap;
        padding: 2px 0 4px
    }

    .partners-logos-table {
        width: 92%;
        margin: 0 auto 4px;
        table-layout: fixed;
    }

    .partner-logo-cell {
        text-align: center;
        vertical-align: middle;
        height: 24px;
        line-height: 24px;
        width: 19.6%;
    }

    .partner-separator {
        width: 0.5px;
        border-left: 1px solid #DDE6F2;
    }

    .partner-logo {
        max-height: 19px;
        max-width: 78px;
        width: auto;
        vertical-align: middle;
        display: inline-block;
    }

    .partner-hp {
        max-height: 22px;
    }

    .partner-lenovo {
        max-height: 19px;
    }

    .partner-dell {
        max-height: 20px;
    }

    .partner-microsoft {
        max-height: 20px;
    }

    .partner-synology {
        max-height: 18px;
    }

    .partners-line,
    .partners-line-left,
    .partners-line-right {
        border: none !important;
        height: 0 !important;
    }

    .homologados-table {
    width: 92%;
    margin: 0 auto;
    table-layout: fixed;
    }

    .homologado-cell {
        width: 45%;
        text-align: center;
        vertical-align: middle;
        padding: 0
    }

    .homologado-cell-left {
        width: 52%;

        text-align: center;
        vertical-align: middle;

        padding: 0;
    }

    .homologado-separator {
        width: 8%;

        text-align: center;
        vertical-align: middle;
    }

    .homologado-cell-right {
        width: 40%;

        text-align: center;
        vertical-align: middle;

        padding: 0;
    }

    .homologado-separator-inner {
        width: 1px;
        height: 60px;

        margin: 0 auto;

        border-left: 1px solid #DDE6F2;
    }

    .logo-homologado-main {
        width: auto;
        height: auto;
        vertical-align: middle;
        display:inline-block;
    }
</style>

<div class="left-strip-fixed"></div>

<div class="body">
    <table class="header" cellpadding="0" cellspacing="0">
        <tr>
            <td class="header-left-new">
                @if($logoWillatecSrc = $pdfImage($logoWillatec))
                <img src="{{ $logoWillatecSrc }}" class="logo-willatec" alt="Willatec">
                @endif
            </td>

            <td class="header-center-new">

                <div class="header-center-box">

                    <div class="tag">
                        ¡SOMOS PROVEEDOR HOMOLOGADO!
                    </div>

                    <table
                        class="homologados-table"
                        cellpadding="0"
                        cellspacing="0"
                    >
                        <tr>

                            <td class="homologado-cell-left">

                                @if($logoHomologado2Src = $pdfImage($logoHomologado2))
                                    <img
                                        src="{{ $logoHomologado2Src }}"
                                        class="logo-homologado-main logo-homologado-left"
                                        alt="Homologado"
                                    >
                                @endif

                            </td>

                            <td class="homologado-separator">
                                <div class="homologado-separator-inner"></div>
                            </td>

                            <td class="homologado-cell-right">

                                @if($logoHomologadoSrc = $pdfImage($logoHomologado))
                                    <img
                                        src="{{ $logoHomologadoSrc }}"
                                        class="logo-homologado-main logo-homologado-right"
                                        alt="Homologado"
                                    >
                                @endif

                            </td>

                        </tr>
                    </table>

                </div>

            </td>

            <td class="header-right-new">
                <div class="title">COTIZACIÓN</div>

                <table class="meta" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>Nro. Cotización:</td>
                        <td class="value">{{ $cotizacion->numero }}</td>
                    </tr>
                    <tr>
                        <td>Fecha emisión:</td>
                        <td class="value">{{ $fechaEmision }}</td>
                    </tr>
                    <tr>
                        <td>Válido hasta:</td>
                        <td class="value">{{ $fechaValidez }}</td>
                    </tr>
                    <tr>
                        <td>Vendedor:</td>
                        <td class="value">{{ $primerNombre }} {{ $primerApellido }}</td>
                    </tr>
                </table>

                <span class="badge">Válido {{ $validezDias }} días calendario</span>
            </td>
        </tr>
    </table>
    @if($cotizacion->items->count() <= 3)
        <table class="partners" cellpadding="0" cellspacing="0">
        <tr>
            <td class="partners-line"></td>
            <td class="partners-title">NUESTROS ALIADOS TECNOLÓGICOS</td>
            <td class="partners-line"></td>
        </tr>
        </table>

        <table class="partners-logos-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="partner-logo-cell">
                    @if($logoMicrosoftSrc = $pdfImage($logoMicrosoft))
                    <img src="{{ $logoMicrosoftSrc }}" class="partner-logo partner-microsoft">
                    @endif
                </td>
                <td class="partner-separator"></td>
                <td class="partner-logo-cell">
                    @if($logoDellSrc = $pdfImage($logoDell))
                    <img src="{{ $logoDellSrc }}" class="partner-logo partner-dell">
                    @endif
                </td>
                <td class="partner-separator"></td>
                <td class="partner-logo-cell">
                    @if($logoHpSrc = $pdfImage($logoHp))
                    <img src="{{ $logoHpSrc }}" class="partner-logo partner-hp">
                    @endif
                </td>
                <td class="partner-separator"></td>
                <td class="partner-logo-cell">
                    @if($logoSynologySrc = $pdfImage($logoSynology))
                    <img src="{{ $logoSynologySrc }}" class="partner-logo partner-synology">
                    @endif
                </td>
                <td class="partner-separator"></td>
                <td class="partner-logo-cell">
                    @if($logoLenovoSrc = $pdfImage($logoLenovo))
                    <img src="{{ $logoLenovoSrc }}" class="partner-logo partner-lenovo">
                    @endif
                </td>
            </tr>
        </table>
        @endif
        <table class="info" cellpadding="0" cellspacing="0">
            <tr>
                <td class="info-cell info-left">
                    <div class="info-content-left">
                        <div class="section-title">Datos del cliente</div>
                        <div class="name">{{ $cotizacion->cliente_nombre ?? 'Cliente' }}</div>
                        <div class="detail">
                            RUC / DNI: <b>{{ $cotizacion->cliente_ruc ?? '-' }}</b><br>
                            Contacto: <b>{{ $cotizacion->cliente_contacto ?? '-' }}</b> &nbsp; Teléfono: <b>{{ $cotizacion->cliente_telefono ?? '-' }}</b><br>
                            Correo: <b>{{ $cotizacion->cliente_correo ?? '-' }}</b><br>
                            Asunto: <b>{{ $cotizacion->titulo ?? '-' }}</b>
                        </div>
                    </div>
                </td>
                <td class="info-cell">
                    <div class="section-title">Datos del emisor</div>
                    <div class="name">WILLATEC S.A.C</div>
                    <div class="detail">
                        RUC: <b>20602503331</b><br>
                        WhatsApp: <b>{{ $whatsappEmisor }}</b> &nbsp; Teléfono: <b>(01) 757-1253</b><br>
                        Correo: <b>ventas@willatec.com</b> &nbsp; Web: <b>www.willatec.com</b><br>
                        Dirección: <b>{{ $direccionEmpresaDetalle }}</b>
                    </div>
                </td>
            </tr>
        </table>

        <div class="items-wrap">
            <table class="items" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th class="center" style="width:20px">#</th>
                        <th>Producto / Servicio</th>
                        <th class="center" style="width:52px">Imagen</th>
                        @if($esMultidestino)
                        <th class="center" style="width:70px">Destino</th>
                        @endif
                        <th class="right" style="width:30px">Cant.</th>
                        <th class="center" style="width:36px">Und.</th>
                        <th class="right" style="width:68px">P. Unit.</th>
                        <th class="right" style="width:68px">Subtotal</th>
                        <th class="center" style="width:86px">Disponibilidad</th>
                    </tr>
                </thead>
                @forelse($cotizacion->items as $item)

                @php
                $itemImage = null;

                if ($item->imagen) {

                $imagen = ltrim(
                str_replace('\\', '/', $item->imagen),
                '/'
                );

                foreach (
                [
                'storage/app/public/',
                'public/storage/',
                'storage/'
                ] as $prefix
                ) {

                if (str_starts_with($imagen, $prefix)) {

                $imagen = substr(
                $imagen,
                strlen($prefix)
                );

                break;
                }

                }

                $rutaLocal = public_path(
                'storage/' . $imagen
                );

                if (file_exists($rutaLocal)) {
                $itemImage = $rutaLocal;
                }
                }

                $itemImageSrc =
                $itemImage
                ? $pdfImage($itemImage)
                : null;


                $destinosItem =
                $esMultidestino
                && $item->destinosEntrega->isNotEmpty()

                ? $item->destinosEntrega

                : collect([
                (object) [
                'destino_entrega' =>
                $item->destino_entrega
                ?: 'Lima Metropolitana',

                'detalle_variante' => null,

                'cantidad' =>
                $item->cantidad,

                'precio_venta' =>
                $item->precio_venta,

                'subtotal' =>
                $item->subtotal,
                ]
                ]);


                /*
                * Guardamos estos valores antes de entrar
                * al foreach de destinos.
                *
                * Dentro del foreach, $loop ya será
                * el loop de los destinos.
                */
                $itemNumero = $loop->iteration;
                $itemEsPar = $loop->even;

                @endphp


                <tbody class="item-group">

                    @foreach($destinosItem as $destino)

                    <tr
                        class="
                    {{ $itemEsPar ? 'even' : '' }}
                    {{ $loop->last ? 'item-row-last' : '' }}
                ">

                        {{-- ==================================
                     #
                =================================== --}}

                        <td
                            class="
                        center
                        item-merge-cell
                        {{ !$loop->first ? 'item-merge-empty' : '' }}
                    ">

                            @if($loop->first)

                            {{ $itemNumero }}

                            @endif

                        </td>


                        {{-- ==================================
                     PRODUCTO / SERVICIO
                =================================== --}}

                        <td
                            class="
                        item-merge-cell
                        {{ !$loop->first ? 'item-merge-empty' : '' }}
                    ">

                            @if($loop->first)

                            <span class="strong">
                                {{ $item->descripcion }}
                            </span>

                            <br>


                            @if(!empty($item->nota))

                            @php

                            $notaItemHtml =
                            str_replace(

                            [
                            '&lt;strong&gt;',
                            '&lt;/strong&gt;',
                            '&lt;b&gt;',
                            '&lt;/b&gt;',
                            '&lt;em&gt;',
                            '&lt;/em&gt;',
                            '&lt;i&gt;',
                            '&lt;/i&gt;',
                            '&lt;u&gt;',
                            '&lt;/u&gt;'
                            ],

                            [
                            '<strong>',
                                '</strong>',
                            '<strong>',
                                '</strong>',
                            '<em>',
                                '</em>',
                            '<em>',
                                '</em>',
                            '<u>',
                                '</u>'
                            ],

                            nl2br(
                            e($item->nota)
                            )
                            );

                            @endphp


                            <span class="muted">

                                Nota:
                                {!! $notaItemHtml !!}

                            </span>

                            <br>

                            @endif


                            @if($item->marca)

                            <span class="muted">

                                Marca:
                                {{ $item->marca }}

                            </span>

                            <br>

                            @endif


                            @php
                                $identificadorItem = trim((string) ($item->modelo ?: $item->codigo));
                                $identificadorLabel = filled($item->modelo) ? 'Modelo' : 'Código';
                            @endphp

                            @if($identificadorItem !== '' && $identificadorItem !== '-')

                            <span class="muted">

                                {{ $identificadorLabel }}:
                                {{ $identificadorItem }}

                            </span>

                            <br>

                            @endif


                            <span class="muted">

                                Garantia:
                                {{ $item->garantia_meses ?? '-' }}
                                meses

                            </span>

                            @endif

                        </td>


                        {{-- ==================================
                     IMAGEN
                =================================== --}}

                        <td
                            class="
                        center
                        item-merge-cell
                        {{ !$loop->first ? 'item-merge-empty' : '' }}
                    ">

                            @if($loop->first)

                            @if($itemImageSrc)

                            <img
                                src="{{ $itemImageSrc }}"
                                class="prod-img"
                                alt="">

                            @else

                            <span class="muted">
                                Sin imagen
                            </span>

                            @endif

                            @endif

                        </td>


                        {{-- ==================================
                     DESTINO
                =================================== --}}

                        @if($esMultidestino)

                        <td class="center">

                            <span class="muted">

                                {{
                                $destino->destino_entrega
                                ?: 'Lima Metropolitana'
                            }}

                            </span>


                            @if(
                            !empty(
                            $destino->detalle_variante
                            )
                            )

                            <br>

                            <span
                                style="
                                    font-size:7.4px;
                                    color:#374151;
                                ">

                                {{
                                    $destino
                                        ->detalle_variante
                                }}

                            </span>

                            @endif

                        </td>

                        @endif


                        {{-- ==================================
                     CANTIDAD
                =================================== --}}

                        <td class="right">

                            {{ $destino->cantidad }}

                        </td>

                        <td class="center">

                            {{ $item->unidad_medida ?: 'UND' }}

                        </td>


                        {{-- ==================================
                     PRECIO UNITARIO
                =================================== --}}

                        <td class="right">

                            {{ $simbolo }}
                            {{
                        number_format(
                            (float)
                            $destino->precio_venta,
                            2
                        )
                    }}

                        </td>


                        {{-- ==================================
                     SUBTOTAL
                =================================== --}}

                        <td class="right strong">

                            {{ $simbolo }}
                            {{
                        number_format(
                            (float)
                            $destino->subtotal,
                            2
                        )
                    }}

                        </td>


                        {{-- ==================================
                     DISPONIBILIDAD
                =================================== --}}

                        <td
                            class="
                        center
                        item-merge-cell
                        {{ !$loop->first ? 'item-merge-empty' : '' }}
                    ">

                            @if($loop->first)

                            @if(
                            $item->disponibilidad_tipo
                            === 'importacion'
                            )

                            <div class="import">

                                IMPORTACION

                                <br>

                                {{
                                    $item
                                        ->disponibilidad_dias
                                }}
                                dias c.

                                <br>

                                Puesta la OC.

                            </div>

                            @else

                            <div class="stock">

                                STOCK DISP.

                                <br>

                                {{
                                    $item
                                        ->disponibilidad_dias
                                }}
                                dias c.

                                <br>

                                Puesta la OC.

                            </div>

                            @endif

                            @endif

                        </td>

                    </tr>

                    @endforeach

                </tbody>


                @empty

                <tbody>

                    <tr>

                        <td
                            colspan="{{ $esMultidestino ? 9 : 8 }}"
                            class="center muted">

                            Sin items registrados

                        </td>

                    </tr>

                </tbody>

                @endforelse
            </table>
        </div>

        <table class="bottom" cellpadding="0" cellspacing="0">
            <tr>
                <td class="conditions">
                    <div class="section-title">Condiciones comerciales</div>
                    <div class="condition"><span>&rsaquo;</span> Forma de Pago: {{ $formaPago }}{{ $formaPagoCalendario }}</div>
                    <div class="condition"><span>&rsaquo;</span> {{ $condicionEntrega }}</div>
                    <div class="condition"><span>&rsaquo;</span> Precios en {{ $nombreMoneda }} y {{ $textoIgv }}.</div>
                    <div class="condition"><span>&rsaquo;</span> Precios sujetos a cambio sin previo aviso.</div>
                    <div class="condition"><span>&rsaquo;</span> WILLATEC S.A.C, Incorporado al Régimen de Buenos Contribuyentes; Resolución de Intendencia N° 0230050266292.</div>
                    <div class="note">
                        El precio del producto esta sujeto al stock y nuevo ingreso de importacion de la marca.<br>
                        Para productos importados, el tiempo de entrega puede variar entre 20 y 25 dias calendario.
                    </div>
                </td>
                <td class="totals-cell">
                    <table class="totals" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="label">Subtotal</td>
                            <td class="amount">{{ $simbolo }} {{ number_format((float) $cotizacion->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="label">IGV (18%)</td>
                            <td class="amount">{{ $simbolo }} {{ number_format((float) $cotizacion->igv, 2) }}</td>
                        </tr>
                        <tr class="total-final">
                            <td class="total-label">Total</td>
                            <td class="total-amount">{{ $simbolo }} {{ number_format((float) $cotizacion->total, 2) }}</td>
                        </tr>
                        @if($cotizacion->cuota_mensual)
                              <tr>
                                  <td colspan="2" style="border: 1px solid #B9CBE5; background: #F2F7FC; color: #1F3B63; font-weight: 700; text-align: center; padding: 7px 8px;">Cuota Mensual: {{ $simbolo }} {{ number_format((float) $cotizacion->total / max(1, (int) ($cotizacion->cuota_mensual_meses ?? 12)), 2) }} {{ $textoCuotaIgv }} &nbsp;|&nbsp; Periodo: {{ (int) ($cotizacion->cuota_mensual_meses ?? 12) }} meses</td>
                            </tr>
                        @endif
                        <tr class="currency">
                            <td colspan="2">Moneda: {{ $codigoMoneda }} {{ $simbolo }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <div class="signature">
            <table cellpadding="0" cellspacing="0">
                <tr>
                    <td class="signature-cell">
                        @if($firmaGerenteSrc = $pdfImage($firmaGerente))
                        <img src="{{ $firmaGerenteSrc }}" class="signature-img" alt="Firma">
                        @else
                        <div style="height:58px">&nbsp;</div>
                        @endif
                        <div class="signature-line"></div>
                        <div class="signature-date">Fecha: {{ $fechaEmision }}</div>
                    </td>
                    <td class="signature-cell">&nbsp;</td>
                </tr>
            </table>
        </div>

        <div class="payment">
            <div class="section-title">Metodo de pago</div>

            <table class="payment-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="payment-col">
                        <div class="payment-card">
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="pay-title">Cuenta corriente</td>
                                    <td style="text-align:right">
                                        @if($logoBcpSrc = $pdfImage($logoBcp))
                                        <img src="{{ $logoBcpSrc }}" class="pay-logo-bcp" alt="BCP">
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <table class="pay-row" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="pay-tag">US $.</td>
                                    <td class="pay-num">193-2421813-1-66</td>
                                </tr>
                                <tr>
                                    <td class="pay-tag">CCI:</td>
                                    <td class="pay-num">00219300242181316612</td>
                                </tr>
                                <tr>
                                    <td class="pay-tag">S/.</td>
                                    <td class="pay-num">191-2494330-0-51</td>
                                </tr>
                                <tr>
                                    <td class="pay-tag">CCI:</td>
                                    <td class="pay-num">00219100249433005157</td>
                                </tr>
                            </table>
                        </div>
                    </td>

                    <td class="payment-col">
                        <div class="payment-card yape">
                            <div class="pay-title">Cuenta detracción</div>

                            @if($logoBancoNacionSrc = $pdfImage($logoBancoNacion))
                            <img src="{{ $logoBancoNacionSrc }}" class="pay-logo-nacion" alt="Banco de la Nación">
                            @else
                            <div class="strong">Banco de la Nación</div>
                            @endif

                            <div class="muted" style="margin-top:8px;">Número:</div>
                            <div class="yape-number" style="font-size:11px;">
                                00-012-043538
                            </div>
                        </div>
                    </td>

                    <td class="payment-col">
                        <div class="payment-card yape yape-card">
                            @if($qrYapeSrc = $pdfImage($qrYape))
                            <div class="yape-qr-wrap">
                                <img src="{{ $qrYapeSrc }}" class="qr-yape" alt="QR Yape">
                            </div>
                            @endif

                            <table class="yape-bottom" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="yape-logo-cell">
                                        @if($logoYapeSrc = $pdfImage($logoYape))
                                        <img src="{{ $logoYapeSrc }}" class="pay-logo" alt="Yape">
                                        @endif
                                    </td>
                                    <td class="yape-data-cell">
                                        <div class="muted">Willatec Sac</div>
                                        <div class="yape-number">905431917</div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
</div>

<table class="footer" cellpadding="0" cellspacing="0">
    <tr>
        <td class="footer-item" style="width:22%">
            @if($iconPhoneSrc = $pdfImage($iconPhone))
            <img src="{{ $iconPhoneSrc }}" class="footer-icon-img">
            @endif
            <span class="footer-text">
                <span class="footer-label">Telefono:</span>
                <span class="footer-value">(01) 757-1253</span>
            </span>
        </td>

        <td class="footer-sep"></td>

        <td class="footer-item" style="width:25%">
            @if($iconMailSrc = $pdfImage($iconMail))
            <img src="{{ $iconMailSrc }}" class="footer-icon-img">
            @endif
            <span class="footer-text">
                <span class="footer-label">Correo:</span>
                <span class="footer-value">ventas@willatec.com</span>
            </span>
        </td>

        <td class="footer-sep"></td>

        <td class="footer-item" style="width:20%">
            @if($iconWebSrc = $pdfImage($iconWeb))
            <img src="{{ $iconWebSrc }}" class="footer-icon-img">
            @endif
            <span class="footer-text">
                <span class="footer-label">Web:</span>
                <span class="footer-value">www.willatec.com</span>
            </span>
        </td>

        <td class="footer-sep"></td>

        <td class="footer-item" style="width:33%">
            @if($iconMapSrc = $pdfImage($iconMap))
            <img src="{{ $iconMapSrc }}" class="footer-icon-img">
            @endif
            <span class="footer-text">
                <span class="footer-label">Dirección:</span>
                <span class="footer-value">{{ $direccionEmpresaFooter }}</span>
        </td>
    </tr>
</table>
@if($logoFooterSrc = $pdfImage($logoFooter))
<div class="footer-notch">
    <img src="{{ $logoFooterSrc }}" alt="Willatec">
</div>
@endif
@endsection
