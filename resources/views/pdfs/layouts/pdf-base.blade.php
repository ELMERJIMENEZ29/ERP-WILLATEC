<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 25px 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #2C2C4A;
            font-size: 12px;
        }

        * {
            box-sizing: border-box;
        }

        h1,h2,h3,h4,h5,h6 {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        td, th {
            padding: 8px;
        }

        .page-break {
            page-break-before: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .w-100 {
            width: 100%;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }

        .mb-30 {
            margin-bottom: 30px;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .mt-30 {
            margin-top: 30px;
        }

        .totales-box {
            width: 280px;
            margin-left: auto;
            border: 1px solid #ddd;
        }

        .total-row {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .total-final {
            background: #1565C0;
            color: white;
            padding: 14px;
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 11px;
            color: #666;
        }

    </style>

    @yield('styles')
</head>
<body>

    @yield('content')

</body>
</html>