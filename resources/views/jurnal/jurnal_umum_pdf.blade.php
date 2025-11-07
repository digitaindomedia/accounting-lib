<!DOCTYPE html>
<html>
<head>
    <title>Jurnal Umum</title>
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
<h1 style="text-align: center;">Jurnal Umum</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Jurnal</th>
        <th>Keterangan</th>
        <th>Nominal</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['jurnal_date'] }}</td>
            <td>{{ $data['jurnal_no'] }}</td>
            <td>{{ $data['note'] }}</td>
            <td>{{ number_format($data['total'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
