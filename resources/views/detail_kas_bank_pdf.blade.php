<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
<h2>{{ $title }}</h2>
<p>{{ $period }}</p>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>No Transaksi</th>
        <th>Akun</th>
        <th>Keterangan</th>
        <th class="text-right">Debet</th>
        <th class="text-right">Kredit</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($reportData as $group)
        @foreach ($group['rows'] as $index => $row)
            <tr>
                <td>{{ $index === 0 ? $group['transaction_date'] : '' }}</td>
                <td>{{ $index === 0 ? $group['transaction_no'] : '' }}</td>
                <td>{{ $row['coa']['coa_name'] }} ({{ $row['coa']['coa_code'] }})</td>
                <td>{{ $row['note'] }}</td>
                <td class="text-right">{{ number_format($row['debet'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td class="text-right">{{ number_format($row['kredit'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            </tr>
        @endforeach
    @empty
        <tr>
            <td colspan="6" style="text-align: center">Data Masih Kosong</td>
        </tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
