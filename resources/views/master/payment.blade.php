<!DOCTYPE html>
<html>
<head>
    <title>Metode Pembayaran</title>
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
<h1 style="text-align: center;">Metode Pembayaran</h1>
<table>
    <thead>
    <tr>
        <th>Nama Pembayaran</th>
        <th>Akun</th>
        <th>Deskripsi</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['payment_name'] }}</td>
            <td>{{ !empty($data['coa']) ? $data['coa']->coa_name: "" }}</td>
            <td>{{ $data['description'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
