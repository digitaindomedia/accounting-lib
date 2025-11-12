<?php

use Icso\Accounting\Http\Controllers\Akuntansi\BukuBesarController;
use Icso\Accounting\Http\Controllers\Akuntansi\BukuPembantuController;
use Icso\Accounting\Http\Controllers\Akuntansi\JurnalController;
use Icso\Accounting\Http\Controllers\Akuntansi\LabaRugiController;
use Icso\Accounting\Http\Controllers\Akuntansi\NeracaController;
use Icso\Accounting\Http\Controllers\Akuntansi\SaldoAwalController;
use Icso\Accounting\Http\Controllers\Master\CategoryController;
use Icso\Accounting\Http\Controllers\Master\CoaController;
use Icso\Accounting\Http\Controllers\Master\CountryController;
use Icso\Accounting\Http\Controllers\Master\PaymentMethodController;
use Icso\Accounting\Http\Controllers\Master\ProductController;
use Icso\Accounting\Http\Controllers\Master\TaxController;
use Icso\Accounting\Http\Controllers\Master\UnitController;
use Icso\Accounting\Http\Controllers\Master\VendorController;
use Icso\Accounting\Http\Controllers\Master\WarehouseController;
use Icso\Accounting\Http\Controllers\Pembelian\BastController;
use Icso\Accounting\Http\Controllers\Pembelian\DpController;
use Icso\Accounting\Http\Controllers\Pembelian\InvoiceController;
use Icso\Accounting\Http\Controllers\Pembelian\OrderController;
use Icso\Accounting\Http\Controllers\Pembelian\PaymentController;
use Icso\Accounting\Http\Controllers\Pembelian\ReceiveController;
use Icso\Accounting\Http\Controllers\Pembelian\RequestController;
use Icso\Accounting\Http\Controllers\Pembelian\ReturController;
use Icso\Accounting\Http\Controllers\Persediaan\AdjustmentController;
use Icso\Accounting\Http\Controllers\Persediaan\InventoryController;
use Icso\Accounting\Http\Controllers\Persediaan\MutationController;
use Icso\Accounting\Http\Controllers\Persediaan\PemakaianController;
use Icso\Accounting\Http\Controllers\Setting;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::group([
    'prefix'     => '/{tenant}',
    'middleware' => [InitializeTenancyByPath::class],
], function () {
    Route::get('system-setting',[Setting::class, 'getSystemSetting']);
    Route::post('save-system-setting',[Setting::class, 'storeData']);
    Route::post('save-key-value-setting',[Setting::class, 'storeKeyValue']);
    Route::post('save-coa-setting',[Setting::class, 'storeAkunCoa']);
    Route::get('get-setting-by-key',[Setting::class, 'getKeyValue']);
    Route::get('get-coa-setting',[Setting::class, 'getSettingCoa']);
    Route::get('get-content',[Setting::class, 'getContent']);
    Route::get('get-all-content',[Setting::class, 'getAllContents']);
    Route::post('save-content',[Setting::class, 'storeDashboard']);
    //category route
    Route::get('category-get-all',[CategoryController::class, 'getAllData']);
    Route::post('category-save-data',[CategoryController::class, 'store']);
    Route::get('category-find-by-id',[CategoryController::class, 'show']);
    Route::delete('category-delete-by-id',[CategoryController::class, 'destroy']);
    Route::delete('category-delete-all',[CategoryController::class, 'deleteAll']);
    Route::get('category-export-pdf', [CategoryController::class, 'exportPdf']);
    Route::get('category-export-excel', [CategoryController::class, 'export']);
    Route::get('category-export-csv', [CategoryController::class, 'exportCsv']);

    //tax route
    Route::get('tax-get-all',[TaxController::class, 'getAllData']);
    Route::post('tax-save-data',[TaxController::class, 'store']);
    Route::get('tax-find-by-id',[TaxController::class, 'show']);
    Route::delete('tax-delete-by-id',[TaxController::class, 'destroy']);
    Route::delete('tax-delete-all',[TaxController::class, 'deleteAll']);
    Route::get('tax-export-pdf', [TaxController::class, 'exportPdf']);
    Route::get('tax-export-excel', [TaxController::class, 'export']);
    Route::get('tax-export-csv', [TaxController::class, 'exportCsv']);

    //unit route
    Route::get('unit-get-all',[UnitController::class, 'getAllData']);
    Route::post('unit-save-data',[UnitController::class, 'store']);
    Route::get('unit-find-by-id',[UnitController::class, 'show']);
    Route::delete('unit-delete-by-id',[UnitController::class, 'destroy']);
    Route::delete('unit-delete-all',[UnitController::class, 'deleteAll']);
    Route::get('unit-export-pdf', [UnitController::class, 'exportPdf']);
    Route::get('unit-export-excel', [UnitController::class, 'export']);
    Route::get('unit-export-csv', [UnitController::class, 'exportCsv']);

    //warehouse route
    Route::get('warehouse-get-all',[WarehouseController::class, 'getAllData']);
    Route::post('warehouse-save-data',[WarehouseController::class, 'store']);
    Route::get('warehouse-find-by-id',[WarehouseController::class, 'show']);
    Route::delete('warehouse-delete-by-id',[WarehouseController::class, 'destroy']);
    Route::delete('warehouse-delete-all',[WarehouseController::class, 'deleteAll']);
    Route::get('warehouse-export-pdf', [WarehouseController::class, 'exportPdf']);
    Route::get('warehouse-export-excel', [WarehouseController::class, 'exportExcel']);
    Route::get('warehouse-export-csv', [WarehouseController::class, 'exportCsv']);

    //vendor route
    Route::get('vendor-get-all',[VendorController::class, 'getAllData']);
    Route::post('vendor-save-data',[VendorController::class, 'store']);
    Route::get('vendor-find-by-id',[VendorController::class, 'show']);
    Route::get('vendor-count',[VendorController::class, 'getCountVendor']);
    Route::delete('vendor-delete-by-id',[VendorController::class, 'destroy']);
    Route::delete('vendor-delete-all',[VendorController::class, 'deleteAll']);
    Route::get('download-sample-vendor', [VendorController::class, 'downloadSample']);
    Route::post('import-vendor', [VendorController::class, 'import']);
    Route::get('vendor-export-pdf', [VendorController::class, 'exportPdf']);
    Route::get('vendor-export-excel', [VendorController::class, 'export']);
    Route::get('vendor-export-csv', [VendorController::class, 'exportCsv']);

    //coa route
    Route::get('coa-get-all',[CoaController::class, 'getAllData']);
    Route::get('coa-get-all-data',[CoaController::class, 'getAllMasterCoa']);
    Route::get('coa-get-all-by-category',[CoaController::class, 'getAllCategoryData']);
    Route::get('coa-get-all-by-level',[CoaController::class, 'getAllLevelData']);
    Route::post('coa-save-data',[CoaController::class, 'store']);
    Route::post('coa-update-data',[CoaController::class, 'update']);
    Route::get('coa-find-by-id',[CoaController::class, 'show']);
    Route::get('coa-count',[CoaController::class, 'getCoaCount']);
    Route::delete('coa-delete-by-id',[CoaController::class, 'destroy']);
    Route::get('coa-saldo',[BukuBesarController::class, 'getTotalAkun']);
    Route::get('coa-export-excel', [CoaController::class, 'exportExcel']);
    Route::get('coa-export-csv', [CoaController::class, 'exportCsv']);
    Route::get('coa-export-pdf', [CoaController::class, 'exportPdf']);

    //product route
    Route::get('product-get-all',[ProductController::class, 'getAllData']);
    Route::post('product-save-data',[ProductController::class, 'store']);
    Route::get('product-find-by-id',[ProductController::class, 'show']);
    Route::delete('product-delete-by-id',[ProductController::class, 'destroy']);
    Route::delete('product-delete-all',[ProductController::class, 'deleteAll']);
    Route::get('product-convertion-get-by-product',[ProductController::class, 'getAllProductConvertion']);
    Route::post('product-convertion-save-data',[ProductController::class, 'storeProductConvertion']);
    Route::get('product-count',[ProductController::class, 'getCountProduct']);
    Route::get('download-sample-products', [ProductController::class, 'downloadSample']);
    Route::post('import-products', [ProductController::class, 'import']);
    Route::get('download-product-image', [ProductController::class, 'downloadimage']);
    Route::get('product-export-pdf', [ProductController::class, 'exportPdf']);
    Route::get('product-export-excel', [ProductController::class, 'export']);
    Route::get('product-export-csv', [ProductController::class, 'exportCsv']);



    //payment route
    Route::get('payment-get-all',[PaymentMethodController::class, 'getAllData']);
    Route::post('payment-save-data',[PaymentMethodController::class, 'store']);
    Route::get('payment-find-by-id',[PaymentMethodController::class, 'show']);
    Route::delete('payment-delete-by-id',[PaymentMethodController::class, 'destroy']);
    Route::delete('payment-delete-all',[PaymentMethodController::class, 'deleteAll']);
    Route::get('payment-export-pdf', [PaymentMethodController::class, 'exportPdf']);
    Route::get('payment-export-excel', [PaymentMethodController::class, 'export']);
    Route::get('payment-export-csv', [PaymentMethodController::class, 'exportCsv']);

    //country route
    Route::get('country-get-all',[CountryController::class, 'getAllData']);
    Route::get('country-find-by-id',[CountryController::class, 'show']);

    //saldo awal akuntansi
    Route::post('save-saldo-awal',[SaldoAwalController::class, 'store']);
    Route::post('save-saldo-awal-akun',[SaldoAwalController::class, 'storeSaldoAkun']);
    Route::get('find-saldo-awal',[SaldoAwalController::class, 'findDefaultSaldoAwal']);
    Route::get('find-saldo-awal-by-coa',[SaldoAwalController::class, 'findCoa']);
    Route::get('find-all-saldo-awal-coa',[SaldoAwalController::class, 'findAllCoa']);
    Route::post('save-jurnal',[JurnalController::class, 'store']);
    Route::get('jurnal-get-all',[JurnalController::class, 'getAllData']);
    Route::get('get-jurnal-by-id',[JurnalController::class, 'findJurnalById']);
    Route::delete('delete-jurnal',[JurnalController::class, 'deleteById']);
    Route::delete('delete-all-jurnal',[JurnalController::class, 'deleteAllJurnal']);
    Route::get('show-jurnal',[JurnalController::class, 'showAccountJurnal']);
    Route::get('download-sample-jurnal-umum', [JurnalController::class, 'downloadSample']);
    Route::get('download-sample-jurnal-kas-bank', [JurnalController::class, 'downloadKasBankSample']);
    Route::post('import-jurnal-umum', [JurnalController::class, 'import']);
    Route::post('import-jurnal-kas-bank', [JurnalController::class, 'importKasBank']);
    Route::get('export-excel-jurnal', [JurnalController::class, 'export']);
    Route::get('export-pdf-jurnal', [JurnalController::class, 'exportPdf']);
    Route::get('export-excel-saldo-awal', [SaldoAwalController::class, 'exportExcel']);
    Route::get('export-pdf-saldo-awal', [SaldoAwalController::class, 'exportPdf']);

    //persediaan
    Route::prefix('stock')->group(function () {
        Route::get('get-list-awal', [InventoryController::class, 'getAllStockAwal']);
        Route::get('get-stock-by-product-id', [InventoryController::class, 'getStockByProductId']);
        Route::get('get-hpp', [InventoryController::class, 'getStockHppByDate']);
        Route::post('save-awal', [InventoryController::class, 'storeAwal']);
        Route::post('update-awal', [InventoryController::class, 'updateAwal']);
        Route::delete('delete-awal', [InventoryController::class, 'deleteSaldoAwal']);
        Route::get('kartu-stok', [InventoryController::class, 'kartuStok']);
        Route::get('kartu-stok-detail', [InventoryController::class, 'showKartuStockDetail']);
    });

    Route::prefix('purchase-invoice')->group(function () {
        Route::get('get-all',[InvoiceController::class, 'getAllData']);
        Route::get('get-all-faktur-pajak',[InvoiceController::class, 'getAllFakturPajak']);
        Route::get('get-by-id',[InvoiceController::class, 'show']);
        Route::get('completion',[InvoiceController::class, 'completion']);
        Route::post('save-saldo-awal',[InvoiceController::class, 'storeSaldoAwal']);
        Route::post('save-data',[InvoiceController::class, 'store']);
        Route::post('save-data-faktur-pajak',[InvoiceController::class, 'saveFakturPajak']);
        Route::get('report-kartu-hutang',[InvoiceController::class, 'kartuHutang']);
        Route::get('report-kartu-hutang-detail',[InvoiceController::class, 'showKartuHutangDetail']);
        Route::delete('delete',[InvoiceController::class, 'destroy']);
        Route::delete('delete-all',[InvoiceController::class, 'deleteAll']);
        Route::get('total-saldo',[InvoiceController::class, 'getTotalInvoice']);
        Route::get('download-sample', [InvoiceController::class, 'downloadSample']);
        Route::post('import', [InvoiceController::class, 'import']);
        Route::get('export-excel', [InvoiceController::class, 'export']);
        Route::get('export-csv', [InvoiceController::class, 'exportCsv']);
        Route::get('export-pdf', [InvoiceController::class, 'exportPdf']);
        Route::get('export-excel-report', [InvoiceController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [InvoiceController::class, 'exportReportPdf']);
    });

    Route::prefix('buku-pembantu')->group(function () {
        Route::get('get-all-list', [BukuPembantuController::class, 'getAllData']);
        Route::post('store-saldo-awal', [BukuPembantuController::class, 'storeSaldoAwal']);
        Route::post('update-saldo-awal', [BukuPembantuController::class, 'updateSaldoAwal']);
    });

    Route::prefix('report')->group(function () {
        Route::get('buku-besar', [BukuBesarController::class, 'show']);
        Route::get('export-excel-buku-besar', [BukuBesarController::class, 'export']);
        Route::get('export-excel-jurnal-transaksi', [BukuBesarController::class, 'exportExcelTransaksi']);
        Route::get('export-pdf-jurnal-transaksi', [BukuBesarController::class, 'exportPdfTransaksi']);
        Route::get('export-pdf-buku-besar', [BukuBesarController::class, 'exportToPdf']);
        Route::get('neraca', [NeracaController::class, 'show']);
        Route::get('export-neraca', [NeracaController::class, 'export']);
        Route::get('export-neraca-pdf', [NeracaController::class, 'exportToPdf']);
        Route::get('laba-rugi', [LabaRugiController::class, 'show']);
        Route::get('export-excel-laba-rugi', [LabaRugiController::class, 'export']);
        Route::get('export-pdf-laba-rugi', [LabaRugiController::class, 'exportToPdf']);
        Route::get('jurnal-transaksi', [BukuBesarController::class, 'showAll']);
    });

    Route::prefix('purchase-request')->group(function () {
        Route::get('get-all-list', [RequestController::class, 'getAllData']);
        Route::get('find-by-id', [RequestController::class, 'show']);
        Route::post('save-data', [RequestController::class, 'store']);
        Route::delete('delete', [RequestController::class, 'delete']);
        Route::delete('delete-all', [RequestController::class, 'deleteAll']);
        Route::get('completion', [RequestController::class, 'completion']);
        Route::get('download-sample', [RequestController::class, 'downloadSample']);
        Route::post('import', [RequestController::class, 'import']);
        Route::get('export-excel', [RequestController::class, 'export']);
        Route::get('export-csv', [RequestController::class, 'exportCsv']);
        Route::get('export-pdf', [RequestController::class, 'exportPdf']);
        Route::get('export-excel-report', [RequestController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [RequestController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-order')->group(function () {
        Route::get('get-all-list', [OrderController::class, 'getAllData']);
        Route::get('find-by-id', [OrderController::class, 'show']);
        Route::get('find-received-by-id', [OrderController::class, 'showNotReceived']);
        Route::post('save-data', [OrderController::class, 'store']);
        Route::delete('delete', [OrderController::class, 'delete']);
        Route::delete('delete-all', [OrderController::class, 'deleteAll']);
        Route::get('completion', [OrderController::class, 'completion']);
        Route::get('download-sample', [OrderController::class, 'downloadSample']);
        Route::post('import', [OrderController::class, 'import']);
        Route::get('export-excel', [OrderController::class, 'export']);
        Route::get('export-csv', [OrderController::class, 'exportCsv']);
        Route::get('export-pdf', [OrderController::class, 'exportPdf']);
        Route::get('export-excel-report', [OrderController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [OrderController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-received')->group(function () {
        Route::get('get-all-list', [ReceiveController::class, 'getAllData']);
        Route::get('find-by-id', [ReceiveController::class, 'show']);
        Route::get('completion', [ReceiveController::class, 'completion']);
        Route::post('save-data', [ReceiveController::class, 'store']);
        Route::delete('delete', [ReceiveController::class, 'destroy']);
        Route::delete('delete-all', [ReceiveController::class, 'deleteAll']);
        Route::get('export-excel', [ReceiveController::class, 'export']);
        Route::get('export-csv', [ReceiveController::class, 'exportCsv']);
        Route::get('export-pdf', [ReceiveController::class, 'exportPdf']);
        Route::get('export-excel-report', [ReceiveController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [ReceiveController::class, 'exportReportPdf']);;
    });

    Route::prefix('down-payment')->group(function () {
        Route::get('get-all-list', [DpController::class, 'getAllData']);
        Route::get('find-by-id', [DpController::class, 'show']);
        Route::post('save-data', [DpController::class, 'store']);
        Route::delete('delete', [DpController::class, 'delete']);
        Route::delete('delete-all', [DpController::class, 'deleteAll']);
        Route::get('export-excel', [DpController::class, 'export']);
        Route::get('export-csv', [DpController::class, 'exportCsv']);
        Route::get('export-pdf', [DpController::class, 'exportPdf']);
        Route::get('export-excel-report', [DpController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [DpController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-payment')->group(function () {
        Route::get('get-all-list', [PaymentController::class, 'getAllData']);
        Route::get('find-by-id', [PaymentController::class, 'show']);
        Route::post('save-data', [PaymentController::class, 'store']);
        Route::delete('delete', [PaymentController::class, 'destroy']);
        Route::delete('delete-all', [PaymentController::class, 'deleteAll']);
        Route::get('export-excel', [PaymentController::class, 'export']);
        Route::get('export-excel-report', [PaymentController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [PaymentController::class, 'exportReportPdf']);
        Route::get('export-csv', [PaymentController::class, 'exportCsv']);
        Route::get('export-pdf', [PaymentController::class, 'exportPdf']);
    });

    Route::prefix('purchase-retur')->group(function () {
        Route::get('get-all-list',[ReturController::class, 'getAllData']);
        Route::get('find-by-id',[ReturController::class, 'show']);
        Route::post('save-data',[ReturController::class, 'store']);
        Route::delete('delete', [ReturController::class, 'destroy']);
        Route::delete('delete-all', [ReturController::class, 'deleteAll']);
        Route::get('export-excel', [ReturController::class, 'export']);
        Route::get('export-report-excel', [ReturController::class, 'exportReportExcel']);
        Route::get('export-report-pdf', [ReturController::class, 'exportReportPdf']);
        Route::get('export-csv', [ReturController::class, 'exportCsv']);
        Route::get('export-pdf', [ReturController::class, 'exportPdf']);
    });

    Route::prefix('purchase-bast')->group(function () {
        Route::get('get-all-list', [BastController::class, 'getAllData']);
        Route::get('find-by-id', [BastController::class, 'show']);
        Route::post('save-data', [BastController::class, 'store']);
        Route::delete('delete', [BastController::class, 'destroy']);
        Route::delete('delete-all', [BastController::class, 'deleteAll']);
        Route::get('export-excel', [BastController::class, 'export']);
        Route::get('export-csv', [BastController::class, 'exportCsv']);
        Route::get('export-pdf', [BastController::class, 'exportPdf']);
        Route::get('export-excel-report', [BastController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [BastController::class, 'exportReportPdf']);
        Route::get('export-csv-report', [BastController::class, 'exportReportCsv']);
    });

    Route::prefix('sales-order')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'show']);
        Route::get('completion', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'completion']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'delete']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'deleteAll']);
        Route::get('download-sample', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'downloadSample']);
        Route::post('import', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'import']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'exportCsv']);
        Route::get('export-pdf', [Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'exportReportExcel']);
        Route::get('export-csv-report', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'exportReportCsv']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\Penjualan\OrderController::class, 'exportReportPdf']);
    });

    Route::prefix('sales-down-payment')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'exportPdf']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'exportReportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\Penjualan\DpController::class, 'exportReportExcel']);
    });

    Route::prefix('delivery-order')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'show']);
        Route::get('completion', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'completion']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'exportPdf']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'exportReportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\Penjualan\DeliveryController::class, 'exportReportExcel']);
    });

    Route::prefix('sales-invoice')->group(function () {
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'store']);
        Route::get('invoice-get-all',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'getAllData']);
        Route::get('total-saldo',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'getTotalInvoice']);
        Route::get('completion',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'completion']);
        Route::post('save-invoice-saldo-awal',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'storeSaldoAwal']);
        Route::post('update-invoice-saldo-awal',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'updateSaldoAwal']);
        Route::delete('delete-invoice-by-id',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'deleteById']);
        Route::get('report-kartu-piutang',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'kartuPiutang']);
        Route::get('report-kartu-piutang-detail',[\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'showKartuPiutangDetail']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'deleteAll']);
        Route::get('download-sample', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'downloadSample']);
        Route::post('import', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'import']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'exportPdf']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'exportReportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\Penjualan\InvoiceController::class, 'exportReportExcel']);
    });

    Route::prefix('sales-payment')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\Penjualan\PaymentController::class, 'exportPdf']);
    });

    Route::prefix('sales-spk')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\Penjualan\SpkController::class, 'exportPdf']);
    });

    Route::prefix('sales-retur')->group(function () {
        Route::get('get-all-list',[\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'getAllData']);
        Route::get('find-by-id',[\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'show']);
        Route::post('save-data',[\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\Penjualan\ReturController::class, 'exportReportPdf']);
    });

    Route::prefix('adjustment-stock')->group(function () {
        Route::get('get-all-list',[AdjustmentController::class, 'getAllData']);
        Route::get('find-by-id',[AdjustmentController::class, 'show']);
        Route::post('save-data',[AdjustmentController::class, 'store']);
        Route::delete('delete', [AdjustmentController::class, 'destroy']);
        Route::delete('delete-all', [AdjustmentController::class, 'deleteAll']);
        Route::get('download-sample', [AdjustmentController::class, 'downloadSample']);
        Route::post('import', [AdjustmentController::class, 'import']);
        Route::get('export-excel', [AdjustmentController::class, 'export']);
        Route::get('export-csv', [AdjustmentController::class, 'exportCsv']);
        Route::get('export-pdf', [AdjustmentController::class, 'exportPdf']);
        Route::get('export-excel-report', [AdjustmentController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [AdjustmentController::class, 'exportReportPdf']);
    });

    Route::prefix('usage-stock')->group(function () {
        Route::get('get-all-list',[PemakaianController::class, 'getAllData']);
        Route::get('find-by-id',[PemakaianController::class, 'show']);
        Route::post('save-data',[PemakaianController::class, 'store']);
        Route::delete('delete', [PemakaianController::class, 'destroy']);
        Route::delete('delete-all', [PemakaianController::class, 'deleteAll']);
        Route::get('download-sample', [PemakaianController::class, 'downloadSample']);
        Route::post('import', [PemakaianController::class, 'import']);
        Route::get('export-excel', [PemakaianController::class, 'export']);
        Route::get('export-csv', [PemakaianController::class, 'exportCsv']);
        Route::get('export-pdf', [PemakaianController::class, 'exportPdf']);
        Route::get('export-excel-report', [PemakaianController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [PemakaianController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-order-aset-tetap')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'delete']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseOrderController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-dp-aset-tetap')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'delete']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseDownPaymentController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-receive-aset-tetap')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseReceivedController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-invoice-aset-tetap')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'delete']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchaseInvoiceController::class, 'exportReportPdf']);
    });

    Route::prefix('purchase-payment-aset-tetap')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'destroy']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Pembelian\PurchasePaymentController::class, 'exportReportPdf']);
    });

    Route::prefix('sales-invoice-aset-tetap')->group(function () {
        Route::get('get-all-list', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'getAllData']);
        Route::get('find-by-id', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'show']);
        Route::post('save-data', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'store']);
        Route::delete('delete', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'delete']);
        Route::delete('delete-all', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'deleteAll']);
        Route::get('export-excel', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'export']);
        Route::get('export-csv', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'exportCsv']);
        Route::get('export-pdf', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'exportPdf']);
        Route::get('export-excel-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'exportReportExcel']);
        Route::get('export-pdf-report', [\Icso\Accounting\Http\Controllers\AsetTetap\Penjualan\SalesInvoiceController::class, 'exportReportPdf']);
    });

    Route::prefix('mutation-stock')->group(function () {
        Route::get('get-all-list',[MutationController::class, 'getAllData']);
        Route::get('find-by-id',[MutationController::class, 'show']);
        Route::post('save-data',[MutationController::class, 'store']);
        Route::delete('delete', [MutationController::class, 'destroy']);
        Route::delete('delete-all', [MutationController::class, 'deleteAll']);
    });
});
