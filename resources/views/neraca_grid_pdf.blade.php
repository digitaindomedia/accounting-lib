<!DOCTYPE html>
<html>
<head>
    <title>Neraca List</title>
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
<h1>Neraca List</h1>
<table>
    <thead>
    <tr>
        <th>COA Name</th>
        <th>Saldo</th>
        <th>COA Name</th>
        <th>Saldo</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td style="white-space: pre;">{!! $item['coa_name_asset'] !!}</td>
            <td style="text-align: right">{{ $item['saldo_asset'] }}</td>
            <td style="white-space: pre;">{!! $item['coa_name_liability_equity'] !!}</td>
            <td style="text-align: right">{{ $item['saldo_liability_equity'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
