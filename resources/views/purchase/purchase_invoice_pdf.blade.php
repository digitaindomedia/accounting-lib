<!DOCTYPE html>
<html>
<head>
    <title>Invoice Pembelian</title>
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
<h1 style="text-align: center;">Invoice Pembelian</h1>
<table>
    <thead>
    <tr>
        <th>Tanggal</th>
        <th>No Invoice</th>
        <th>No Order</th>
        <th>Nama Supplier</th>
        <th>Jatuh Tempo</th>
        <th>Total</th>
        <th>Status Invoice</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['invoice_date'] }}</td>
            <td>{{ $data['invoice_no'] }}</td>
            <td>{{ $data['order'] }}</td>
            <td>{{ $data['vendor'] }}</td>
            <td>{{ $data['due_date'] }}</td>
            <td>{{ $data['grandtotal'] }}</td>
            <td>{{ $data['invoice_status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
