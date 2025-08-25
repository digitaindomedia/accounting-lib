@php use App\Models\Tenant\Master\Coa; @endphp
<!DOCTYPE html>
<html>
<head>
    <title>{{$productType === \App\Utils\ProductType::ITEM ? "Barang" : "Jasa"}}</title>
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
<h1 style="text-align: center;">{{$productType === \App\Utils\ProductType::ITEM ? "Barang" : "Jasa"}}</h1>
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
        @if($productType == \App\Utils\ProductType::ITEM)
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
            <td>{{ \App\Repositories\Tenant\Master\Product\ProductRepo::getAllCategoriesById($item->id) }}</td>
            <td>{{ !empty(\App\Repositories\Tenant\Master\Product\ProductRepo::getSatuanById($item->id)) ? \App\Repositories\Tenant\Master\Product\ProductRepo::getSatuanById($item->id)->unit_name: "" }}</td>
            <td>{{ $data['selling_price'] }}</td>
            <td>{{ $data['descriptions'] }}</td>
            <td>{{ $data['has_tax'] == 'yes' ? "YA" : "TIDAK" }}</td>
            @if($productType == \App\Utils\ProductType::ITEM)
                @php
                    $coaSediaan = "";
                    if(!empty($item->coa_id)){
                        $findCoaSediaan = Coa::where('id', $item->coa_id)->first();
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
                    if(!empty($item->coa_id)){
                        $findCoaPendapatan = Coa::where('id', $item->coa_id)->first();
                        if(!empty($findCoaPendapatan)){
                            $coaPendapatan = $findCoaPendapatan->coa_name;
                        }
                    }
                    if(!empty($item->coa_biaya_id)){
                        $findCoaBiaya = Coa::where('id', $item->coa_biaya_id)->first();
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
