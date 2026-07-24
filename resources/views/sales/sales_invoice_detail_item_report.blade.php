@php
    use Icso\Accounting\Enums\TypeEnum;

    $separator = \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat();
    $totalSubtotal = 0;
    $totalDiscount = 0;
    $totalTax = 0;
    $totalGrandTotal = 0;
    $totalHpp = 0;
@endphp
<!DOCTYPE html>
<html>
<head>
    <title>Detail Invoice Penjualan</title>
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
        .font-bold { font-weight: bold; }
        .sub-header { background-color: #fafafa; font-weight: bold; }
        .section-gap td { border: none; height: 12px; }
    </style>
</head>
<body>
<table>
    <tr>
        <td class="text-center font-bold" colspan="7">Laporan Invoice Penjualan Detail</td>
    </tr>
    <tr>
        <td class="text-center" colspan="7">
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate']) }}
            -
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate']) }}
        </td>
    </tr>
</table>

<table>
    <thead>
    <tr>
        <th width="16%">Nomor Transaksi</th>
        <th width="10%">Tanggal</th>
        <th width="18%">No Order/Pengiriman</th>
        <th width="28%">Nama Customer</th>
        <th width="10%">Status</th>
        <th width="9%" class="text-right">Tagihan</th>
        <th width="9%" class="text-right">Sisa Tagihan</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($data as $post)
        @php
            $deliveryNos = !empty($post->invoicedelivery)
                ? $post->invoicedelivery->map(function ($item) {
                    return !empty($item->delivery) ? $item->delivery->delivery_no : null;
                })->filter()->implode(', ')
                : '';

            $invoiceSubtotal = (float) ($post->subtotal ?? 0);
            $invoiceDiscount = (float) ($post->discount_total ?? $post->total_discount ?? 0);
            $invoiceTax = (float) ($post->tax_total ?? 0);
            $invoiceGrandTotal = (float) ($post->grandtotal ?? 0);
            $invoiceHpp = (float) ($post->hpp_total ?? 0);

            $totalSubtotal += $invoiceSubtotal;
            $totalDiscount += $invoiceDiscount;
            $totalTax += $invoiceTax;
            $totalGrandTotal += $invoiceGrandTotal;
            $totalHpp += $invoiceHpp;
        @endphp

        <tr>
            <td>{{ $post->invoice_no }}</td>
            <td class="text-center">{{ $post->invoice_date }}</td>
            <td>{{ !empty($deliveryNos) ? $deliveryNos : (!empty($post->order) ? $post->order->order_no : '-') }}</td>
            <td>{{ optional($post->vendor)->vendor_company_name ?? optional($post->vendor)->vendor_name }}</td>
            <td class="text-center">{{ $post->invoice_status ?? '-' }}</td>
            <td class="text-right">{{ number_format($invoiceGrandTotal, $separator) }}</td>
            <td class="text-right">{{ number_format((float) ($post->left_bill ?? 0), $separator) }}</td>
        </tr>

        <tr class="sub-header">
            <td colspan="2">Nama Item</td>
            <td class="text-center">Qty</td>
            <td class="text-right">Harga</td>
            <td class="text-right">HPP</td>
            <td class="text-right">Diskon</td>
            <td class="text-right">Subtotal</td>
        </tr>

        @php $arrTax = []; @endphp

        @if(empty($post->order))
            @foreach ($post->orderproduct as $item)
                @php
                    $taxname = \Icso\Accounting\Utils\Helpers::getTaxName($item->tax_id, $item->tax_percentage, $item->tax_group);
                    $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp($item->subtotal, $item->tax_id, $item->tax_type, $item->tax_percentage);
                @endphp
                <tr>
                    <td colspan="2">
                        {{ !empty($item->product) ? $item->product->item_name.' ('.$item->product->item_code.')' : $item->service_name }}
                    </td>
                    <td class="text-center">
                        {{ $item->qty }}
                        {{ optional($item->unit)->unit_code }}
                    </td>
                    <td class="text-right">{{ number_format((float) ($item->price ?? 0), $separator) }}</td>
                    <td class="text-right">{{ number_format((float) ($item->hpp_total ?? $item->subtotal_hpp ?? 0), $separator) }}</td>
                    <td class="text-right">{{ \Icso\Accounting\Utils\Helpers::getDiscountString($item->discount, $item->discount_type) }}</td>
                    <td class="text-right">{{ number_format((float) ($item->subtotal ?? 0), $separator) }}</td>
                </tr>
                @php
                    if (!empty($item->tax_id)) {
                        $arrTax[] = [
                            'id' => $item->tax_id,
                            'name' => $taxname,
                            'total' => $taxCalc[TypeEnum::PPN] ?? 0,
                        ];
                    }
                @endphp
            @endforeach
        @else
            @foreach ($post->invoicedelivery as $invoiceDelivery)
                @php
                    $deliveryProducts = !empty($invoiceDelivery->delivery)
                        ? $invoiceDelivery->delivery->deliveryproduct
                        : collect();
                @endphp
                @foreach($deliveryProducts as $item)
                    @php
                        $taxname = \Icso\Accounting\Utils\Helpers::getTaxName($item->tax_id, $item->tax_percentage, $item->tax_group);
                        $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp($item->subtotal, $item->tax_id, $item->tax_type, $item->tax_percentage);
                    @endphp
                    <tr>
                        <td colspan="2">
                            {{ !empty($item->product) ? $item->product->item_name.' ('.$item->product->item_code.')' : '-' }}
                        </td>
                        <td class="text-center">
                            {{ $item->qty }}
                            {{ optional($item->unit)->unit_code }}
                        </td>
                        <td class="text-right">{{ number_format((float) ($item->sell_price ?? 0), $separator) }}</td>
                        <td class="text-right">{{ number_format((float) ($item->hpp_total ?? $item->subtotal_hpp ?? 0), $separator) }}</td>
                        <td class="text-right">{{ \Icso\Accounting\Utils\Helpers::getDiscountString($item->discount, $item->discount_type) }}</td>
                        <td class="text-right">{{ number_format((float) ($item->subtotal ?? 0), $separator) }}</td>
                    </tr>
                    @php
                        if (!empty($item->tax_id)) {
                            $arrTax[] = [
                                'id' => $item->tax_id,
                                'name' => $taxname,
                                'total' => $taxCalc[TypeEnum::PPN] ?? 0,
                            ];
                        }
                    @endphp
                @endforeach
            @endforeach
        @endif

        @php $resultTax = \Icso\Accounting\Utils\Helpers::sumTotalsByTaxId($arrTax); @endphp

        <tr>
            <td colspan="6" class="text-right">Subtotal</td>
            <td class="text-right">{{ number_format($invoiceSubtotal, $separator) }}</td>
        </tr>
        <tr>
            <td colspan="6" class="text-right">Diskon</td>
            <td class="text-right">{{ number_format($invoiceDiscount, $separator) }}</td>
        </tr>
        @foreach ($resultTax ?? [] as $tax)
            <tr>
                <td colspan="6" class="text-right">{{ $tax['name'] }}</td>
                <td class="text-right">{{ number_format((float) ($tax['total'] ?? 0), $separator) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="6" class="text-right">Grand Total</td>
            <td class="text-right font-bold">{{ number_format($invoiceGrandTotal, $separator) }}</td>
        </tr>
        <tr>
            <td colspan="6" class="text-right">Total HPP</td>
            <td class="text-right font-bold">{{ number_format($invoiceHpp, $separator) }}</td>
        </tr>
        <tr class="section-gap"><td colspan="7"></td></tr>
    @empty
        <tr>
            <td class="text-center" colspan="7">Data tidak ditemukan</td>
        </tr>
    @endforelse

    <tr>
        <td class="text-right font-bold" colspan="6">Total Subtotal</td>
        <td class="text-right font-bold">{{ number_format($totalSubtotal, $separator) }}</td>
    </tr>
    <tr>
        <td class="text-right font-bold" colspan="6">Total Diskon</td>
        <td class="text-right font-bold">{{ number_format($totalDiscount, $separator) }}</td>
    </tr>
    <tr>
        <td class="text-right font-bold" colspan="6">Total Pajak</td>
        <td class="text-right font-bold">{{ number_format($totalTax, $separator) }}</td>
    </tr>
    <tr>
        <td class="text-right font-bold" colspan="6">Total Grand Total</td>
        <td class="text-right font-bold">{{ number_format($totalGrandTotal, $separator) }}</td>
    </tr>
    <tr>
        <td class="text-right font-bold" colspan="6">Total HPP</td>
        <td class="text-right font-bold">{{ number_format($totalHpp, $separator) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
