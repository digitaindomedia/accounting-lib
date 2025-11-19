<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 6px; }
        th { background: #efefef; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        h2 {text-align: center}
    </style>
</head>
<body>

<h2>Ringkasan Kartu Stok</h2>
<p class="text-center">Periode: {{ $fromDate }} s/d {{ $untilDate }}</p>

<table>
    <thead>
    <tr>
        <th>Nama Produk</th>
        <th>Saldo Awal</th>
        <th>Qty Masuk</th>
        <th>Qty Keluar</th>
        <th>Saldo Akhir</th>
    </tr>
    </thead>

    <tbody>
    @foreach ($summary as $row)
        <tr>
            <td>{{ $row['product_name'] }} ({{ $row['product_code'] }})</td>

            <td class="text-right">{{ number_format($row['saldo_awal'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($row['qty_in'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($row['qty_out'], 0, ',', '.') }}</td>

            <td class="text-right"><strong>{{ number_format($row['saldo_akhir'], 0, ',', '.') }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
