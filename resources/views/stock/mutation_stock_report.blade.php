<!DOCTYPE html>
<html>
<head>
    <title>Laporan Mutasi Stok</title>
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
<h1 style="text-align: center;">Laporan Mutasi Stok</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Referensi</th>
        <th>Tipe Mutasi</th>
        <th>Dari Gudang</th>
        <th>Ke Gudang</th>
        <th>Catatan</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item['mutation_date'] }}</td>
            <td>{{ $item['ref_no'] }}</td>
            <td>{{ $item['mutation_type'] == 'mutation_in' ? 'Masuk' : 'Keluar' }}</td>
            <td>{{ $item['fromwarehouse']['warehouse_name'] ?? '-' }}</td>
            <td>{{ $item['towarehouse']['warehouse_name'] ?? '-' }}</td>
            <td>{{ $item['note'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
