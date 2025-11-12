<!DOCTYPE html>
<html>
<head>
    <title>Laporan Penyesuaian Stok</title>
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
<h1 style="text-align: center;">Laporan Penyesuaian Stok</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Penyesuaian</th>
        <th>Nama Gudang</th>
        <th>Akun Penyesuaian</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['adjustment_date'] }}</td>
            <td>{{ $data['ref_no'] }}</td>
            <td>{{ $data['warehouse'] }}</td>
            <td>{{ $data['akun'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
