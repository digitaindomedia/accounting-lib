<!DOCTYPE html>
<html>
<head>
    <title>Pengiriman Penjualan</title>
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
<h1 style="text-align: center;">Pengiriman Penjualan</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Pengiriman</th>
        <th>No Order</th>
        <th>Nama Customer</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['delivery_date'] }}</td>
            <td>{{ $data['delivery_no'] }}</td>
            <td>{{ $data['order'] }}</td>
            <td>{{ $data['vendor'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
