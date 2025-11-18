<h3>Kartu Hutang</h3>
<p><strong>Nama Vendor:</strong> {{ $vendor->vendor_name }}</p>

<table>
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
        <td>Saldo Awal</td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td>{{ $saldoAwal }}</td>
    </tr>

    @foreach($transaksi as $t)
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
