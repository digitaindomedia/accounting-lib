<!DOCTYPE html>
<html>
<head>
    <title>BAST Pembelian</title>
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
<h1 style="text-align: center;">BAST Pembelian</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor BAST</th>
        <th>No Order</th>
        <th>Nama Supplier</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['bast_date'] }}</td>
            <td>{{ $data['bast_no'] }}</td>
            <td>{{ $data['order'] }}</td>
            <td>{{ $data['vendor'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
