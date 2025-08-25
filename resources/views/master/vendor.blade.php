<!DOCTYPE html>
<html>
<head>
    <title>{{$vendorType == \App\Utils\VendorType::SUPPLIER ? "Supplier" : "Customer"}}</title>
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
<h1 style="text-align: center;">{{$vendorType == \App\Utils\VendorType::SUPPLIER ? "Supplier" : "Customer"}}</h1>
<table>
    <thead>
    <tr>
        <th>Nama</th>
        <th>Kode</th>
        <th>Nama Perusahaan</th>
        <th>Email</th>
        <th>No Telp</th>
        <th>NPWP</th>
        <th>Alamat</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['vendor_name'] }}</td>
            <td>{{ $data['vendor_code'] }}</td>
            <td>{{ $data['vendor_company_name'] }}</td>
            <td>{{ $data['vendor_email'] }}</td>
            <td>{{ $data['vendor_phone'] }}</td>
            <td>{{ $data['vendor_npwp'] }}</td>
            <td>{{ $data['vendor_address'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
