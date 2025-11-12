<!DOCTYPE html>
<html>
<head>
    <title>Pembayaran Penjualan Aset Tetap</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
<h1 style="text-align: center;">Laporan Pembayaran Penjualan Aset Tetap</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>No Pelunasan</th>
        <th>No Invoice</th>
        <th>Total Bayar</th>
        <th>Metode Pembayaran</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['payment_date'] }}</td>
            <td>{{ $item['payment_no'] }}</td>
            <td>{{ $item['sales_invoice'] }}</td>
            <td>{{ number_format($item['total'],\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            <td>{{ $item['payment_method'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
