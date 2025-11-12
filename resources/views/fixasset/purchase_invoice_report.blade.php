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
        <th>No Order</th>
        <th>Nama Aset</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['invoice_date'] }}</td>
            <td>{{ $item['invoice_no'] }}</td>
            <td>{{ $item['no_aset'] }}</td>
            <td>{{ $item['nama_aset'] }}</td>
            <td>{{ $item['invoice_status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
