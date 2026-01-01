<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pemakaian Stok</title>
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
<h1 style="text-align: center;">Laporan Pemakaian Stok</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Pemakaian</th>
        <th>Nama Gudang</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['usage_date'] }}</td>
            <td>{{ $item['ref_no'] }}</td>
            <td>{{ $item['warehouse']['warehouse_name'] ?? '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
