<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">

    <title>Orden de Compra {{ $ocEmitida->numero }}</title>

    <style>
        @page {
            margin: 22px 30px 25px 30px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #17172d;
            margin: 0;
            padding: 0;
        }

        /* =========================
           COLORES
        ========================== */

        .purple {
            color: #312783;
        }

        .teal {
            color: #008e98;
        }

        .pink {
            color: #e50073;
        }

        /* =========================
           HEADER
        ========================== */

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .header-table td {
            border: none;
            vertical-align: top;
            padding: 0;
        }

        .logo-area {
            width: 48%;
            padding-top: 7px;
        }

        .logo {
            width: 240px;
            max-height: 75px;
            object-fit: contain;
        }

        .order-area {
            width: 52%;
            text-align: right;
        }

        .order-title {
            background: #25236f;
            color: white;
            font-size: 24px;
            font-weight: bold;
            padding: 16px 20px 12px 20px;
            text-align: center;
            text-transform: uppercase;
        }

        .order-number-wrapper {
            text-align: center;
            margin-top: -5px;
        }

        .order-number {
            display: inline-block;
            background: #5a469b;
            color: white;
            padding: 7px 28px;
            border-radius: 18px;
            font-size: 19px;
            font-weight: bold;
            min-width: 210px;
        }

        .date-box {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
        }

        .date-icon {
            display: inline-block;
            background: #5541a0;
            color: white;
            padding: 4px 7px;
            margin-right: 5px;
            font-weight: bold;
            border-radius: 3px;
        }

        /* =========================
           LINEA COLORES
        ========================== */

        .color-line {
            margin-top: 15px;
            margin-bottom: 26px;
            width: 100%;
            border-collapse: collapse;
        }

        .color-line td {
            height: 2px;
            border: none;
            padding: 0;
        }

        .line-pink {
            background: #e50073;
            width: 15%;
        }

        .line-cyan {
            background: #0ea4aa;
            width: 18%;
        }

        .line-green {
            background: #8ebd21;
            width: 20%;
        }

        .line-purple {
            background: #312783;
            width: 47%;
        }

        /* =========================
           CAJAS INFORMACION
        ========================== */

        .info-layout {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-left: -10px;
            width: calc(100% + 20px);
        }

        .info-layout > tbody > tr > td {
            width: 50%;
            border: none;
            vertical-align: top;
            padding: 0 10px;
        }

        .section-title {
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 8px 13px;
            width: 74%;
            text-transform: uppercase;
        }

        .section-title.provider {
            background: #30277d;
        }

        .section-title.billing {
            background: #008c96;
        }

        .info-card {
            border: 1px solid #d4d4dc;
            border-radius: 8px;
            padding: 13px 16px;
            min-height: 205px;
        }

        .info-row {
            width: 100%;
            border-collapse: collapse;
        }

        .info-row td {
            border: none;
            border-bottom: 1px solid #e5e5e8;
            padding: 10px 4px;
            vertical-align: top;
        }

        .info-row:last-child td {
            border-bottom: none;
        }

        .icon-cell {
            width: 28px;
            text-align: center;
        }

        .info-icon-purple,
        .info-icon-teal {
            display: inline-block;
            width: 19px;
            height: 19px;
            line-height: 19px;
            border-radius: 50%;
            color: white;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
        }

        .info-icon-purple {
            background: #5542a0;
        }

        .info-icon-teal {
            background: #008f98;
        }

        .label-cell {
            width: 105px;
            font-weight: bold;
        }

        .provider-label {
            color: #4c3b91;
        }

        .billing-label {
            color: #008c96;
        }

        .value-cell {
            color: #17172d;
            line-height: 1.45;
        }

        /* =========================
           PRODUCTOS
        ========================== */

        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 28px;
            border-radius: 7px;
            overflow: hidden;
        }

        .products-table th {
            background: #44368c;
            color: white;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            padding: 11px 7px;
            border: 1px solid #756bac;
        }

        .products-table td {
            border: 1px solid #d7d7dd;
            padding: 11px 7px;
            vertical-align: middle;
        }

        .products-table .item {
            width: 7%;
            text-align: center;
        }

        .products-table .code {
            width: 16%;
            text-align: center;
        }

        .products-table .description {
            width: 36%;
        }

        .products-table .quantity {
            width: 11%;
            text-align: center;
        }

        .products-table .unit-price {
            width: 15%;
            text-align: right;
        }

        .products-table .total-price {
            width: 15%;
            text-align: right;
        }

        /* =========================
           PARTE INFERIOR
        ========================== */

        .bottom-layout {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
        }

        .bottom-layout td {
            border: none;
            vertical-align: top;
        }

        .bottom-left {
            width: 55%;
            padding-right: 25px;
        }

        .bottom-right {
            width: 45%;
        }

        /* OPCIONALES */

        .optional-title {
            font-weight: bold;
            color: #008c96;
            margin-bottom: 7px;
            font-size: 10px;
        }

        .optional-line {
            border-bottom: 1px solid #dddddf;
            min-height: 22px;
            padding: 4px 0;
            margin-bottom: 1px;
            line-height: 1.5;
        }

        .observations-box {
            margin-top: 12px;
            border: 1px solid #d7d7dd;
            border-left: 5px solid #5542a0;
            border-radius: 7px;
            background: #f7f6fb;
            padding: 10px 12px;
        }

        .observations-title {
            color: #312783;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .observations-text {
            color: #17172d;
            font-size: 10px;
            line-height: 1.55;
            white-space: pre-line;
        }

        .payment-box {
            margin-top: 30px;
        }

        .payment-title {
            color: #e50073;
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 7px;
        }

        .payment-icon {
            display: inline-block;
            border: 2px solid #e50073;
            color: #e50073;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            line-height: 21px;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            margin-right: 7px;
        }

        /* =========================
           TOTALES
        ========================== */

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d6d6dd;
        }

        .totals-table td {
            padding: 10px 15px;
            border: 1px solid #d6d6dd;
        }

        .total-label {
            font-size: 11px;
            font-weight: bold;
        }

        .total-value {
            text-align: right;
            font-size: 12px;
            font-weight: bold;
        }

        .grand-total td {
            background: #008c96;
            color: white;
            border-color: #4fb4ba;
            padding-top: 11px;
            padding-bottom: 11px;
        }

        .grand-total .total-label {
            font-size: 16px;
        }

        .grand-total .total-value {
            font-size: 18px;
        }

        /* =========================
           FIRMA
        ========================== */

        .signature {
            margin-top: 20px;
            text-align: center;
        }

        .signature-text {
            margin-bottom: 4px;
            font-size: 10px;
        }

        .signature-img {
            max-width: 100px;
            max-height: 55px;
            margin-bottom: 0;
        }

        .signature-line {
            width: 185px;
            margin: 0 auto;
            border-top: 1px solid #008c96;
            padding-top: 5px;
        }

        .signature-name {
            font-weight: bold;
            font-size: 11px;
        }

        .signature-role {
            font-size: 9px;
            margin-top: 2px;
        }

        .signature-company {
            color: #008c96;
            font-weight: bold;
            font-size: 9px;
            margin-top: 2px;
        }

        /* =========================
           FOOTER
        ========================== */

        .footer {
            margin-top: 25px;
            background: #1f2464;
            color: white;
            padding: 12px 10px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            border: none;
            vertical-align: top;
            padding: 2px 9px;
            font-size: 8px;
            line-height: 1.5;
        }

        .footer-title {
            font-weight: bold;
            font-size: 8px;
        }

        .footer-address {
            width: 29%;
        }

        .footer-phone {
            width: 22%;
            border-left: 1px solid rgba(255,255,255,.25) !important;
        }

        .footer-email {
            width: 24%;
            border-left: 1px solid rgba(255,255,255,.25) !important;
        }

        .footer-web {
            width: 25%;
            border-left: 1px solid rgba(255,255,255,.25) !important;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
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

    $logoPath = public_path('public/img/logoWILLATEC-black.png');
    $firmaPath = public_path('public/img/firma/firma_gerente.png');
@endphp


{{-- =========================================================
     HEADER
========================================================= --}}

<table class="header-table">
    <tr>
        <td class="logo-area">

            @if(file_exists($logoPath))
                <img
                    src="{{ $logoPath }}"
                    class="logo"
                    alt="Willatec"
                >
            @else
                <div style="font-size:30px;font-weight:bold;">
                    WILLATEC
                </div>

                <div style="font-size:14px;margin-left:70px;">
                    Soluciones Digitales
                </div>
            @endif

        </td>

        <td class="order-area">

            <div class="order-title">
                ORDEN DE COMPRA
            </div>

            <div class="order-number-wrapper">
                <span class="order-number">
                    N° {{ $ocEmitida->numero }}
                </span>
            </div>

            <div class="date-box">
                <span class="date-icon">D</span>
                {{ $fecha }}
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

            <div class="section-title provider">
                DATOS DEL PROVEEDOR
            </div>

            <div class="info-card">

                <table class="info-row">
                    <tr>
                        <td class="icon-cell">
                            <span class="info-icon-purple">P</span>
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
                            <span class="info-icon-purple">R</span>
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
                            <span class="info-icon-purple">D</span>
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
                            <span class="info-icon-purple">T</span>
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
                            <span class="info-icon-purple">A</span>
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

            <div class="section-title billing">
                FACTURAR A
            </div>

            <div class="info-card">

                <table class="info-row">
                    <tr>
                        <td class="icon-cell">
                            <span class="info-icon-teal">R</span>
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
                            <span class="info-icon-teal">D</span>
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
                            <span class="info-icon-teal">C</span>
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
                            <span class="info-icon-teal">T</span>
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
                            <span class="info-icon-teal">@</span>
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

                    <div class="observations-text">
                        {{ $ocEmitida->observaciones }}
                    </div>
                </div>
            @endif


            {{-- MODALIDAD DE PAGO --}}

            <div class="payment-box">

                <div class="payment-title">

                    <span class="payment-icon">
                        $
                    </span>

                    MODALIDAD DE PAGO:

                </div>

                <div style="padding-left:38px;font-size:10px;">
                    {{ $modalidadPago }}
                </div>

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


                @if(file_exists($firmaPath))

                    <img
                        src="{{ $firmaPath }}"
                        class="signature-img"
                        alt="Firma"
                    >

                @else

                    <div style="height:48px;"></div>

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

            <td class="footer-address">

                <div class="footer-title">
                    Dirección Comercial:
                </div>

                Jr. Jorge Chávez N° 1747 - Int. 1002<br>
                Breña - Lima

            </td>


            <td class="footer-phone">

                <div class="footer-title">
                    Central Telefónica:
                </div>

                (511) 757 - 1253

            </td>


            <td class="footer-email">

                <div class="footer-title">
                    E-mail:
                </div>

                ventas@willatec.com

            </td>


            <td class="footer-web">

                <div class="footer-title">
                    Web:
                </div>

                https://www.willatec.com

            </td>

        </tr>

    </table>

</div>

</body>
</html>
