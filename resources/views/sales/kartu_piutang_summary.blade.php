<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 6px; }
        th { background: #eee; font-weight: bold; }
        h3 { margin-bottom: 0; text-align: center}
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

<h3>Ringkasan Kartu Piutang</h3>
<p class="text-center">Periode: {{ $fromDate }} s/d {{ $untilDate }}</p>

<table>
    <thead>
    <tr>
        <th>Nama Vendor</th>
        <th class="text-right">Saldo Awal</th>
        <th class="text-right">Penjualan</th>
        <th class="text-right">Pelunasan</th>
        <th class="text-right">Saldo Akhir</th>
    </tr>
    </thead>

    <tbody>
    @foreach ($summary as $row)
        <tr>
            <td>{{ $row['vendor_name'] }}</td>
            <td class="text-right">{{ number_format($row['saldo_awal'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($row['penjualan'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($row['pelunasan'], 0, ',', '.') }}</td>
            <td class="text-right"><strong>{{ number_format($row['saldo_akhir'], 0, ',', '.') }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>
