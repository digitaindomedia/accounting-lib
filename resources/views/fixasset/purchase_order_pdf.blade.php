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
<h1 style="text-align: center;">Order Pembelian Aset Tetap</h1>
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
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['nama_aset'] }}</td>
            <td>{{ $data['no_aset'] }}</td>
            <td>{{ $data['aset_tetap_date'] }}</td>
            <td>{{ number_format($data['harga_beli'],\App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            <td>{{ $data['status_aset_tetap'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
