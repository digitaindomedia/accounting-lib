<!DOCTYPE html>
<html>
<head>
    <title>Uang Muka Pembelian</title>
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
        <td style="text-align: center" colspan="9">Laporan Uang Muka Pembelian</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="9">
            {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate'])}} - {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate'])}}</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="9"></td>
    </tr>
    <tr>
        <th>Tanggal</th>
        <th>Nomor Transaksi</th>
        <th>No Order</th>
        <th>Nama Supplier</th>
        <th>Akun Coa</th>
        <th>Nominal</th>
        <th>No Faktur</th>
        <th>Tanggal Faktur</th>
        <th>Note</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $item)
        <tr>
            <td>{{ $item->downpayment_date }}</td>
            <td>{{ $item->ref_no }}</td>
            <td>
                {{$item->order->order_no}}
            </td>
            <td>
                {{ $item->order->vendor->vendor_company_name }}
            </td>
            <td>{{ $item->coa->coa_name." - ".$item->coa->coa_code }}</td>
            <td style="text-align: right;">{{ number_format($item->nominal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            <td>{{ $item->no_faktur }}</td>
            <td>{{ $item->faktur_date }}</td>
            <td>{{ $item->note }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
