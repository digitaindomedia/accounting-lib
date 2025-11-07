<!DOCTYPE html>
<html>
<head>
    <title>Order Pembelian</title>
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
<h1 style="text-align: center;">Laporan Order Pembelian</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor</th>
        <th>No Permintaan</th>
        <th>Nama Supplier</th>
        <th>Total</th>
        <th>Status Order</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['order_date'] }}</td>
            <td>{{ $data['order_no'] }}</td>
            <td>{{ $data['request_no'] }}</td>
            <td>{{ $data['vendor'] }}</td>
            <td>{{ number_format($data['grandtotal'],\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            <td>{{ $data['order_status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
