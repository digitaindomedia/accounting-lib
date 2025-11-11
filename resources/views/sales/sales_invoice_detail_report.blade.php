@php use Icso\Accounting\Enums\TypeEnum; @endphp
<!DOCTYPE html>
<html>
<head>
    <title>Invoice Penjualan</title>
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
        <td style="text-align: center" colspan="5">Laporan Invoice Penjualan</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="5">
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate'])}}
            - {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate'])}}</td>
    </tr>
    <tr>
        <td style="text-align: center" colspan="5"></td>
    </tr>
    <tr>
        <th>Nomor Transaksi</th>
        <th>Tanggal</th>
        <th>No Order</th>
        <th>Nama Customer</th>
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
                    $taxname = \Icso\Accounting\Utils\Helpers::getTaxName($item->tax_id, $item->tax_percentage, $item->tax_group);
                    $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp($item->subtotal,$item->tax_id,$item->tax_type,$item->tax_percentage);
                @endphp
                <tr>
                    <td>{{ !empty($item->product) ? $item->product->item_name."(".$item->product->item_code.")" : $item->service_name }}</td>
                    <td>
                        {{ $item->qty }}
                        {{ !empty($item->unit) && !empty($item->unit->unit_code) ? $item->unit->unit_code : "" }}
                    </td>
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
            @foreach ($post->invoicedelivery as $item)
                @php
                    $receiveRepo = new \Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo(new \Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery());
                    $total = $receiveRepo->getTotalDelivery($item->id);
                @endphp
                <tr>
                    <td>{{$item->delivery->delivery_no}}</td>
                    <td>{{$item->delivery->delivery_date}}</td>
                    <td>{{$item->delivery->warehouse->warehouse_name}}</td>
                    <td style="text-align: right">{{ number_format($total, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                </tr>
                @foreach($item->delivery->deliveryproduct as $val)
                    @php
                        $taxname = \Icso\Accounting\Utils\Helpers::getTaxName($val->tax_id, $val->tax_percentage, $val->tax_group);
                        $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp($val->subtotal,$val->tax_id,$val->tax_type,$val->tax_percentage);
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
            <td style="text-align: right">{{number_format($post->subtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        <tr>
            <td style="text-align: right" colspan="{{$colspan}}">Diskon</td>
            <td style="text-align: right">{{number_format($post->total_discount, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        @php
            if(!empty($arrTax)){
                $resultTax = \Icso\Accounting\Utils\Helpers::sumTotalsByTaxId($arrTax);
                if(!empty($resultTax)){
                    foreach ($resultTax as $item){
        @endphp
        <tr>
            <td style="text-align: right" colspan="{{$colspan}}">{{$item['name']}}</td>
            <td style="text-align: right">{{number_format($item['total'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat())}}</td>
        </tr>
        @php
            }
        }
    }
        @endphp
        <tr>
            <td style="text-align: right" colspan="{{$colspan}}">Grand Total</td>
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
