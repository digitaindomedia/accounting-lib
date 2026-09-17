@php
    use Icso\Accounting\Enums\TypeEnum;

    $separator = \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat();
    $totalPpn = 0;
    $totalSubtotal = 0;
@endphp
<!DOCTYPE html>
<html>
<head>
    <title>Invoice Pembelian Detail</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        <td class="text-center font-bold" colspan="12">Laporan Invoice Pembelian Detail By Supplier</td>
    </tr>
    <tr>
        <td class="text-center" colspan="12">
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['fromDate']) }}
            -
            {{ \Icso\Accounting\Utils\Utility::convert_tanggal($params['untilDate']) }}
        </td>
    </tr>
    <tr>
        <td class="text-center" colspan="12"></td>
    </tr>
    <tr>
        <th>SUPPLIER</th>
        <th>KATEGORI</th>
        <th>KODE</th>
        <th>BARANG</th>
        <th>NO INVOICE</th>
        <th>TANGGAL</th>
        <th>QTY</th>
        <th>SATUAN</th>
        <th>HARGA</th>
        <th>DISKON</th>
        <th>PPN</th>
        <th>SUBTOTAL</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($data as $post)
        @php
            $supplier = optional($post->vendor)->vendor_company_name ?? optional($post->vendor)->vendor_name ?? '-';
            $items = !empty($post->orderproduct) && $post->orderproduct->isNotEmpty()
                ? $post->orderproduct
                : collect();

            if ($items->isEmpty() && !empty($post->order)) {
                foreach ($post->invoicereceived ?? [] as $invoiceReceived) {
                    $receiveProducts = !empty($invoiceReceived->receive)
                        ? $invoiceReceived->receive->receiveproduct
                        : collect();
                    $items = $items->merge($receiveProducts);
                }
            }
        @endphp

        @forelse ($items as $item)
            @php
                $product = $item->product ?? null;
                $category = !empty($product)
                    ? $product->categories->pluck('category_name')->filter()->implode(', ')
                    : '';
                $price = (float) ($item->price ?? $item->buy_price ?? 0);
                $subtotal = (float) ($item->subtotal ?? 0);
                $taxCalc = \Icso\Accounting\Utils\Helpers::hitungTaxDpp(
                    $subtotal,
                    $item->tax_id,
                    $item->tax_type,
                    $item->tax_percentage
                );
                $ppn = (float) ($taxCalc[TypeEnum::PPN] ?? 0);

                $totalPpn += $ppn;
                $totalSubtotal += $subtotal;
            @endphp
            <tr>
                <td>{{ $supplier }}</td>
                <td>{{ !empty($category) ? $category : '-' }}</td>
                <td>{{ $product->item_code ?? '-' }}</td>
                <td>{{ $product->item_name ?? $item->service_name ?? '-' }}</td>
                <td>{{ $post->invoice_no }}</td>
                <td class="text-center">{{ $post->invoice_date }}</td>
                <td class="text-right">{{ number_format((float) ($item->qty ?? 0), $separator) }}</td>
                <td>{{ optional($item->unit)->unit_code ?? '-' }}</td>
                <td class="text-right">{{ number_format($price, $separator) }}</td>
                <td class="text-right">{{ \Icso\Accounting\Utils\Helpers::getDiscountString($item->discount, $item->discount_type) }}</td>
                <td class="text-right">{{ number_format($ppn, $separator) }}</td>
                <td class="text-right">{{ number_format($subtotal, $separator) }}</td>
            </tr>
        @empty
            <tr>
                <td>{{ $supplier }}</td>
                <td colspan="11" class="text-center">Item tidak ditemukan</td>
            </tr>
        @endforelse
    @empty
        <tr>
            <td class="text-center" colspan="12">Data tidak ditemukan</td>
        </tr>
    @endforelse

    <tr>
        <td class="text-right font-bold" colspan="10">Total</td>
        <td class="text-right font-bold">{{ number_format($totalPpn, $separator) }}</td>
        <td class="text-right font-bold">{{ number_format($totalSubtotal, $separator) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
