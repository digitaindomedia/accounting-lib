<!DOCTYPE html>
<html>
<head>
    <title>Gudang</title>
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
<h1 style="text-align: center;">Gudang</h1>
<table>
    <thead>
    <tr>
        <th>Nama</th>
        <th>Kode</th>
        <th>Deskripsi</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['warehouse_name'] }}</td>
            <td>{{ $data['warehouse_code'] }}</td>
            <td>{{ $data['warehouse_address'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
