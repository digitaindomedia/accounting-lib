@php use App\Enums\TypeEnum; @endphp
    <!DOCTYPE html>
<html>
<head>
    <title>Permintaan Pembelian</title>
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
        <td style="text-align: center" colspan="5">Laporan Permintaan Pembelian</td>
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
        <th>Permintaan Dari</th>
        <th>Tanggal Butuh</th>
        <th>Sifat Permintaan</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $post)
        <tr>
            <td>
                {{$post->request_no}}
            </td>
            <td>
                {{$post->request_date}}
            </td>
            <td>
                {{$post->request_from}}
            </td>
            <td>
                {{$post->req_needed_date}}
            </td>
            <td>
                {{$post->urgency}}
            </td>
        </tr>
        <tr>
            <th>Kode Item</th>
            <th>Nama Item</th>
            <th>Qty</th>
            <th>Satuan</th>
            <th>Keterangan</th>
        </tr>
        @foreach ($post->requestproduct as $item)
            <tr>
                <td>{{ $item->product->item_code }}</td>
                <td>{{ $item->product->item_name }}</td>
                <td>{{ $item->qty }}</td>
                <td>{{ $item->unit->unit_code }}</td>
                <td>{{ $item->note }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td colspan="5"></td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
