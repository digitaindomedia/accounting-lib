<!DOCTYPE html>
<html>
<head>
    <title>Pembayaran Penjualan</title>
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
<h1 style="text-align: center;">Pembayaran Penjualan</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>No Pelunasan</th>
        <th>Nama Customer</th>
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
            <td>{{ $data['total'] }}</td>
            <td>{{ $data['method'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
