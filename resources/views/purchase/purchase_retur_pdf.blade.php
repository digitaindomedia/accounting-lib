<!DOCTYPE html>
<html>
<head>
    <title>Retur Pembelian</title>
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
<h1 style="text-align: center;">Retur Pembelian</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>No Retur</th>
        <th>No Penerimaan</th>
        <th>No Invoice</th>
        <th>Nama Supplier</th>
        <th>Total</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['retur_date'] }}</td>
            <td>{{ $data['retur_no'] }}</td>
            <td>{{ $data['receive'] }}</td>
            <td>{{ $data['invoice'] }}</td>
            <td>{{ $data['vendor'] }}</td>
            <td>{{ $data['total'] }}</td>
            <td>{{ $data['retur_status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
