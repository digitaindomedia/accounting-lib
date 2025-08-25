<!DOCTYPE html>
<html>
<head>
    <title>BAST Pembelian</title>
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
<table>
    <thead>
    <tr>
        <td style="text-align: center" colspan="5">Laporan BAST Pembelian</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="5">
            {{\App\Utils\Utility::convert_tanggal($params['fromDate'])}} - {{\App\Utils\Utility::convert_tanggal($params['untilDate'])}}</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="5"></td>
    </tr>
    <tr>
        <th>Nomor Transaksi</th>
        <th>Tanggal</th>
        <th>No Order</th>
        <th>Nama Supplier</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $post)
        <tr>
            <td>
                {{$post->bast_no}}
            </td>
            <td>
                {{$post->bast_date}}
            </td>
            <td>
                {{$post->order->order_no}}
            </td>
            <td>
                {{$post->vendor->vendor_company_name}}
            </td>
        </tr>
        <tr>
            <td>Nama Jasa</td>
            <td>Qty</td>
        </tr>
        @foreach ($post->bastproduct as $item)
            <tr>
                <td>{{ $item->service_name }}</td>
                <td>{{ $item->qty }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="6"></td>
        </tr>
        <tr>
            <td colspan="6"></td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
