<h2>Kartu Hutang Supplier</h2>

@foreach ($listVendor as $item)

    <br>
    <h3>Nama Vendor: {{ $item['vendor']->vendor_name }}</h3>

    <table border="1">
        <thead>
        <tr>
            <th>Tanggal</th>
            <th>Nomor</th>
            <th>Keterangan</th>
            <th>Debet</th>
            <th>Kredit</th>
            <th>Saldo</th>
        </tr>
        </thead>

        <tbody>
        <tr>
            <td><strong>Saldo Awal</strong></td>
            <td></td><td></td>
            <td></td><td></td>
            <td><strong>{{ $item['saldoAwal'] }}</strong></td>
        </tr>

        @foreach($item['transaksi'] as $t)
            <tr>
                <td>{{ $t->tanggal }}</td>
                <td>{{ $t->nomor }}</td>
                <td>{{ $t->note }}</td>
                <td>{{ $t->debet }}</td>
                <td>{{ $t->kredit }}</td>
                <td>{{ $t->saldo }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <hr>

@endforeach
