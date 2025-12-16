@php use Icso\Accounting\Models\Master\Coa; @endphp
        <!DOCTYPE html>
<html>
<head>
    <title>{{$productType === \Icso\Accounting\Utils\ProductType::ITEM ? "Barang" : "Jasa"}}</title>
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
    </style>
</head>
<body>
<h1 style="text-align: center;">{{$productType === \Icso\Accounting\Utils\ProductType::ITEM ? "Barang" : "Jasa"}}</h1>
<table>
    <thead>
    <tr>
        <th>Nama</th>
        <th>Kode</th>
        <th>Kategori</th>
        <th>Satuan</th>
        <th>Harga</th>
        <th>Deskripsi</th>
        <th>Kena Pajak</th>
        @if($productType == \Icso\Accounting\Utils\ProductType::ITEM)
            <th>Akun Sediaan</th>
        @else
            <th>Akun Pendapatan</th>
            <th>Akun Biaya</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach ($arrData as $data)
        <tr>
            <td>{{ $data['item_name'] }}</td>
            <td>{{ $data['item_code'] }}</td>
            <td>{{ \Icso\Accounting\Repositories\Master\Product\ProductRepo::getAllCategoriesById($data['id']) }}</td>
            <td>{{ !empty(\Icso\Accounting\Repositories\Master\Product\ProductRepo::getSatuanById($data['id'])) ? \Icso\Accounting\Repositories\Master\Product\ProductRepo::getSatuanById($item->id)->unit_name: "" }}</td>
            <td>{{ $data['selling_price'] }}</td>
            <td>{{ $data['descriptions'] }}</td>
            <td>{{ $data['has_tax'] == 'yes' ? "YA" : "TIDAK" }}</td>
            @if($productType == \Icso\Accounting\Utils\ProductType::ITEM)
                @php
                    $coaSediaan = "";
                    if(!empty($data['coa_id'])){
                        $findCoaSediaan = Coa::where('id', $data['coa_id'])->first();
                        if(!empty($findCoaSediaan)){
                            $coaSediaan = $findCoaSediaan->coa_name;
                        }
                    }
                @endphp
                <td>{{$coaSediaan}}</td>
            @else
                @php
                    $coaPendapatan = "";
                    $coaBiaya = "";
                    if(!empty($data['coa_id'])){
                        $findCoaPendapatan = Coa::where('id', $data['coa_id'])->first();
                        if(!empty($findCoaPendapatan)){
                            $coaPendapatan = $findCoaPendapatan->coa_name;
                        }
                    }
                    if(!empty($data['coa_biaya_id'])){
                        $findCoaBiaya = Coa::where('id', $data['coa_biaya_id'])->first();
                        if(!empty($findCoaBiaya)){
                            $coaBiaya = $findCoaBiaya->coa_name;
                        }
                    }
                @endphp
                <td>{{$coaPendapatan}}</td>
                <td>{{$coaBiaya}}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
