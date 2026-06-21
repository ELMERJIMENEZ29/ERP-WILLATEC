<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $ocEmitida->numero }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
        }

        th {
            background: #f3f4f6;
        }

        .totals {
            width: 40%;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <h1>Orden de Compra {{ $ocEmitida->numero }}</h1>

    <p><strong>Proveedor:</strong> {{ $ocEmitida->proveedor }}</p>
    <p><strong>Fecha:</strong> {{ optional($ocEmitida->fecha_emision)->format('Y-m-d') }}</p>
    <p><strong>Cotizacion:</strong> {{ $ocEmitida->cotizacion?->numero }}</p>
    <p><strong>Cliente:</strong> {{ $ocEmitida->cliente_nombre }} - {{ $ocEmitida->cliente_ruc }}</p>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Codigo</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ocEmitida->items as $item)
                <tr>
                    <td>{{ $item->descripcion }}</td>
                    <td>{{ $item->codigo }}</td>
                    <td>{{ $item->cantidad }}</td>
                    <td>{{ number_format((float) $item->precio_unitario, 2) }}</td>
                    <td>{{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <th>Subtotal</th>
            <td>{{ number_format((float) $ocEmitida->subtotal, 2) }}</td>
        </tr>
        <tr>
            <th>IGV 18%</th>
            <td>{{ number_format((float) $ocEmitida->igv, 2) }}</td>
        </tr>
        <tr>
            <th>Total</th>
            <td>{{ number_format((float) $ocEmitida->total, 2) }}</td>
        </tr>
    </table>
</body>
</html>
