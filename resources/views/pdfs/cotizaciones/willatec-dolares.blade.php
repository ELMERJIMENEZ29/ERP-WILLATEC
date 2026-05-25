@extends('pdfs.layouts.pdf-base')

@section('content')

@php
use Carbon\Carbon;
$fechaEmision = Carbon::parse($cotizacion->fecha)->format('d/m/Y');
$fechaValidez = Carbon::parse($cotizacion->fecha)
    ->addDays($cotizacion->validez_dias ?? 10)
    ->format('d/m/Y');
$simbolo = $cotizacion->moneda->simbolo ?? '$';
@endphp

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: DejaVu Sans, sans-serif;
    color: #2C2C4A;
    font-size: 11px;
    background: #fff;
}

table { border-collapse: collapse; }
tr    { page-break-inside: avoid; }
thead { display: table-header-group; }

/* ── FRANJA + WRAPPER ── */
.wrap  { width: 100%; border-collapse: collapse; }
.strip { width: 6px; background: #1565C0; }
.body  { padding: 0; vertical-align: top; }

/* ── HEADER ── */
.hd        { width: 100%; border-bottom: 2px solid #E3F2FD; }
.hd-logo   { width: 55%; vertical-align: top; padding: 24px 16px 18px 18px; }
.hd-right  { width: 45%; vertical-align: top; text-align: right; padding: 24px 18px 18px 16px; }

.logo-sq {
    display: inline-block;
    width: 34px; height: 34px;
    background: #1565C0;
    border-radius: 6px;
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    text-align: center;
    line-height: 34px;
    vertical-align: middle;
    margin-right: 7px;
}
.co-name  { font-size: 18px; font-weight: 700; color: #1A1A2E; letter-spacing: 1px; text-transform: uppercase; vertical-align: middle; }
.co-tag   { font-size: 9px; color: #6B7A99; letter-spacing: 2px; text-transform: uppercase; margin-top: 3px; }
.doc-title{ font-size: 32px; font-weight: 700; color: #1565C0; letter-spacing: 3px; line-height: 1; margin-bottom: 9px; }

.meta     { width: auto; margin-left: auto; border-collapse: collapse; font-size: 10.5px; }
.meta td  { padding: 1px 4px; color: #6B7A99; }
.meta .v  { text-align: right; color: #2C2C4A; font-weight: 600; }

.badge {
    display: inline-block;
    background: #E3F2FD;
    color: #1565C0;
    font-size: 9.5px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 10px;
    margin-top: 6px;
}

/* ── INFO CLIENTE/EMISOR ── */
.info      { width: 100%; border-bottom: 1px solid #E0E6F0; }
.info-l    { width: 50%; vertical-align: top; padding: 15px 16px 15px 18px; border-right: 1px solid #E0E6F0; }
.info-r    { width: 50%; vertical-align: top; padding: 15px 18px 15px 16px; }
.info-lbl  { font-size: 8.5px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #1565C0; border-bottom: 1px solid #E3F2FD; padding-bottom: 4px; margin-bottom: 7px; }
.cli-name  { font-size: 14px; font-weight: 700; color: #1A1A2E; margin-bottom: 4px; }
.detail    { font-size: 10.5px; color: #6B7A99; line-height: 1.75; }
.detail b  { color: #2C2C4A; font-weight: 600; }

/* ── TABLA ITEMS ── */
.t-sec     { padding: 14px 18px; }
.t-items   { width: 100%; border-collapse: collapse; font-size: 10.5px; }
.t-items thead tr { background: #1565C0; color: #fff; }
.t-items thead th { padding: 8px 10px; font-weight: 600; font-size: 9px; letter-spacing: 0.7px; text-transform: uppercase; text-align: left; }
.t-items thead th.tc { text-align: center; }
.t-items thead th.tr { text-align: right; }
.t-items tbody tr { border-bottom: 1px solid #E0E6F0; }
.t-items tbody tr.even { background: #F5F7FA; }
.t-items tbody td { padding: 8px 10px; vertical-align: middle; color: #2C2C4A; }
.t-items tbody td.tc { text-align: center; color: #6B7A99; font-size: 10px; }
.t-items tbody td.tr { text-align: right; }
.t-items tbody td.tf { text-align: right; font-weight: 700; color: #1A1A2E; }
.prod-name { font-weight: 600; font-size: 11px; color: #1A1A2E; }
.prod-sub  { font-size: 9.5px; color: #6B7A99; }
.prod-img  { width: 44px; height: 44px; display: block; margin: 0 auto; }

.disp-stock {
    background: #E8F5E9; color: #2E7D32;
    padding: 5px 6px; border-radius: 3px;
    font-size: 9px; font-weight: 600; line-height: 1.45;
    text-align: center;
}
.disp-imp {
    background: #FFF3CD; color: #856404;
    padding: 5px 6px; border-radius: 3px;
    font-size: 9px; font-weight: 600; line-height: 1.45;
    text-align: center;
}

/* ── CONDICIONES + TOTALES ── */
.bt        { width: 100%; border-collapse: collapse; }
.bt-left   { vertical-align: top; padding: 14px 18px; }
.bt-right  { width: 250px; vertical-align: top; padding: 14px 18px 14px 0; }

.cond-title{ font-size: 8.5px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #1565C0; border-bottom: 1px solid #E3F2FD; padding-bottom: 4px; margin-bottom: 7px; }
.cond-item { font-size: 10px; color: #6B7A99; line-height: 1.8; margin-bottom: 1px; }
.cond-pfx  { color: #42A5F5; font-weight: 700; margin-right: 3px; }

.nota-box  { margin-top: 10px; background: #FFF8E1; border-left: 3px solid #F9A825; padding: 7px 10px; }
.nota-ttl  { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #F57F17; margin-bottom: 3px; }
.nota-txt  { font-size: 9.5px; color: #795548; line-height: 1.55; }

.tot-box   { width: 100%; border-collapse: collapse; border: 1px solid #E0E6F0; }
.tot-box td{ padding: 7px 12px; font-size: 11px; border-bottom: 1px solid #E0E6F0; color: #6B7A99; }
.tot-lbl   { text-align: left; }
.tot-val   { text-align: right; font-weight: 600; color: #2C2C4A; }
.tot-fin td{ background: #1565C0; color: #fff; font-weight: 700; padding: 11px 12px; border-bottom: none; }
.tot-fin .lbl { font-size: 13px; letter-spacing: 2px; text-transform: uppercase; text-align: left; color: #fff; }
.tot-fin .amt { font-size: 18px; text-align: right; color: #fff; }
.tot-mon td{ background: #1565C0; font-size: 9px; text-align: right; padding: 3px 12px 6px; color: rgba(255,255,255,0.7); border-bottom: none; }

/* ── PAGO ── */
.pago-sec  { padding: 0 18px 16px; page-break-inside: avoid; }
.pago-ttl  { font-size: 8.5px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #1565C0; border-bottom: 1px solid #E3F2FD; padding-bottom: 4px; margin-bottom: 8px; }
.pago-tbl  { width: 100%; border-collapse: collapse; }
.pago-tbl td { padding: 0 8px 0 0; vertical-align: top; }
.pago-card { background: #F5F7FA; border: 1px solid #E0E6F0; border-radius: 4px; padding: 7px 10px; }
.pago-key  { font-size: 8.5px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: #6B7A99; margin-bottom: 2px; }
.pago-val  { font-size: 11.5px; font-weight: 600; color: #1A1A2E; }

/* ── FIRMA ── */
.firma-tbl { width: 100%; border-collapse: collapse; padding: 0 18px 20px; page-break-inside: avoid; }
.firma-cel { width: 50%; text-align: center; vertical-align: bottom; padding: 0 22px 20px; }
.firma-sp  { height: 40px; }
.firma-ln  { border-top: 1.5px solid #1A1A2E; margin-bottom: 4px; }
.firma-nm  { font-size: 11px; font-weight: 600; color: #1A1A2E; }
.firma-cg  { font-size: 10px; color: #6B7A99; }
.firma-fh  { font-size: 9.5px; color: #aaa; margin-top: 2px; }

/* ── FOOTER ── */
.ft        { width: 100%; border-collapse: collapse; background: #1565C0; }
.ft td     { padding: 10px 16px; font-size: 10.5px; color: rgba(255,255,255,0.85); vertical-align: middle; }
</style>

{{-- ═══════════════════════════════════
     WRAPPER CON FRANJA LATERAL
═══════════════════════════════════ --}}
<table class="wrap" cellpadding="0" cellspacing="0">
<tr>
    <td class="strip">&nbsp;</td>
    <td class="body">

        {{-- HEADER --}}
        <table class="hd" cellpadding="0" cellspacing="0">
        <tr>
            <td class="hd-logo">
                <span class="logo-sq">W</span>
                <span class="co-name">WILLATEC S.A.C</span>
                <div class="co-tag">Soluciones digitales</div>
            </td>
            <td class="hd-right">
                <div class="doc-title">COTIZACIÓN</div>
                <table class="meta" cellpadding="0" cellspacing="0">
                    <tr><td>N° Cotización:</td><td class="v">{{ $cotizacion->numero }}</td></tr>
                    <tr><td>Fecha emisión:</td><td class="v">{{ $fechaEmision }}</td></tr>
                    <tr><td>Válido hasta:</td><td class="v">{{ $fechaValidez }}</td></tr>
                    <tr><td>Vendedor:</td><td class="v">{{ $cotizacion->user->nombres ?? '' }} {{ $cotizacion->user->apellidos ?? '' }}</td></tr>
                </table>
                <div style="text-align:right;margin-top:6px">
                    <span class="badge">Válido {{ $cotizacion->validez_dias }} días calendarios</span>
                </div>
            </td>
        </tr>
        </table>

        {{-- INFO CLIENTE / EMISOR --}}
        <table class="info" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-l">
                <div class="info-lbl">Datos del cliente</div>
                <div class="cli-name">{{ $cotizacion->cliente_nombre ?? 'Cliente' }}</div>
                <div class="detail">
                    Tel: <b>{{ $cotizacion->cliente_telefono ?? 'N/A' }}</b><br>
                    Correo: <b>{{ $cotizacion->cliente_correo ?? 'N/A' }}</b><br>
                    RUC/DNI: <b>{{ $cotizacion->cliente_ruc ?? 'N/A' }}</b>
                </div>
            </td>
            <td class="info-r">
                <div class="info-lbl">Datos del emisor</div>
                <div class="cli-name">WILLATEC S.A.C</div>
                <div class="detail">
                    Dir: <b>Jr. Jorge Chávez N° 1747 - Of.1002 - Breña - Lima</b><br>
                    RUC: <b>20602503331</b> &nbsp; Tel: <b>(01) 757-1253</b><br>
                    WA: <b>{{ $cotizacion->user->profile->telefono ?? '934-577-815' }}</b><br>
                    Correo: <b>ventas@willatec.com</b> &nbsp; Web: <b>www.willatec.com</b>
                </div>
            </td>
        </tr>
        </table>

        {{-- TABLA DE PRODUCTOS --}}
        <div class="t-sec">
        <table class="t-items" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th class="tc" style="width:30px">#</th>
                    <th style="width:auto">Producto / Servicio</th>
                    <th class="tc" style="width:58px">Imagen</th>
                    <th class="tr" style="width:45px">Cant.</th>
                    <th class="tr" style="width:80px">P. Unit.</th>
                    <th class="tr" style="width:80px">Subtotal</th>
                    <th class="tc" style="width:110px">Disponibilidad</th>
                </tr>
            </thead>
            <tbody>
            @foreach($cotizacion->items as $item)
            <tr class="{{ $loop->even ? 'even' : '' }}">
                <td class="tc">{{ $loop->iteration }}</td>
                <td>
                    <span class="prod-name">{{ $item->descripcion }}</span><br>
                    @if($item->marca)
                    <span class="prod-sub">Marca: {{ $item->marca }}</span><br>
                    @endif
                    <span class="prod-sub">Garantía: {{ $item->garantia_meses }} meses</span>
                </td>
                <td class="tc">
                    @if($item->imagen)
                        <img src="{{ public_path('storage/' . $item->imagen) }}" class="prod-img" alt="{{ $item->descripcion }}">
                    @else
                        <span style="font-size:9px;color:#bbb">Sin imagen</span>
                    @endif
                </td>
                <td class="tr">{{ $item->cantidad }}</td>
                <td class="tr">{{ $simbolo }} {{ number_format($item->precio_venta, 2) }}</td>
                <td class="tf">{{ $simbolo }} {{ number_format($item->subtotal, 2) }}</td>
                <td class="tc">
                    @if($item->disponibilidad_tipo === 'importacion')
                        <div class="disp-imp">
                            IMPORTACIÓN<br>
                            {{ $item->disponibilidad_dias }} días c.<br>
                            Puesta la OC.
                        </div>
                    @else
                        <div class="disp-stock">
                            STOCK DISP.<br>
                            {{ $item->disponibilidad_dias }} días c.<br>
                            Puesta la OC.
                        </div>
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>

        {{-- CONDICIONES + TOTALES --}}
        <table class="bt" cellpadding="0" cellspacing="0">
        <tr>
            <td class="bt-left">
                <div class="cond-title">Condiciones comerciales</div>
                <div class="cond-item"><span class="cond-pfx">&rsaquo;</span>Forma de Pago: Crédito a 30 días calendarios</div>
                <div class="cond-item"><span class="cond-pfx">&rsaquo;</span>Incluye entrega en oficinas del cliente, Lima Metropolitana.</div>
                <div class="cond-item"><span class="cond-pfx">&rsaquo;</span>Precios en Dólares Americanos (USD) y NO incluyen IGV.</div>
                <div class="cond-item"><span class="cond-pfx">&rsaquo;</span>Precios sujetos a cambio sin previo aviso.</div>
                <div class="cond-item"><span class="cond-pfx">&rsaquo;</span>WILLATEC S.A.C, Régimen de Buenos Contribuyentes - Res. N° 0230050266292 (SUNAT).</div>
                <div class="nota-box">
                    <div class="nota-ttl">Notas</div>
                    <div class="nota-txt">
                        El precio del producto está sujeto al stock y nuevo ingreso de importación de la marca.<br>
                        Esto aplica después de los 05 días calendarios de validez de la cotización.<br>
                        Para productos importados, el tiempo de entrega puede variar entre 20 y 25 días calendario.
                    </div>
                </div>
            </td>
            <td class="bt-right">
                <table class="tot-box" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="tot-lbl">Subtotal</td>
                        <td class="tot-val">{{ $simbolo }} {{ number_format($cotizacion->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="tot-lbl">IGV (18%)</td>
                        <td class="tot-val">{{ $simbolo }} {{ number_format($cotizacion->igv, 2) }}</td>
                    </tr>
                    <tr class="tot-fin">
                        <td class="lbl">Total</td>
                        <td class="amt">{{ $simbolo }} {{ number_format($cotizacion->total, 2) }}</td>
                    </tr>
                    <tr class="tot-mon">
                        <td colspan="2">Moneda: {{ $cotizacion->moneda->codigo ?? 'USD' }} {{ $simbolo }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        </table>

        {{-- MÉTODO DE PAGO --}}
        <div class="pago-sec">
            <div class="pago-ttl">Método de pago</div>
            <table class="pago-tbl" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:33%;padding-right:8px">
                    <div class="pago-card">
                        <div class="pago-key">Banco</div>
                        <div class="pago-val">Banco de Crédito</div>
                    </div>
                </td>
                <td style="width:33%;padding-right:8px">
                    <div class="pago-card">
                        <div class="pago-key">Cta. Cte. Dólares</div>
                        <div class="pago-val">193-2421813-1-66</div>
                    </div>
                </td>
                <td style="width:33%">
                    <div class="pago-card">
                        <div class="pago-key">CCI</div>
                        <div class="pago-val">00219300242181316612</div>
                    </div>
                </td>
            </tr>
            </table>
        </div>

        {{-- FIRMA --}}
        <table class="firma-tbl" cellpadding="0" cellspacing="0">
        <tr>
            <td class="firma-cel">
                <div class="firma-sp"></div>
                @if(file_exists(public_path('img/firma/firma_gerente.png')))
                <img src="{{ public_path('img/firma/firma_gerente.png') }}"
                     style="height:44px;width:auto;display:block;margin:0 auto 4px;"
                     alt="Firma">
                @endif
                <div class="firma-ln"></div>
                <div class="firma-nm">Luis Ángel López Salazar</div>
                <div class="firma-cg">Gerente General — Willatec S.A.C</div>
                <div class="firma-fh">Fecha: {{ $fechaEmision }}</div>
            </td>
            <td class="firma-cel">
                {{-- segunda firma: descomenta si la necesitas --}}
            </td>
        </tr>
        </table>

        {{-- FOOTER --}}
        <table class="ft" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:25%">(01) 757-1253</td>
            <td style="width:25%">ventas@willatec.com</td>
            <td style="width:25%">www.willatec.com</td>
            <td style="width:25%">Jr. Jorge Chávez N° 1747 - Of.1002 - Breña</td>
        </tr>
        </table>

    </td>{{-- fin .body --}}
</tr>
</table>

@endsection