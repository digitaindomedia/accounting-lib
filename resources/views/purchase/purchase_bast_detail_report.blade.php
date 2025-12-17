<!DOCTYPE html>
<html>
<head>
    <title>BAST Pembelian</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background-color: #f2f2f2;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .no-border td {
            border: none;
            padding: 4px;
        }

        .section-gap td {
            border: none;
            height: 10px;
        }

        .sub-header {
            background-color: #fafafa;
            font-weight: bold;
        }
    </style>
</head>
<body>

<table>
    <thead>
    <tr class="no-border">
        <td class="text-center" colspan="4">
            <strong>LAPORAN BAST PEMBELIAN</strong>
        </td>
    </tr>
    <tr class="no-border">
        <td class="text-center" colspan="4">
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate']) }}
            -
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate']) }}
        </td>
    </tr>
    </thead>
</table>

<table>
    <thead>
    <tr>
        <th width="20%">Nomor Transaksi</th>
        <th width="15%">Tanggal</th>
        <th width="20%">No Order</th>
        <th width="45%">Nama Supplier</th>
    </tr>
    </thead>

    <tbody>
    @foreach ($data as $post)

        <!-- Header transaksi -->
        <tr>
            <td>{{ $post->bast_no }}</td>
            <td class="text-center">{{ $post->bast_date }}</td>
            <td>{{ $post->order->order_no }}</td>
            <td>{{ $post->vendor->vendor_company_name }}</td>
        </tr>

        <!-- Sub header jasa -->
        <tr class="sub-header">
            <td colspan="3">Nama Jasa</td>
            <td class="text-center">Qty</td>
        </tr>

        <!-- Detail jasa -->
        @foreach ($post->bastproduct as $item)
            <tr>
                <td colspan="3">{{ $item->service_name }}</td>
                <td class="text-center">{{ $item->qty }}</td>
            </tr>
        @endforeach

        <!-- Spasi antar transaksi -->
        <tr class="section-gap">
            <td colspan="4"></td>
        </tr>

    @endforeach
    </tbody>
</table>

</body>
</html>
