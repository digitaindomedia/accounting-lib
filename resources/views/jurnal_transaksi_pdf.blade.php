<!DOCTYPE html>
<html>
<head>
    <title>Jurnal Transaksi</title>
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
<h1 style="text-align: center;">Laporan Jurnal Transaksi</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor</th>
        <th>Akun</th>
        <th>Keterangan</th>
        <th style="text-align: right">Debet</th>
        <th style="text-align: right">Kredit</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($jurnalData as $data)
            <tr>
                <td>{{ $data['transaction_date'] }}</td>
                <td>{{ $data['transaction_no'] }}</td>
                <td>{{ $data['coa']['coa_name']." ".$data['coa']['coa_code'] }}</td>
                <td>{{ $data['note'] }}</td>
                <td style="text-align: right">{{ number_format($data['debet'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td style="text-align: right">{{ number_format($data['kredit'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
