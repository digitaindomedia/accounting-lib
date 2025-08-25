@php use App\Enums\TypeEnum; @endphp
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
<table>
    <thead>
    <tr>
        <td style="text-align: center" colspan="5">Laporan Invoice Pembelian</td>
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
                {{$post->invoice_no}}
            </td>
            <td>
                {{$post->invoice_date}}
            </td>
            <td>
                {{
                    !empty($post->order) ? $post->order->order_no : "-"
                }}
            </td>
            <td>
                {{ $post->vendor->vendor_company_name }}
            </td>
        </tr>
        @php
            $arrTax = [];
            $colspan = 0;
        @endphp
        @if(empty($post->order))
            @php
                $colspan = 5;
            @endphp
            <tr>
                <td>Nama Item</td>
                <td>Qty</td>
                <td style="text-align: right">Harga</td>
                <td style="text-align: right">Diskon</td>
                <td>Pajak</td>
                <td style="text-align: right">Subtotal</td>
            </tr>
            @foreach ($post->orderproduct as $item)
                @php
                    $taxname = \App\Utils\Helpers::getTaxName($item->tax_id, $item->tax_percentage, $item->tax_group);
                    $taxCalc = \App\Utils\Helpers::hitungTaxDpp($item->subtotal,$item->tax_id,$item->tax_type,$item->tax_percentage);
                @endphp
                <tr>
                    <td>{{ !empty($item->product) ? $item->product->item_name."(".$item->product->item_code.")" : $item->service_name }}</td>
                    <td>
                        {{ $item->qty }}
                        {{ !empty($item->unit) && !empty($item->unit->unit_code) ? $item->unit->unit_code : "" }}
                    </td>
                    <td style="text-align: right">{{ number_format($item->price, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                    <td style="text-align: right">{{ \App\Utils\Helpers::getDiscountString($item->discount, $item->discount_type) }}</td>
                    <td>{{ $taxname }}</td>
                    <td style="text-align: right">{{ number_format($item->subtotal, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                </tr>
                @php
                    if(!empty($item->tax_id)){
                        $arrTax[] = array(
                            'id' => $item->tax_id,
                            'name' => $taxname,
                            'total' => $taxCalc[TypeEnum::PPN]
                        );
                    }
                @endphp
            @endforeach
        @else
            @php
                $colspan = 3;
            @endphp
            <tr>
                <td>No Penerimaan</td>
                <td>Tanggal</td>
                <td>Gudang</td>
                <td style="text-align: right">Total</td>
            </tr>
            @foreach ($post->invoicereceived as $item)
                @php
                    $receiveRepo = new \App\Repositories\Tenant\Pembelian\Received\ReceiveRepo(new \App\Models\Tenant\Pembelian\Penerimaan\PurchaseReceived());
                    $total = $receiveRepo->getTotalReceived($item->id);
                @endphp
                <tr>
                    <td>{{$item->receive->receive_no}}</td>
                    <td>{{$item->receive->receive_date}}</td>
                    <td>{{$item->receive->warehouse->warehouse_name}}</td>
                    <td style="text-align: right">{{ number_format($total, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                </tr>
                @foreach($item->receive->receiveproduct as $val)
                    @php
                        $taxname = \App\Utils\Helpers::getTaxName($val->tax_id, $val->tax_percentage, $val->tax_group);
                        $taxCalc = \App\Utils\Helpers::hitungTaxDpp($val->subtotal,$val->tax_id,$val->tax_type,$val->tax_percentage);
                        if(!empty($val->tax_id)){
                            $arrTax[] = array(
                                'id' => $val->tax_id,
                                'name' => $taxname,
                                'total' => $taxCalc[TypeEnum::PPN]
                            );
                        }
                    @endphp
                @endforeach
            @endforeach
        @endif

        <tr>
            <td colspan="{{$colspan}}" style="text-align: right">Subtotal</td>
            <td style="text-align: right">{{number_format($post->subtotal, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        <tr>
            <td style="text-align: right" colspan="{{$colspan}}">Diskon</td>
            <td style="text-align: right">{{number_format($post->total_discount, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        @php
            if(!empty($arrTax)){
                $resultTax = \App\Utils\Helpers::sumTotalsByTaxId($arrTax);
                if(!empty($resultTax)){
                    foreach ($resultTax as $item){
        @endphp
        <tr>
            <td style="text-align: right" colspan="{{$colspan}}">{{$item['name']}}</td>
            <td style="text-align: right">{{number_format($item['total'], \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        @php
            }
        }
    }
        @endphp
        <tr>
            <td style="text-align: right" colspan="{{$colspan}}">Grand Total</td>
            <td style="text-align: right">{{number_format($post->grandtotal, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat())}}</td>
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
