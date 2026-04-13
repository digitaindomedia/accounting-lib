<h2>Kartu Stok</h2>

@foreach ($rows as $row)

    <br><br>
    <h3>Nama Produk: {{ $row['product']->item_name }} - {{ $row['product']->item_code }}</h3>

    <table border="1" style="width: 100%; border-collapse: collapse">
        <thead>
        <tr style="background: #efefef">
            <th>Tanggal</th>
            <th>Nomor</th>
            <th>Transaksi</th>
            <th>Qty Masuk</th>
            <th>Nilai Masuk</th>
            <th>Qty Keluar</th>
            <th>Nilai Keluar</th>
            <th>Saldo Qty</th>
            <th>Saldo Nilai</th>
        </tr>
        </thead>

        <tbody>
        <tr>
            <td><strong>Saldo Awal</strong></td>
            <td></td><td></td>
            <td></td><td></td>
            <td></td><td></td>
            <td><strong>{{ $row['saldo_awal_qty'] }}</strong></td>
            <td><strong>{{ number_format($row['saldo_awal_nilai'], \Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</strong></td>
        </tr>

        @foreach ($row['details'] as $d)
            <tr>
                <td>{{ $d->inventory_date }}</td>
                <td>{{ $d->transaction_no }}</td>
                <td>{{ $d->transaction_name }}</td>
                <td>{{ number_format($d->qty_in,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($d->total_in,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($d->qty_out,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($d->total_out,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($d->saldo_qty,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
                <td>{{ number_format($d->saldo_nilai,\Icso\Accounting\Repositories\Utils\SettingRepo::getSeparatorFormat()) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

@endforeach
