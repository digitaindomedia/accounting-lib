@php
    use Icso\Accounting\Enums\TypeEnum;
    use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
    use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo;
@endphp

        <!DOCTYPE html>
<html>
<head>
    <title>Invoice Pembelian</title>
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

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .no-border td {
            border: none;
            padding: 4px;
        }

        .section-gap td {
            border: none;
            height: 12px;
        }

        .sub-header {
            background-color: #fafafa;
            font-weight: bold;
        }
    </style>
</head>
<body>

<!-- ===== HEADER LAPORAN ===== -->
<table>
    <tr class="no-border">
        <td class="text-center" colspan="6">
            <strong>LAPORAN INVOICE PEMBELIAN</strong>
        </td>
    </tr>
    <tr class="no-border">
        <td class="text-center" colspan="6">
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate']) }}
            -
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate']) }}
        </td>
    </tr>
</table>

<!-- ===== DATA ===== -->
<table>
    <thead>
    <tr>
        <th width="18%">Nomor Transaksi</th>
        <th width="12%">Tanggal</th>
        <th width="18%">No Order</th>
        <th width="32%">Nama Supplier</th>
        <th width="20%"></th>
        <th width="20%"></th>
    </tr>
    </thead>

    <tbody>
    @foreach ($data as $post)

        <!-- Header Invoice -->
        <tr>
            <td>{{ $post->invoice_no }}</td>
            <td class="text-center">{{ $post->invoice_date }}</td>
            <td>{{ !empty($post->order) ? $post->order->order_no : '-' }}</td>
            <td colspan="3">{{ $post->vendor->vendor_company_name }}</td>
        </tr>

        @php
            $arrTax = [];
            $colspan = empty($post->order) ? 5 : 4;
        @endphp

                <!-- ===== TANPA ORDER ===== -->
        @if(empty($post->order))

            <tr class="sub-header">
                <td colspan="2">Nama Item</td>
                <td class="text-center">Qty</td>
                <td class="text-right">Harga</td>
                <td class="text-right">Diskon</td>
                <td class="text-right">Subtotal</td>
            </tr>

            @foreach ($post->orderproduct as $item)
                @php
                    $taxname = \Icso\Accounting\Utils\Helpers::getTaxName(
                        $item->tax_id, $item->tax_percentage, $item->tax_group
                    );
                    $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp(
                        $item->subtotal, $item->tax_id, $item->tax_type, $item->tax_percentage
                    );
                @endphp
                <tr>
                    <td colspan="2">
                        {{ !empty($item->product)
                            ? $item->product->item_name.' ('.$item->product->item_code.')'
                            : $item->service_name }}
                    </td>
                    <td class="text-center">
                        {{ $item->qty }}
                        {{ optional($item->unit)->unit_code }}
                    </td>
                    <td class="text-right">
                        {{ number_format($item->price, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}
                    </td>
                    <td class="text-right">
                        {{ \Icso\Accounting\Utils\Helpers::getDiscountString($item->discount, $item->discount_type) }}
                    </td>
                    <td class="text-right">
                        {{ number_format($item->subtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}
                    </td>
                </tr>

                @php
                    if($item->tax_id){
                        $arrTax[] = [
                            'id' => $item->tax_id,
                            'name' => $taxname,
                            'total' => $taxCalc[TypeEnum::PPN]
                        ];
                    }
                @endphp
            @endforeach

            <!-- ===== DENGAN ORDER ===== -->
        @else

            <tr>
                <td colspan="6" style="padding:0">
                    <table width="100%" border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse">
                        <thead>
                        <tr style="background:#fafafa;font-weight:bold;text-align:center">
                            <td width="20%">No Penerimaan</td>
                            <td width="15%">Tanggal</td>
                            <td width="45%">Gudang</td>
                            <td width="20%" style="text-align:right">Total</td>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($post->invoicereceived as $item)
                            @php
                                $receiveRepo = new ReceiveRepo(new PurchaseReceived());
                                $total = $receiveRepo->getTotalReceived($item->id);
                            @endphp
                            <tr>
                                <td>{{ $item->receive->receive_no }}</td>
                                <td class="text-center">{{ $item->receive->receive_date }}</td>
                                <td>{{ $item->receive->warehouse->warehouse_name }}</td>
                                <td class="text-right">
                                    {{ number_format($total, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}
                                </td>
                            </tr>

                            @foreach($item->receive->receiveproduct as $val)
                                @php
                                    $taxname = \Icso\Accounting\Utils\Helpers::getTaxName(
                                        $val->tax_id,
                                        $val->tax_percentage,
                                        $val->tax_group
                                    );
                                    $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp(
                                        $val->subtotal,
                                        $val->tax_id,
                                        $val->tax_type,
                                        $val->tax_percentage
                                    );

                                    if($val->tax_id){
                                        $arrTax[] = [
                                            'id' => $val->tax_id,
                                            'name' => $taxname,
                                            'total' => $taxCalc[TypeEnum::PPN]
                                        ];
                                    }
                                @endphp
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>

            @php
                $colspan = 5;
            @endphp

        @endif

        <!-- ===== TOTAL ===== -->
        <tr>
            <td colspan="{{ $colspan }}" class="text-right">Subtotal</td>
            <td class="text-right">
                {{ number_format($post->subtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}
            </td>
        </tr>

        <tr>
            <td colspan="{{ $colspan }}" class="text-right">Diskon</td>
            <td class="text-right">
                {{ number_format($post->total_discount, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}
            </td>
        </tr>

        @php
            $resultTax = \Icso\Accounting\Utils\Helpers::sumTotalsByTaxId($arrTax);
        @endphp
        @foreach ($resultTax ?? [] as $tax)
            <tr>
                <td colspan="{{ $colspan }}" class="text-right">{{ $tax['name'] }}</td>
                <td class="text-right">
                    {{ number_format($tax['total'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}
                </td>
            </tr>
        @endforeach

        <tr>
            <td colspan="{{ $colspan }}" class="text-right"><strong>Grand Total</strong></td>
            <td class="text-right">
                <strong>{{ number_format($post->grandtotal, \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</strong>
            </td>
        </tr>

        <tr class="section-gap"><td colspan="6"></td></tr>

    @endforeach
    </tbody>
</table>

</body>
</html>
