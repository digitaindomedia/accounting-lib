<!DOCTYPE html>
<html>
<head>
    <title>Pajak</title>
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
<h1 style="text-align: center;">Pajak</h1>
<table>
    <thead>
    <tr>
        <th>Nama Pajak</th>
        <th>Persentase</th>
        <th>Deskripsi</th>
        <th>Jenis Pajak</th>
        <th>Akun Pembelian</th>
        <th>Akun Penjualan</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['tax_name'] }}</td>
            <td>{{ $data['tax_percentage'] }}</td>
            <td>{{ $data['tax_description'] }}</td>
            <td>{{ $data['tax_sign'] }}</td>
            <td>{{ $data['purchase_coa_id'] }}</td>
            <td>{{ $data['sales_coa_id'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
