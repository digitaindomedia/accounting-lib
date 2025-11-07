@php use App\Enums\TypeEnum; @endphp
    <!DOCTYPE html>
<html>
<head>
    <title>Penerimaan Pembelian</title>
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
        <td style="text-align: center" colspan="5">Laporan Penerimaan Pembelian</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="5">
            {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate'])}} - {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate'])}}</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="5"></td>
    </tr>
    <tr>
        <th>Nomor Transaksi</th>
        <th>Tanggal</th>
        <th>No Order</th>
        <th>Nama Supplier</th>
        <th>Nama Gudang</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $post)
        <tr>
            <td>
                {{$post->receive_no}}
            </td>
            <td>
                {{$post->receive_date}}
            </td>
            <td>
                {{$post->order->order_no}}
            </td>
            <td>
                {{$post->vendor->vendor_company_name}}
            </td>
            <td>
                {{$post->warehouse->warehouse_name}}
            </td>
        </tr>
        <tr>
            <td>Kode Item</td>
            <td>Nama Item</td>
            <td>Qty</td>
            <td>Satuan</td>
        </tr>
        @foreach ($post->receiveproduct as $item)
            <tr>
                <td>{{ $item->product->item_code }}</td>
                <td>{{ $item->product->item_name }}</td>
                <td>{{ $item->qty }}</td>
                <td>{{ $item->unit->unit_code }}</td>
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
