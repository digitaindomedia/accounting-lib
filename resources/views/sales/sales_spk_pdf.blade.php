<!DOCTYPE html>
<html>
<head>
    <title>SPK Penjualan</title>
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
<h1 style="text-align: center;">SPK Penjualan</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor SPK</th>
        <th>No Order</th>
        <th>Nama Customer</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['spk_date'] }}</td>
            <td>{{ $data['spk_no'] }}</td>
            <td>{{ $data['order'] }}</td>
            <td>{{ $data['vendor'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
