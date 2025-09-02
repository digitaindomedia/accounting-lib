<?php

namespace Icso\Accounting\Utils;


use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseRetur;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Retur\SalesRetur;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\StockUsage;

class TransactionsCode
{
    const SALDO_AWAL = "SALDO_AWAL";
    const JURNAL = "JURNAL";
    const UANG_MUKA_PEMBELIAN = "UANG_MUKA_PEMBELIAN";
    const UANG_MUKA_PENJUALAN = "UANG_MUKA_PENJUALAN";
    const PENERIMAAN = "PENERIMAAN_PEMBELIAN";
    const INVOICE_PEMBELIAN = "INVOICE_PEMBELIAN";
    const INVOICE_PENJUALAN = "INVOICE_PENJUALAN";
    const PELUNASAN_PEMBELIAN = "PELUNASAN_PEMBELIAN";
    const PELUNASAN_PENJUALAN = "PELUNASAN_PENJUALAN";
    const RETUR_PEMBELIAN = "RETUR_PEMBELIAN";
    const DELIVERY_ORDER = "DELIVERY_ORDER";
    const RETUR_PENJUALAN = "RETUR_PENJUALAN";
    const ADJUSTMENT = "ADJUSTMENT";
    const MUTATION = "MUTATION";
    const PEMAKAIAN_STOCK = "PEMAKAIAN_STOCK";
    const UANG_MUKA_PEMBELIAN_ASET_TETAP = "UANG_MUKA_PEMBELIAN_ASET_TETAP";
    const DEPRESIASI_ASET_TETAP = "DEPRESIASI_ASET_TETAP";
    const PENERIMAAN_ASET_TETAP = "PENERIMAAN_ASET_TETAP";

    const INVOICE_PEMBELIAN_ASET_TETAP = "INVOICE_PEMBELIAN_ASET_TETAP";
    const PELUNASAN_PEMBELIAN_ASET_TETAP = "PELUNASAN_PEMBELIAN_ASET_TETAP";
    const PENJUALAN_ASET_TETAP = "PENJUALAN_ASET_TETAP";
    const PELUNASAN_PENJUALAN_ASET_TETAP = "PELUNASAN_PENJUALAN_ASET_TETAP";

    public static function getNumberAndNameTransaction($transactionCode, $transactionId)
    {
        $transactionName = '';
        $transactionNo = '';
        if($transactionCode == self::PENERIMAAN){
            $findPenerimaan = PurchaseReceived::where(array('id' => $transactionId))->first();
            if(!empty($findPenerimaan)){
                $transactionNo = $findPenerimaan->receive_no;
            }
            $transactionName = "PENERIMAAN PEMBELIAN";
        }
        else if($transactionCode == self::INVOICE_PEMBELIAN){
            $findInvoice = PurchaseInvoicing::where(array('id' => $transactionId))->first();
            if(!empty($findInvoice)){
                $transactionNo = $findInvoice->invoice_no;
            }
            $transactionName = "INVOICE PEMBELIAN";
        }
        else if($transactionCode == self::RETUR_PEMBELIAN){
            $findRetur = PurchaseRetur::where(array('id' => $transactionId))->first();
            if(!empty($findRetur)){
                $transactionNo = $findRetur->retur_no;
            }
            $transactionName = "RETUR PEMBELIAN";
        }
        else if($transactionCode == self::DELIVERY_ORDER){
            $findDelivery = SalesDelivery::where(array('id' => $transactionId))->first();
            if(!empty($findDelivery)){
                $transactionNo = $findDelivery->delivery_no;
            }
            $transactionName = "PENGIRIMAN PENJUALAN";
        }
        else if($transactionCode == self::INVOICE_PENJUALAN){
            $findInvoice = SalesInvoicing::where(array('id' => $transactionId))->first();
            if(!empty($findInvoice)){
                $transactionNo = $findInvoice->invoice_no;
            }
            $transactionName = "INVOICE PENJUALAN";
        }
        else if($transactionCode == self::RETUR_PENJUALAN){
            $findRetur = SalesRetur::where(array('id' => $transactionId))->first();
            if(!empty($findRetur)){
                $transactionNo = $findRetur->retur_no;
            }
            $transactionName = "RETUR PENJUALAN";
        }
        else if($transactionCode == self::ADJUSTMENT){
            $findAdjustment = Adjustment::where(array('id' => $transactionId))->first();
            if(!empty($findAdjustment)){
                $transactionNo = $findAdjustment->ref_no;
            }
            $transactionName = "PENYESUAIAN STOK";
        }
        else if($transactionCode == self::PEMAKAIAN_STOCK){
            $findPemakaian = StockUsage::where(array('id' => $transactionId))->first();
            if(!empty($findPemakaian)){
                $transactionNo = $findPemakaian->ref_no;
            }
            $transactionName = "PEMAKAIAN STOK";
        }
        return array(
            'transaction_name' => $transactionName,
            'transaction_no' => $transactionNo,
        );
    }
}
