<!DOCTYPE html>
<html>
<head>
    <title>Order Pembelian Aset Tetap</title>
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
<h1 style="text-align: center;">Laporan Order Pembelian Aset Tetap</h1>
<table>
    <thead>
    <tr>
        <th>Nama Aset</th>
        <th>Nomor</th>
        <th>Tanggal Beli</th>
        <th>Harga Beli</th>
        <th>Status Order</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['nama_aset'] }}</td>
            <td>{{ $item['no_aset'] }}</td>
            <td>{{ $item['aset_tetap_date'] }}</td>
            <td>{{ number_format($item['harga_beli'],\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            <td>{{ $item['status_aset_tetap'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
