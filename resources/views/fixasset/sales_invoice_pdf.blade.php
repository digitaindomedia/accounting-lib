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
<h1 style="text-align: center;">Invoice Pembelian Aset Tetap</h1>
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
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['sales_date'] }}</td>
            <td>{{ $data['sales_no'] }}</td>
            <td>{{ $data['namaaset'] }}</td>
            <td>{{ $data['nama_aset'] }}</td>
            <td>{{ $data['buyer_name'] }}</td>
            <td>{{ $data['nominal'] }}</td>
            <td>{{ $data['profit_loss'] }}</td>
            <td>{{ $data['sales_status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
