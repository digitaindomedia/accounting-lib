<!DOCTYPE html>
<html>
<head>
    <title>Buku Besar</title>
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
<h1>Buku Besar</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor</th>
        <th>Keterangan</th>
        <th style="text-align: right">Debet</th>
        <th style="text-align: right">Kredit</th>
        <th style="text-align: right">Saldo</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($jurnalData as $jurnal)
        <tr><td colspan="6">{{ $jurnal['coa']['coa_name'] }} - {{ $jurnal['coa']['coa_code'] }}</td></tr>
        <tr><td colspan="5">Saldo Awal</td><td>{{ number_format($jurnal['saldo_awal'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td></tr>
        @foreach ($jurnal['data'] as $data)
            <tr>
                <td>{{ $data['transaction_date'] }}</td>
                <td>{{ $data['transaction_no'] }}</td>
                <td>{{ $data['note'] }}</td>
                <td style="text-align: right">{{ number_format($data['debet'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td style="text-align: right">{{ number_format($data['kredit'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td style="text-align: right">{{ number_format($data['saldo'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>
</body>
</html>
