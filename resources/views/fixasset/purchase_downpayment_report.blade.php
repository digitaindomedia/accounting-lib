<!DOCTYPE html>
<html>
<head>
    <title>Uang Muka Pembelian Aset Tetap</title>
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
<h1 style="text-align: center;">Laporan Uang Muka Pembelian Aset Tetap</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Transaksi</th>
        <th>Nama Aset</th>
        <th>No Order</th>
        <th>Nominal</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['downpayment_date'] }}</td>
            <td>{{ $item['ref_no'] }}</td>
            <td>{{ $item['nama_aset'] }}</td>
            <td>{{ $item['order'] }}</td>
            <td style="text-align: right;">{{ number_format($item['nominal'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
