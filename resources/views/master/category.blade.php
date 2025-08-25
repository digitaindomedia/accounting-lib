<!DOCTYPE html>
<html>
<head>
    <title>Kategori</title>
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
<h1 style="text-align: center;">Kategori</h1>
<table>
    <thead>
    <tr>
        <th>Nama</th>
        <th>Deskripsi</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['category_name'] }}</td>
            <td>{{ $data['category_description'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
