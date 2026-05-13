@extends('pdf.layouts.pdf-base')

@section('content')

@php
use Carbon\Carbon;
$fechaEmision = Carbon::parse($cotizacion->fecha)->format('d/m/Y');
$fechaValidez = Carbon::parse($cotizacion->fecha)
->addDays($cotizacion->validez_dias ?? 10)
->format('d/m/Y');
$simbolo = $cotizacion->moneda == 'USD' ? '$' : 'S/';
@endphp

<style>
    /* ══════════════════════════════
    VARIABLES DE COLOR
    ══════════════════════════════ */
    * {
        box-sizing: border-box;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #2C2C4A;
        font-size: 12px;
        margin: 0;
        padding: 0;
        background: #fff;
    }

    tr {
    page-break-inside: avoid;
    }

    /* ── WRAPPER CON FRANJA LATERAL ── */
    /*
     * DomPDF no soporta ::before, así que la franja
     * la hacemos con una celda real de tabla.
     */
    .page-wrapper {
        width: 100%;
        border-collapse: collapse;
    }

    .franja-lateral {
        width: 6px;
        background: #1565C0;
        /* DomPDF acepta background en td */
    }

    .page-content {
        padding: 0;
    }

    /* ══════════════════════════════
    HEADER
    ══════════════════════════════ */
    .header-table {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 2px solid #E3F2FD;
        padding: 0;
    }

    .header-logo-cell {
        width: 55%;
        vertical-align: top;
        padding: 28px 16px 22px 20px;
    }

    .header-right-cell {
        width: 45%;
        vertical-align: top;
        text-align: right;
        padding: 28px 20px 22px 16px;
    }

    .logo-shape {
        display: inline-block;
        width: 38px;
        height: 38px;
        background: #1565C0;
        border-radius: 8px;
        color: white;
        font-size: 16px;
        font-weight: 700;
        text-align: center;
        line-height: 38px;
        vertical-align: middle;
        margin-right: 8px;
    }

    .company-name {
        font-size: 20px;
        font-weight: 700;
        color: #1A1A2E;
        letter-spacing: 1px;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .company-tagline {
        font-size: 10px;
        color: #6B7A99;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-top: 4px;
    }

    .doc-title {
        font-size: 36px;
        font-weight: 700;
        color: #1565C0;
        letter-spacing: 3px;
        line-height: 1;
        margin-bottom: 10px;
    }

    .meta-table {
        width: auto;
        margin-left: auto;
        border-collapse: collapse;
        font-size: 11px;
    }

    .meta-table td {
        padding: 1px 4px;
        color: #6B7A99;
    }

    .meta-table td.meta-val {
        text-align: right;
        color: #2C2C4A;
        font-weight: 600;
    }

    .validez-badge {
        display: inline-block;
        background: #E3F2FD;
        color: #1565C0;
        font-size: 10px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 12px;
        margin-top: 7px;
    }

    /* ══════════════════════════════
    INFO CLIENTE / EMISOR
    ══════════════════════════════ */
    .info-table {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 1px solid #E0E6F0;
    }

    .info-cell {
        width: 50%;
        vertical-align: top;
        padding: 18px 20px;
    }

    .info-cell-left {
        border-right: 1px solid #E0E6F0;
        padding-left: 20px;
    }

    .info-cell-right {
        padding-left: 24px;
    }

    .info-label-text {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #1565C0;
        border-bottom: 1px solid #E3F2FD;
        padding-bottom: 5px;
        margin-bottom: 8px;
    }

    .cliente-nombre {
        font-size: 15px;
        font-weight: 700;
        color: #1A1A2E;
        margin-bottom: 5px;
    }

    .info-detail {
        font-size: 11px;
        color: #6B7A99;
        line-height: 1.8;
    }

    .info-detail-val {
        color: #2C2C4A;
        font-weight: 500;
    }

    /* ══════════════════════════════
    TABLA DE PRODUCTOS
    ══════════════════════════════ */
    .tabla-section {
        padding: 18px 20px;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11.5px;
        page-break-inside: auto;
    }

    .items-table thead tr {
        background: #1565C0;
        color: white;
    }

    .items-table thead th {
        padding: 9px 12px;
        font-weight: 600;
        font-size: 10px;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        text-align: left;
    }

    .items-table thead th.th-center {
        text-align: center;
    }

    .items-table thead th.th-right {
        text-align: right;
    }

    .items-table tbody tr {
        border-bottom: 1px solid #E0E6F0;
    }

    /* DomPDF soporta nth-child */
    .items-table tbody tr.row-even {
        background: #F5F7FA;
    }

    .items-table tbody td {
        padding: 9px 12px;
        vertical-align: middle;
        color: #2C2C4A;
    }

    .td-num {
        text-align: center;
        color: #6B7A99;
        font-size: 11px;
    }

    .td-right {
        text-align: right;
    }

    .td-total {
        text-align: right;
        font-weight: 700;
        color: #1A1A2E;
    }

    .td-strong {
        font-weight: 600;
        font-size: 12px;
        color: #1A1A2E;
    }

    .td-sub {
        font-size: 10px;
        color: #6B7A99;
    }

    /* Imagen producto */
    .prod-img {
        width: 50px;
        height: 50px;
        object-fit: contain;
        display: block;
        margin: 0 auto;
    }

    /* ══════════════════════════════
    BOTTOM: CONDICIONES + TOTALES
    ══════════════════════════════ */
    .bottom-table {
        width: 100%;
        border-collapse: collapse;
        padding: 0 20px;
        page-break-inside: avoid;
    }

    .bottom-left {
        vertical-align: top;
        padding: 16px 20px;
    }

    .bottom-right {
        width: 280px;
        vertical-align: top;
        padding: 16px 20px 16px 0;
    }

    .condiciones-title {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #1565C0;
        border-bottom: 1px solid #E3F2FD;
        padding-bottom: 5px;
        margin-bottom: 8px;
    }

    .condicion-item {
        font-size: 11px;
        color: #6B7A99;
        line-height: 1.85;
        margin-bottom: 2px;
    }

    .condicion-item::before {
        content: '> ';
        color: #42A5F5;
        font-weight: 700;
    }

    /* DomPDF soporta ::before en algunos contextos,
       pero para mayor seguridad usamos prefijo inline */
    .cond-prefix {
        color: #42A5F5;
        font-weight: 700;
        margin-right: 4px;
    }

    .notas-box {
        margin-top: 12px;
        background: #FFF8E1;
        border-left: 3px solid #F9A825;
        padding: 8px 11px;
    }

    .notas-title {
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #F57F17;
        margin-bottom: 3px;
    }

    .notas-text {
        font-size: 10.5px;
        color: #795548;
        line-height: 1.6;
    }

    /* Totales */
    .totales-box {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #E0E6F0;
    }

    .totales-box td {
        padding: 8px 14px;
        font-size: 12px;
        border-bottom: 1px solid #E0E6F0;
        color: #6B7A99;
    }

    .totales-box td.tot-label {
        text-align: left;
    }

    .totales-box td.tot-val {
        text-align: right;
        font-weight: 600;
        color: #2C2C4A;
    }

    .totales-box td.tot-desc {
        color: #388E3C;
        font-weight: 600;
        text-align: right;
    }

    .total-final-row td {
        background: #1565C0;
        color: white;
        font-weight: 700;
        padding: 12px 14px;
        border-bottom: none;
    }

    .total-final-row td.tf-label {
        font-size: 14px;
        letter-spacing: 2px;
        text-transform: uppercase;
        text-align: left;
        color: white;
    }

    .total-final-row td.tf-amount {
        font-size: 20px;
        text-align: right;
        color: white;
    }

    .moneda-row td {
        background: #1565C0;
        color: rgba(255, 255, 255, 0.7);
        font-size: 9.5px;
        text-align: right;
        padding: 3px 14px 7px;
        border-bottom: none;
    }

    /* ══════════════════════════════
    MÉTODO DE PAGO
    ══════════════════════════════ */
    .pago-section {
        padding: 0 20px 18px;
        page-break-inside: avoid;
    }

    .pago-title {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #1565C0;
        border-bottom: 1px solid #E3F2FD;
        padding-bottom: 5px;
        margin-bottom: 9px;
    }

    .pago-table {
        width: 100%;
        border-collapse: collapse;
    }

    .pago-table td {
        padding: 0 8px 0 0;
        width: 33.33%;
        vertical-align: top;
    }

    .pago-item {
        background: #F5F7FA;
        border: 1px solid #E0E6F0;
        border-radius: 5px;
        padding: 8px 11px;
    }

    .pago-key {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #6B7A99;
        margin-bottom: 2px;
    }

    .pago-val {
        font-size: 12px;
        font-weight: 600;
        color: #1A1A2E;
    }

    /* ══════════════════════════════
    FIRMAS
    ══════════════════════════════ */
    .firma-table {
        width: 100%;
        border-collapse: collapse;
        padding: 0 20px 22px;
        page-break-inside: avoid;
    }

    .firma-cell {
        width: 50%;
        text-align: center;
        vertical-align: bottom;
        padding: 0 24px 22px;
    }

    .firma-espacio {
        height: 44px;
    }

    .firma-linea {
        border-top: 1.5px solid #1A1A2E;
        margin-bottom: 5px;
    }

    .firma-nombre {
        font-size: 12px;
        font-weight: 600;
        color: #1A1A2E;
    }

    .firma-cargo {
        font-size: 10.5px;
        color: #6B7A99;
    }

    .firma-fecha {
        font-size: 10px;
        color: #aaa;
        margin-top: 3px;
    }

    /* ══════════════════════════════
    FOOTER
    ══════════════════════════════ */
    .footer-bar {
        background: #1565C0;
        width: 100%;
        border-collapse: collapse;
    }

    .footer-bar td {
        padding: 11px 20px;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.85);
        vertical-align: middle;
    }

    .footer-icon-circle {
        display: inline-block;
        width: 15px;
        height: 15px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        text-align: center;
        line-height: 15px;
        font-size: 8px;
        color: white;
        margin-right: 5px;
        vertical-align: middle;
    }
</style>

{{-- WRAPPER PRINCIPAL (tabla para la franja lateral) --}}
<table class="page-wrapper" cellpadding="0" cellspacing="0">
    <tr>
        {{-- FRANJA LATERAL AZUL (reemplaza el ::before) --}}
        <td class="franja-lateral" style="width:6px; background:#1565C0;">&nbsp;</td>

        {{-- CONTENIDO PRINCIPAL --}}
        <td class="page-content">

            {{-- ══ HEADER ══ --}}
            <table class="header-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="header-logo-cell">
                        <span class="logo-shape">W</span>
                        <span class="company-name">WILLATEC S.A.C</span>
                        <div class="company-tagline">Soluciones digitales</div>
                    </td>
                    <td class="header-right-cell">
                        <div class="doc-title">COTIZACIÓN</div>
                        <table class="meta-table" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>N° Cotización:</td>
                                <td class="meta-val">{{ $cotizacion->numero }}</td>
                            </tr>
                            <tr>
                                <td>Fecha de emisión:</td>
                                <td class="meta-val">{{ $fechaEmision }}</td>
                            </tr>
                            <tr>
                                <td>Válido hasta:</td>
                                <td class="meta-val">{{ $fechaValidez }}</td>
                            </tr>
                            <tr>
                                <td>Vendedor:</td>
                                <td class="meta-val">{{ $cotizacion->user->nombres ?? 'N/A' }} {{ $cotizacion->user->apellidos ?? 'N/A' }}</td>
                            </tr>
                        </table>
                        <div style="text-align:right; margin-top:7px;">
                            <span class="validez-badge">Valido por {{ $cotizacion->validez_dias }} dias calendarios</span>
                        </div>
                    </td>
                </tr>
            </table>

            {{-- ══ INFO CLIENTE / EMISOR ══ --}}
            <table class="info-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="info-cell info-cell-left">
                        <div class="info-label-text">Datos del cliente</div>
                        <div class="cliente-nombre">{{ $cotizacion->cliente->nombre ?? 'Cliente Ocasional' }}</div>
                        <div class="info-detail">
                            Telefono: <span class="info-detail-val">{{ $cotizacion->cliente->telefono ?? 'N/A' }}</span><br>
                            Correo: <span class="info-detail-val">{{ $cotizacion->cliente->correo ?? 'N/A' }}</span><br>
                            RUC/DNI: <span class="info-detail-val">{{ $cotizacion->cliente->ruc ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td class="info-cell info-cell-right">
                        <div class="info-label-text">Datos del emisor</div>
                        <div class="cliente-nombre">WILLATEC S.A.C</div>
                        <div class="info-detail">
                            Direccion: <span class="info-detail-val">Jr. Jorge Chavez N° 1747 - Of.1002 - Breña - Lima</span><br>
                            RUC: <span class="info-detail-val">20602503331</span><br>
                            Telefono: <span class="info-detail-val">(01) 757-1253</span><br>
                            WhatsApp: <span class="info-detail-val">{{ $cotizacion->user->profile->telefono ?? '934-577-815' }}</span><br>
                            Correo: <span class="info-detail-val">ventas@willatec.com</span><br>
                            Web: <span class="info-detail-val">www.willatec.com</span>
                        </div>
                    </td>
                </tr>
            </table>

            {{-- ══ TABLA DE PRODUCTOS ══ --}}
            <div class="tabla-section">
                <table class="items-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="th-center" style="width:36px">Item</th>
                            <th>Producto / Servicio</th>
                            <th class="th-center" style="width:70px">Imagen</th>
                            <th class="th-right" style="width:60px">Cant.</th>
                            <th class="th-right" style="width:90px">P. Unit.</th>
                            <th class="th-right" style="width:90px">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cotizacion->items as $item)
                        <tr class="{{ $loop->even ? 'row-even' : '' }}">
                            <td class="td-num">{{ $loop->iteration }}</td>
                            <td>
                                <span class="td-strong">{{ $item->descripcion }}</span><br>
                                <span class="td-sub">Marca: {{ $item->marca }}</span>
                            </td>
                            <td class="td-right" style="text-align:center">
                                @if($item->imagen)
                                <img
                                    src="{{ public_path('storage/' . $item->imagen) }}"
                                    class="prod-img"
                                    alt="{{ $item->descripcion }}">
                                @else
                                <span style="font-size:9px;color:#aaa">Sin imagen</span>
                                @endif
                            </td>
                            <td class="td-right">{{ $item->cantidad }}</td>
                            <td class="td-right">{{ $simbolo }} {{ number_format($item->precio_venta, 2) }}</td>
                            <td class="td-total">{{ $simbolo }} {{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- ══ CONDICIONES + TOTALES ══ --}}
            <table class="bottom-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="bottom-left">
                        <div class="condiciones-title">Condiciones comerciales</div>
                        <div class="condicion-item"><span class="cond-prefix">&rsaquo;</span>Forma de Pago: Credito a 30 dias calendarios</div>
                        <div class="condicion-item"><span class="cond-prefix">&rsaquo;</span>Tiempo de entrega: {{ $cotizacion->items->first()->disponibilidad ?? '10 a 15' }} dias calendarios puesta la Orden de Compra.</div>
                        <div class="condicion-item"><span class="cond-prefix">&rsaquo;</span>Incluye entrega en oficinas del cliente, Lima Metropolitana. Para otras ubicaciones, consultar costo adicional.</div>
                        <div class="condicion-item"><span class="cond-prefix">&rsaquo;</span>Precios en Dolares Americanos (USD) y NO incluyen IGV.</div>
                        <div class="condicion-item"><span class="cond-prefix">&rsaquo;</span>Precios sujetos a cambio sin previo aviso.</div>
                        <div class="condicion-item"><span class="cond-prefix">&rsaquo;</span>WILLATEC S.A.C, incorporado al Regimen de Buenos Contribuyentes - Res. N° 0230050266292 (Sunat).</div>

                        <div class="notas-box">
                            <div class="notas-title">Notas</div>
                            <div class="notas-text">
                                El precio del producto sujeto al stock y nuevo ingreso de importacion de la marca.<br>
                                Esto aplica despues de los 05 dias calendarios de validez de la cotizacion.<br>
                                Para productos importados, el tiempo de entrega puede variar entre 20 - 25 dias calendario.
                            </div>
                        </div>
                    </td>

                    <td class="bottom-right">
                        <table class="totales-box" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="tot-label">Subtotal</td>
                                <td class="tot-val">{{ $simbolo }} {{ number_format($cotizacion->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="tot-label">IGV (18%)</td>
                                <td class="tot-val">{{ $simbolo }} {{ number_format($cotizacion->igv, 2) }}</td>
                            </tr>
                            <tr class="total-final-row">
                                <td class="tf-label">Total</td>
                                <td class="tf-amount">{{ $simbolo }} {{ number_format($cotizacion->total, 2) }}</td>
                            </tr>
                            <tr class="moneda-row">
                                <td colspan="2">Moneda: Dolares Americanos (USD)</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            {{-- ══ MÉTODO DE PAGO ══ --}}
            <div class="pago-section">
                <div class="pago-title">Metodo de pago</div>
                <table class="pago-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-right:10px">
                            <div class="pago-item">
                                <div class="pago-key">Banco</div>
                                <div class="pago-val">Banco de Credito</div>
                            </div>
                        </td>
                        <td style="padding-right:10px">
                            <div class="pago-item">
                                <div class="pago-key">N° Cta. Cte. Dolares</div>
                                <div class="pago-val">193-2421813-1-66</div>
                            </div>
                        </td>
                        <td>
                            <div class="pago-item">
                                <div class="pago-key">CCI</div>
                                <div class="pago-val">00219300242181316612</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- ══ FIRMAS ══ --}}
            <table class="firma-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="firma-cell">
                        <div class="firma-espacio"></div>
                        <img
                            src="{{ public_path('img/firma/firma_gerente.png') }}"
                            style="height:48px; width:auto; display:block; margin:0 auto 4px;"
                            alt="Firma Gerente General">
                        <div class="firma-linea"></div>
                        <div class="firma-nombre">Luis Angel Lopez Salazar</div>
                        <div class="firma-cargo">Gerente General - Willatec S.A.C</div>
                        <div class="firma-fecha">Fecha: {{ $fechaEmision }}</div>
                    </td>
                    <!-- <td class="firma-cell">
                        {{-- segunda firma deshabilitada, descomenta si la necesitas --}}
                        {{-- <div class="firma-espacio"></div>
                        <div class="firma-linea"></div>
                        <div class="firma-nombre">{{ $cotizacion->cliente->nombre ?? '' }}</div>
                        <div class="firma-cargo">Nombre y firma del cliente</div>
                        <div class="firma-fecha">Fecha: {{ $fechaEmision }}</div> --}}
                    </td> -->
                </tr>
            </table>

            {{-- ══ FOOTER ══ --}}
            <table class="footer-bar" cellpadding="0" cellspacing="0">
                <tr>
                    <td>(01) 757-1253</td>
                    <td>ventas@willatec.com</td>
                    <td>www.willatec.com</td>
                    <td>Jr. Jorge Chavez N° 1747 - Of.1002 - Breña - Lima</td>
                </tr>
            </table>

        </td>{{-- fin .page-content --}}
    </tr>
</table>

@endsection