<!DOCTYPE html>
<html>
<head>
    <title>Satuan</title>
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
<h1 style="text-align: center;">Satuan</h1>
<table>
    <thead>
    <tr>
        <th>Nama Satuan</th>
        <th>Kode Satuan</th>
        <th>Deskripsi</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['unit_name'] }}</td>
            <td>{{ $data['unit_code'] }}</td>
            <td>{{ $data['unit_description'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
