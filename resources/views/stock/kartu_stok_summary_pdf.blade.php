<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: sans-serif; font-size: 10px; color: #1f2937; }
        .report-page { page-break-after: always; }
        .report-page:last-child { page-break-after: auto; }
        h2 { margin: 0; text-align: center; font-size: 16px; }
        .period { margin: 4px 0 12px; text-align: center; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 0.5px solid #64748b; padding: 5px 6px; vertical-align: middle; }
        th { background: #dbeafe; font-weight: bold; text-align: center; }
        .name { width: 40%; }
        .number { width: 15%; text-align: right; }
        .saldo-akhir { font-weight: bold; background: #f0fdf4; }
    </style>
</head>
<body>

@forelse ($summaryPages as $page)
    <section class="report-page">
        <h2>Ringkasan Kartu Stok</h2>
        <p class="period">Periode: {{ $fromDate }} s/d {{ $untilDate }}</p>

        <table>
            <thead>
            <tr>
                <th class="name">Nama Barang</th>
                <th>Saldo Awal</th>
                <th>Penambahan</th>
                <th>Pengurangan</th>
                <th>Saldo Akhir</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($page as $row)
                <tr>
                    <td class="name">{{ $row['product_name'] }} ({{ $row['product_code'] }})</td>
                    <td class="number">{{ number_format($row['saldo_awal'], 0, ',', '.') }}</td>
                    <td class="number">{{ number_format($row['qty_in'], 0, ',', '.') }}</td>
                    <td class="number">{{ number_format($row['qty_out'], 0, ',', '.') }}</td>
                    <td class="number saldo-akhir">{{ number_format($row['saldo_akhir'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@empty
    <h2>Ringkasan Kartu Stok</h2>
    <p class="period">Tidak ada data untuk periode {{ $fromDate }} s/d {{ $untilDate }}.</p>
@endforelse

</body>
</html>
