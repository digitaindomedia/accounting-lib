<!DOCTYPE html>
<html>
<head>
    <title>Uang Muka Pembelian</title>
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
<h1 style="text-align: center;">Uang Muka Pembelian</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Transaksi</th>
        <th>No Order</th>
        <th>Nama Supplier</th>
        <th>Nominal</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['downpayment_date'] }}</td>
            <td>{{ $data['ref_no'] }}</td>
            <td>{{ $data['order'] }}</td>
            <td>{{ $data['vendor'] }}</td>
            <td>{{ number_format($data['nominal'],\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
