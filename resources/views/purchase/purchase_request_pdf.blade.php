<!DOCTYPE html>
<html>
<head>
    <title>Permintaan Pembelian</title>
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
<h1 style="text-align: center;">Laporan Permintaan Pembelian</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor</th>
        <th>Permintaan Dari</th>
        <th>Tanggal Butuh</th>
        <th>Sifat Permintaan</th>
        <th>Status Permintaan</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['request_date'] }}</td>
            <td>{{ $data['request_no'] }}</td>
            <td>{{ $data['request_from'] }}</td>
            <td>{{ $data['req_needed_date'] }}</td>
            <td>{{ $data['urgency'] }}</td>
            <td>{{ $data['request_status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
