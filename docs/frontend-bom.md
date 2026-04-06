# Frontend Handoff: BOM dan Manufacturing

Dokumen ini menjelaskan kebutuhan frontend untuk fitur:
- Bill of Material (BOM)
- Production Order
- Dual mode manufacturing:
  - `pre_produce`
  - `auto_consume`

Semua endpoint di bawah mengikuti pola tenant:

```text
/{tenant}/...
```

Contoh:

```text
/tenant-a/manufacturing/bom/get-all
```

## 1. Konsep Utama

### A. BOM
BOM adalah resep/komposisi standar untuk menghasilkan 1 produk jadi.

Contoh:
- Produk hasil: `Burger`
- Bahan:
  - `Roti`
  - `Patty`
  - `Saus`

### B. Production Order
Production Order adalah transaksi produksi aktual:
- bahan baku keluar
- barang jadi masuk

### C. Manufacturing Mode
Setiap BOM bisa punya mode:

- `pre_produce`
  Produksi dilakukan lebih dulu, stok barang jadi masuk, lalu modul penjualan menjual barang jadi seperti biasa.

- `auto_consume`
  Saat penjualan terjadi, sistem otomatis membuat produksi di belakang layar berdasarkan BOM aktif.

- `both`
  Bisa dipakai dua-duanya.

### D. Auto Consume Trigger
Kapan auto-consume dijalankan:

- `invoice`
- `delivery`
- `both`

## 2. Endpoint Yang Perlu Dipakai Frontend

### A. BOM

#### 1. Get List BOM

`GET /{tenant}/manufacturing/bom/get-all`

Query params opsional:
- `q`
- `page`
- `perpage`
- `product_id`
- `use_case`
- `status`

Contoh:

```http
GET /tenant-a/manufacturing/bom/get-all?q=burger&page=0&perpage=10&status=active
```

Response umum:

```json
{
  "status": true,
  "message": "Data berhasil ditemukan",
  "data": [],
  "has_more": false,
  "total": 0
}
```

#### 2. Get Detail BOM

`GET /{tenant}/manufacturing/bom/find-by-id?id={bom_id}`

#### 3. Save BOM

`POST /{tenant}/manufacturing/bom/save-data`

Payload minimum:

```json
{
  "user_id": 1,
  "bom_name": "Resep Burger",
  "product_id": 100,
  "output_unit_id": 2,
  "output_qty": 1,
  "use_case": "restaurant",
  "manufacturing_mode": "both",
  "auto_consume_trigger": "invoice",
  "status": "active",
  "items": [
    {
      "product_id": 10,
      "unit_id": 2,
      "qty": 1,
      "waste_percentage": 0,
      "item_role": "material",
      "is_optional": false,
      "note": ""
    },
    {
      "product_id": 11,
      "unit_id": 2,
      "qty": 1
    }
  ]
}
```

Untuk update, kirim `id`.

Field penting:
- `bom_name`: nama BOM/resep
- `product_id`: produk hasil
- `output_unit_id`: satuan hasil
- `output_qty`: qty standar hasil
- `bom_version`: opsional, default `1.0`
- `use_case`: misal `restaurant` atau `general`
- `manufacturing_mode`: `pre_produce`, `auto_consume`, `both`
- `auto_consume_trigger`: `invoice`, `delivery`, `both`
- `status`: `active` atau status lain yang Anda pakai
- `items`: daftar bahan

Field item:
- `product_id`
- `unit_id`
- `qty`
- `waste_percentage`
- `item_role`
- `is_optional`
- `note`

#### 4. Delete BOM

`DELETE /{tenant}/manufacturing/bom/delete-by-id?id={bom_id}`

#### 5. Bulk Delete BOM

`DELETE /{tenant}/manufacturing/bom/delete-all`

Payload:

```json
{
  "ids": [1, 2, 3]
}
```

#### 6. Preview Kebutuhan Bahan

`GET /{tenant}/manufacturing/bom/preview`

Query params:
- `bom_id` wajib
- `output_qty` wajib
- `warehouse_id` opsional tapi sangat disarankan
- `stock_date` opsional, default hari ini

Contoh:

```http
GET /tenant-a/manufacturing/bom/preview?bom_id=1&output_qty=25&warehouse_id=2&stock_date=2026-04-04
```

Contoh response:

```json
{
  "status": true,
  "message": "Preview kebutuhan bahan berhasil dibuat",
  "data": {
    "bom": {
      "id": 1,
      "bom_name": "Resep Burger"
    },
    "warehouse_id": 2,
    "stock_date": "2026-04-04",
    "output_qty": 25,
    "materials": [
      {
        "bom_item_id": 1,
        "product_id": 10,
        "product_name": "Roti Burger",
        "unit_id": 2,
        "unit_name": "pcs",
        "qty_per_output": 1,
        "required_qty": 25,
        "available_stock": 40,
        "shortage_qty": 0,
        "waste_percentage": 0,
        "hpp": 2000,
        "estimated_cost": 50000,
        "item_role": "material",
        "is_optional": false,
        "note": null
      }
    ],
    "summary": {
      "material_count": 1,
      "estimated_material_cost": 50000,
      "estimated_hpp_per_unit": 2000
    }
  }
}
```

Frontend bisa memakai endpoint ini untuk:
- preview kebutuhan bahan sebelum save production order
- validasi stok bahan cukup/tidak
- tampilkan estimasi HPP
- preview auto-consume pada layar POS atau invoice

### B. Production Order

#### 1. Get List Production Order

`GET /{tenant}/manufacturing/production-order/get-all`

Query params opsional:
- `q`
- `page`
- `perpage`
- `from_date`
- `until_date`
- `warehouse_id`
- `status_production`
- `product_id`

#### 2. Get Detail Production Order

`GET /{tenant}/manufacturing/production-order/find-by-id?id={id}`

#### 3. Save Production Order

`POST /{tenant}/manufacturing/production-order/save-data`

Mode A: pakai BOM

```json
{
  "user_id": 1,
  "production_date": "2026-04-04",
  "warehouse_id": 1,
  "bom_id": 1,
  "product_id": 100,
  "output_unit_id": 2,
  "planned_qty": 10,
  "actual_qty": 9.5,
  "status_production": "finished",
  "coa_id": 123,
  "note": "Batch pagi"
}
```

Mode B: manual tanpa BOM

```json
{
  "user_id": 1,
  "production_date": "2026-04-04",
  "warehouse_id": 1,
  "product_id": 100,
  "output_unit_id": 2,
  "planned_qty": 10,
  "actual_qty": 10,
  "coa_id": 123,
  "materials": [
    {
      "product_id": 10,
      "unit_id": 2,
      "qty_planned": 10,
      "qty_actual": 10
    }
  ],
  "results": [
    {
      "product_id": 100,
      "unit_id": 2,
      "qty_good": 10,
      "qty_waste": 0
    }
  ]
}
```

#### 4. Delete Production Order

`DELETE /{tenant}/manufacturing/production-order/delete-by-id?id={id}`

#### 5. Bulk Delete Production Order

`DELETE /{tenant}/manufacturing/production-order/delete-all`

Payload:

```json
{
  "ids": [1, 2]
}
```

## 3. Endpoint Setting COA Yang Perlu Diupdate di Frontend

Frontend form setting COA yang sudah ada perlu ditambah 1 field baru:

- `akunProduksiWip`

Endpoint save tetap sama:

`POST /{tenant}/save-coa-setting`

Contoh payload tambahan:

```json
{
  "user_id": 1,
  "akunSediaan": { "id": 101 },
  "akunPiutangUsaha": { "id": 102 },
  "akunProduksiWip": { "id": 103 }
}
```

Endpoint get tetap sama:

`GET /{tenant}/get-coa-setting`

Response sekarang akan punya field tambahan:

```json
{
  "status": true,
  "data": {
    "akunProduksiWip": {
      "id": 103,
      "coa_name": "Barang Dalam Proses"
    }
  }
}
```

## 4. Rekomendasi Layar Frontend

### A. Master BOM

Daftar kolom:
- Kode BOM
- Nama BOM
- Produk hasil
- Qty output
- Satuan output
- Use case
- Manufacturing mode
- Trigger auto-consume
- Versi
- Status

Form input:
- Produk hasil
- Nama BOM
- Kode BOM opsional
- Versi
- Qty output
- Satuan output
- Use case
- Manufacturing mode
- Auto consume trigger
- Status
- Catatan
- Grid bahan

Grid bahan:
- Produk bahan
- Satuan
- Qty per output
- Waste %
- Optional
- Note

### B. Preview BOM

Di halaman BOM dan Production Order, tampilkan tombol:
- `Preview Kebutuhan Bahan`

UI preview:
- Header BOM
- Qty output target
- Gudang
- Tanggal stok
- Table bahan:
  - nama bahan
  - qty kebutuhan
  - stok tersedia
  - kekurangan
  - HPP estimasi
  - estimasi biaya

Warnai `shortage_qty > 0` sebagai warning.

### C. Production Order

Daftar kolom:
- No produksi
- Tanggal
- Produk hasil
- Gudang
- Qty rencana
- Qty actual
- Status
- Sumber

Form:
- Tanggal
- Gudang
- BOM
- Produk hasil
- Qty rencana
- Qty aktual
- Akun WIP
- Catatan
- Preview bahan
- Detail bahan
- Detail hasil

Behavior frontend:
- Jika pilih `bom_id`, frontend panggil preview BOM
- Hasil preview bisa dipakai untuk mengisi grid bahan default
- User masih bisa review/edit sebelum save

### D. Setting COA

Tambahkan field:
- `Akun Produksi / WIP`

Keterangan UI:
- dipakai untuk jurnal produksi manual dan auto-consume

## 5. Flow UI Yang Disarankan

### Flow 1: Pre Produce

1. User buka Master BOM
2. User buat resep/BOM
3. User buka Production Order
4. User pilih BOM
5. Frontend panggil preview BOM
6. User review kebutuhan bahan
7. User simpan Production Order
8. Barang jadi masuk stok
9. Modul penjualan menjual barang jadi seperti biasa

### Flow 2: Auto Consume

1. User buat BOM
2. Set `manufacturing_mode = auto_consume` atau `both`
3. Set `auto_consume_trigger = invoice` atau `delivery`
4. Saat transaksi penjualan diposting, backend otomatis membuat production order internal
5. Frontend penjualan tidak wajib mengubah payload invoice/delivery

Catatan:
- Auto-consume bekerja di backend
- Jika frontend ingin preview sebelum submit invoice/POS, frontend bisa memanggil endpoint preview BOM berdasarkan produk yang dipilih

## 6. Aturan Penting Untuk Frontend

- Jangan asumsikan semua produk punya BOM
- Jangan asumsikan semua BOM memakai auto-consume
- Produk tanpa BOM tetap harus bisa dijual normal
- Jika mode `pre_produce`, frontend penjualan tidak perlu perlakuan khusus
- Jika mode `auto_consume`, frontend tetap bisa kirim transaksi penjualan seperti biasa
- Gunakan endpoint preview hanya untuk simulasi, bukan transaksi final

## 7. Validasi Frontend Yang Disarankan

Saat save BOM:
- `product_id` wajib
- `output_unit_id` wajib
- `output_qty > 0`
- minimal 1 bahan
- setiap bahan harus punya `product_id`, `unit_id`, `qty`

Saat save production order:
- `production_date` wajib
- `warehouse_id` wajib
- `product_id` wajib
- `output_unit_id` wajib
- `planned_qty > 0`
- jika tanpa `bom_id`, bahan manual wajib ada

Saat preview:
- `bom_id` wajib
- `output_qty > 0`

## 8. Catatan Backward Compatibility

Fitur ini ditambahkan secara additive:
- endpoint lama tidak berubah
- payload penjualan lama tetap valid
- produk lama tanpa BOM tetap aman
- setting lama tetap aman meskipun belum punya `akunProduksiWip`

Fallback backend:
- jika `akunProduksiWip` belum diset, backend fallback ke `coa_id` produk hasil supaya tidak langsung error

## 9. Saran Implementasi Frontend Bertahap

Tahap 1:
- Master BOM
- Preview BOM
- Setting akun WIP

Tahap 2:
- Production Order manual

Tahap 3:
- Badge/indikator pada produk yang punya BOM auto-consume
- Preview auto-consume di POS/invoice

## 10. Ringkasan Singkat Untuk Tim Frontend

Yang perlu ditambahkan:
- menu Master BOM
- menu Production Order
- field `Akun Produksi / WIP` di setting COA
- tombol preview kebutuhan bahan

Endpoint baru yang wajib diketahui:
- `GET /manufacturing/bom/get-all`
- `POST /manufacturing/bom/save-data`
- `GET /manufacturing/bom/find-by-id`
- `GET /manufacturing/bom/preview`
- `DELETE /manufacturing/bom/delete-by-id`
- `DELETE /manufacturing/bom/delete-all`
- `GET /manufacturing/production-order/get-all`
- `POST /manufacturing/production-order/save-data`
- `GET /manufacturing/production-order/find-by-id`
- `DELETE /manufacturing/production-order/delete-by-id`
- `DELETE /manufacturing/production-order/delete-all`
- `POST /save-coa-setting`
- `GET /get-coa-setting`

