@php use App\Enums\TypeEnum; @endphp
<!DOCTYPE html>
<html>
<head>
    <title>Laporan Retur Pembelian</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        <td class="text-center" colspan="6">Laporan Retur Pembelian</td>
    </tr>
    <tr>
        <td class="text-center" colspan="6">
            {{ \App\Utils\Utility::convert_tanggal($params['fromDate']) }}
            - {{ \App\Utils\Utility::convert_tanggal($params['untilDate']) }}
        </td>
    </tr>
    <tr>
        <td class="text-center" colspan="6"></td>
    </tr>
    <tr>
        <th>Nomor Retur</th>
        <th>Tanggal</th>
        <th>Nama Supplier</th>
        <th>Nomor Penerimaan</th>
        <th>Nomor Invoice</th>
        <th>Keterangan</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($data as $post)
        <tr>
            <td>{{ $post->retur_no }}</td>
            <td>{{ $post->retur_date }}</td>
            <td>{{ optional($post->vendor)->vendor_company_name ?? optional($post->vendor)->vendor_name }}</td>
            <td>{{ optional($post->receive)->receive_no }}</td>
            <td>{{ optional($post->invoice)->invoice_no }}</td>
            <td>{{ $post->note }}</td>
        </tr>

        <tr>
            <td>Nama Item</td>
            <td>Qty</td>
            <td class="text-right">Harga</td>
            <td class="text-right">Diskon</td>
            <td>Pajak</td>
            <td class="text-right">Subtotal</td>
        </tr>

        @php $arrTax = []; @endphp

        @foreach ($post->returproduct as $item)
            @php
                $taxname = \App\Utils\Helpers::getTaxName($item->tax_id, $item->tax_percentage, null);
            @endphp
            <tr>
                <td>{{ !empty($item->product) ? ($item->product->item_name . ' (' . $item->product->item_code . ')') : '' }}</td>
                <td>
                    {{ $item->qty }}
                    {{ !empty($item->unit) && !empty($item->unit->unit_code) ? $item->unit->unit_code : "" }}
                </td>
                <td class="text-right">{{ number_format($item->buy_price, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td class="text-right">{{ \App\Utils\Helpers::getDiscountString($item->discount, $item->discount_type) }}</td>
                <td>{{ $taxname }}</td>
                <td class="text-right">{{ number_format($item->subtotal, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            </tr>

            @php
                if (!empty($item->tax_id)) {
                    // Calculate per-line tax total (single or composite handled by helper)
                    $taxCalc = \App\Utils\Helpers::hitungTaxDpp($item->subtotal, $item->tax_id, $item->tax_type, $item->tax_percentage);
                    $arrTax[] = [
                        'id' => $item->tax_id,
                        'name' => $taxname,
                        'total' => $taxCalc[TypeEnum::PPN] ?? 0,
                    ];
                }
            @endphp
        @endforeach

        <tr>
            <td colspan="5" class="text-right">Subtotal</td>
            <td class="text-right">{{ number_format($post->subtotal, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
        </tr>

        @php
            if(!empty($arrTax)){
                $resultTax = \App\Utils\Helpers::sumTotalsByTaxId($arrTax);
                if(!empty($resultTax)){
                    foreach ($resultTax as $item){
        @endphp
        <tr>
            <td colspan="5" class="text-right">{{ $item['name'] }}</td>
            <td class="text-right">{{ number_format($item['total'], \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</td>
        </tr>
        @php
                    }
                }
            }
        @endphp

        <tr>
            <td colspan="5" class="text-right"><strong>Grand Total</strong></td>
            <td class="text-right"><strong>{{ number_format($post->total, \App\Repositories\Tenant\Utils\SettingRepo::getSeparatorFormat()) }}</strong></td>
        </tr>

        <tr><td colspan="6"></td></tr>
        <tr><td colspan="6"></td></tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
