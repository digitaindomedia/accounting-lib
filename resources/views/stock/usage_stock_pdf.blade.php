<!DOCTYPE html>
<html>
<head>
    <title>Pemakaian Stok</title>
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
<h1 style="text-align: center;">Pemakaian Stok</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Pemakaian</th>
        <th>Nama Gudang</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['usage_date'] }}</td>
            <td>{{ $data['ref_no'] }}</td>
            <td>{{ $data['warehouse'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
