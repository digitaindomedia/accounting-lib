<!DOCTYPE html>
<html>
<head>
    <title>Pembayaran Penjualan</title>
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
        <td style="text-align: center" colspan="5">Laporan Pembayaran Penjualan</td>
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
        <th>Nama Customer</th>
        <th>Metode Pembayaran</th>
        <th>Total Pembayaran</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $post)
        <tr>
            <td>
                {{$post->payment_no}}
            </td>
            <td>
                {{$post->payment_date}}
            </td>
            <td>
                {{$post->vendor->vendor_company_name}}
            </td>
            <td>
                {{$post->payment_method->payment_name}}
            </td>
            <td>
                {{number_format($post->total, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}
            </td>
        </tr>
        <tr>
            <td>No Invoice</td>
            <td>Nilai Invoice</td>
            <td>Pembayaran</td>
            <td>Potongan</td>
            <td>Kelebihan</td>
        </tr>
        @foreach ($post->invoice as $item)
            <tr>
                <td>{{ $item->salesinvoice->invoice_no }}</td>
                <td>{{ number_format($item->salesinvoice->grandtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($item->total_payment,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($item->total_discount,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($item->total_overpayment,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            </tr>
        @endforeach
        @foreach ($post->invoiceretur as $item)
            <tr>
                <td>{{ $item->retur->retur_no }}</td>
                <td>{{ number_format($item->retur->total, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($item->total_payment,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($item->total_discount,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($item->total_overpayment,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
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
