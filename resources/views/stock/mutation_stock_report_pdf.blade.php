<!DOCTYPE html>
<html>
<head>
    <title>Laporan Mutasi Stok</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            padding: 0;
        }
        .meta-info {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Mutasi Stok</h2>
        <p>Periode: {{ $params['from_date'] ?? '-' }} s/d {{ $params['until_date'] ?? '-' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>No. Ref</th>
                <th>Tipe</th>
                <th>Dari Gudang</th>
                <th>Ke Gudang</th>
                <th>Catatan</th>
                <th>Status</th>
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
                    <td>{{ ucfirst($item['status_mutation']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
