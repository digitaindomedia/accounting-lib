<!DOCTYPE html>
<html>
<head>
    <title>Jurnal Kas/Bank</title>
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
<h1 style="text-align: center;">Jurnal Kas/Bank</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Jurnal</th>
        <th>Akun</th>
        <th>Keterangan</th>
        <th>Masuk</th>
        <th>Keluar</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['jurnal_date'] }}</td>
            <td>{{ $data['jurnal_no'] }}</td>
            <td>{{ $data['akun'] }}</td>
            <td>{{ $data['note'] }}</td>
            <td>{{ $data['income'] }}</td>
            <td>{{ $data['outcome'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
