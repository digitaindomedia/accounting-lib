<!DOCTYPE html>
<html>
<head>
    <title>Invoice Pembelian Aset Tetap</title>
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
<h1 style="text-align: center;">Laporan Invoice Pembelian Aset Tetap</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>No Invoice</th>
        <th>Nama Aset</th>
        <th>Nama Pembeli</th>
        <th>Harga Jual</th>
        <th>Untung/Rugi</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['sales_date'] }}</td>
            <td>{{ $item['sales_no'] }}</td>
            <td>{{ $item['namaaset'] }}</td>
            <td>{{ $item['nama_aset'] }}</td>
            <td>{{ $item['buyer_name'] }}</td>
            <td>{{ $item['nominal'] }}</td>
            <td>{{ $item['profit_loss'] }}</td>
            <td>{{ $item['sales_status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
