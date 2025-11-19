<h2>Kartu Stok</h2>

@foreach ($rows as $row)

    <br><br>
    <h3>Nama Produk: {{ $row['product']->product_name }}</h3>

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
            <td><strong>{{ number_format($row['saldo_awal_nilai'], 0, ',', '.') }}</strong></td>
        </tr>

        @foreach ($row['details'] as $d)
            <tr>
                <td>{{ $d->inventory_date }}</td>
                <td>{{ $d->transaction_no }}</td>
                <td>{{ $d->transaction_name }}</td>
                <td>{{ $d->qty_in }}</td>
                <td>{{ number_format($d->value_in,0,',','.') }}</td>
                <td>{{ $d->qty_out }}</td>
                <td>{{ number_format($d->value_out,0,',','.') }}</td>
                <td>{{ $d->saldo_qty }}</td>
                <td>{{ number_format($d->saldo_nilai,0,',','.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

@endforeach
