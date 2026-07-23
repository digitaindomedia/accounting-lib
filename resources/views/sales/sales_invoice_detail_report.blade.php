@php
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

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        <td class="text-center font-bold" colspan="10">Rekapan Invoice Penjualan</td>
    </tr>
    <tr>
        <td class="text-center" colspan="10">
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate'])}}
            - {{\Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate'])}}</td>
    </tr>
    <tr>
        <td class="text-center" colspan="10"></td>
    </tr>
    <tr>
        <th>No</th>
        <th>Nomor Transaksi</th>
        <th>Tanggal</th>
        <th>No Order/Pengiriman</th>
        <th>Nama Customer</th>
        <th class="text-right">Subtotal</th>
        <th class="text-right">Diskon</th>
        <th class="text-right">Pajak</th>
        <th class="text-right">Grand Total</th>
        <th class="text-right">Total HPP</th>
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

            $subtotal = (float) ($post->subtotal ?? 0);
            $discount = (float) ($post->discount_total ?? $post->total_discount ?? 0);
            $tax = (float) ($post->tax_total ?? 0);
            $grandTotal = (float) ($post->grandtotal ?? 0);
            $hpp = (float) ($post->hpp_total ?? 0);

            $totalSubtotal += $subtotal;
            $totalDiscount += $discount;
            $totalTax += $tax;
            $totalGrandTotal += $grandTotal;
            $totalHpp += $hpp;
        @endphp
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $post->invoice_no }}</td>
            <td>{{ $post->invoice_date }}</td>
            <td>{{ !empty($deliveryNos) ? $deliveryNos : (!empty($post->order) ? $post->order->order_no : "-") }}</td>
            <td>{{ optional($post->vendor)->vendor_company_name ?? optional($post->vendor)->vendor_name }}</td>
            <td class="text-right">{{ number_format($subtotal, $separator) }}</td>
            <td class="text-right">{{ number_format($discount, $separator) }}</td>
            <td class="text-right">{{ number_format($tax, $separator) }}</td>
            <td class="text-right">{{ number_format($grandTotal, $separator) }}</td>
            <td class="text-right">{{ number_format($hpp, $separator) }}</td>
        </tr>
    @empty
        <tr>
            <td class="text-center" colspan="10">Data tidak ditemukan</td>
        </tr>
    @endforelse
    <tr>
        <td class="text-right font-bold" colspan="5">Total</td>
        <td class="text-right font-bold">{{ number_format($totalSubtotal, $separator) }}</td>
        <td class="text-right font-bold">{{ number_format($totalDiscount, $separator) }}</td>
        <td class="text-right font-bold">{{ number_format($totalTax, $separator) }}</td>
        <td class="text-right font-bold">{{ number_format($totalGrandTotal, $separator) }}</td>
        <td class="text-right font-bold">{{ number_format($totalHpp, $separator) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
