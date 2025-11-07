
<!DOCTYPE html>
<html>
<head>
    <title>Order Penjualan</title>
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
        <td style="text-align: center" colspan="6">Laporan Penjualan</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="6">
            {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate'])}} - {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate'])}}</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="6"></td>
    </tr>
    <tr>
        <th>Nomor Transaksi</th>
        <th>Tanggal</th>
        <th colspan="4">Nama Customer</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $post)
        <tr>
            <td>
                {{$post->order_no}}
            </td>
            <td>
                {{$post->order_date}}
            </td>
            <td colspan="4">
                {{$post->vendor->vendor_company_name}}
            </td>
        </tr>
        <tr>
            <th>Nama Item</th>
            <th>Qty</th>
            <th style="text-align: right">Harga</th>
            <th style="text-align: right">Diskon</th>
            <th>Pajak</th>
            <th style="text-align: right">Subtotal</th>
        </tr>
        @php
        $arrTax = [];
        @endphp
        @foreach ($post->orderproduct as $item)
            @php
                $taxname = \Icso\Accounting\Utils\Helpers::getTaxName($item->tax_id, $item->tax_percentage, $item->tax_group);
                $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp($item->subtotal,$item->tax_id,$item->tax_type,$item->tax_percentage);
            @endphp
            <tr>
                <td>{{ $item->product->item_name."(".$item->product->item_code.")" }}</td>
                <td>{{ $item->qty." ".$item->unit->unit_code }}</td>
                <td style="text-align: right">{{ number_format($item->price, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td style="text-align: right">{{ \Icso\Accounting\Utils\Helpers::getDiscountString($item->discount, $item->discount_type) }}</td>
                <td>{{ $taxname }}</td>
                <td style="text-align: right">{{ number_format($item->subtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            </tr>
            @php
            if(!empty($item->tax_id)){
                $arrTax[] = array(
                    'id' => $item->tax_id,
                    'name' => $taxname,
                    'total' => $taxCalc[\Icso\Accounting\Enums\TypeEnum::PPN]
                );
            }
            @endphp
        @endforeach
        <tr>
            <td colspan="5" style="text-align: right">Subtotal</td>
            <td style="text-align: right">{{number_format($post->subtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        <tr>
            <td style="text-align: right" colspan="5">Diskon</td>
            <td style="text-align: right">{{number_format($post->total_discount, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        @php
        if(!empty($arrTax)){
            $resultTax = \Icso\Accounting\Utils\Helpers::sumTotalsByTaxId($arrTax);
            if(!empty($resultTax)){
                foreach ($resultTax as $item){
            @endphp
            <tr>
                <td style="text-align: right" colspan="5">{{$item['name']}}</td>
                <td style="text-align: right">{{number_format($item['total'],\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
            </tr>
            @php
                }
            }
        }
        @endphp
        <tr>
            <td style="text-align: right" colspan="5">Grand Total</td>
            <td style="text-align: right">{{number_format($post->grandtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
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
