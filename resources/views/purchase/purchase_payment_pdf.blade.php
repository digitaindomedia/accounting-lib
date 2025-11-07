<!DOCTYPE html>
<html>
<head>
    <title>Pembayaran Pembelian</title>
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
<h1 style="text-align: center;">Pembayaran Pembelian</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>No Pelunasan</th>
        <th>Nama Supplier</th>
        <th>Total Bayar</th>
        <th>Metode Pembayaran</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['payment_date'] }}</td>
            <td>{{ $data['payment_no'] }}</td>
            <td>{{ $data['vendor'] }}</td>
            <td>{{ number_format($data['total'],\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
            <td>{{ $data['method'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
