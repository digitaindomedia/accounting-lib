<!DOCTYPE html>
<html>
<head>
    <title>Pengiriman Penjualan</title>
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
            <td style="text-align: center" colspan="6">Laporan Pengiriman Penjualan</td>
        </tr>
        <tr>
            <td style="text-align: center" colspan="6">
                {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate'])}} - {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate'])}}</td>
        </tr>
        <tr>
            <td style="text-align: center" colspan="6"></td>
        </tr>
        <tr>
            <th>Nomor DO</th>
            <th>Tanggal</th>
            <th colspan="4">Nama Customer</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($data as $post)
        <tr>
            <td>
                {{$post->delivery_no}}
            </td>
            <td>
                {{$post->delivery_date}}
            </td>
            <td colspan="4">
                {{$post->vendor->vendor_company_name}}
            </td>
        </tr>
        <tr>
            <th>Nama Item</th>
            <th>Qty</th>
        </tr>
        @foreach($post->deliveryproduct as $item)
            <tr>
                <td>{{ $item->product->item_name."(".$item->product->item_code.")" }}</td>
                <td>{{ $item->qty." ".$item->unit->unit_code }}</td>
            </tr>
        @endforeach
    @endforeach
    </tbody>

</table>
</body>
</html>