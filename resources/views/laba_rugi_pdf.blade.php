<!DOCTYPE html>
<html>
<head>
    <title>Laba Rugi</title>
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
<h1>Laba Rugi</h1>
<table>
    <thead>
    <tr>
        <th>COA Name</th>
        <th>Saldo</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td style="white-space: pre;">{!! $item['coa_name'] !!}</td>
            <td style="text-align: right">{{ number_format($item['saldo'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
