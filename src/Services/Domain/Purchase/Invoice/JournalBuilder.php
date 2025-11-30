<?php

namespace Icso\Accounting\Services\Domain\Purchase\Invoice;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicingDp;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\TaxCalculator;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\VarType;

class JournalBuilder
{
    public function __construct(private InvoiceRepo $invoiceRepo) {}

    /**
     * Entry utama: generate jurnal invoice
     */
    public function buildInvoiceJournal($invoiceId): void
    {
        $invoice = $this->invoiceRepo->findOne($invoiceId, [], [
            'vendor', 'orderproduct.product', 'orderproduct.tax.taxgroup.tax',
            'invoicereceived.receive.receiveproduct.tax.taxgroup.tax'
        ]);

        $journalRows = [];

        // 1. Inventory / goods / service lines
        $journalRows = array_merge($journalRows, $this->buildLinesForProducts($invoice));

        // 2. Tax group lines
        $journalRows = array_merge($journalRows, $this->buildLinesForTax($invoice));

        // 3. Discount lines
        $journalRows = array_merge($journalRows, $this->buildLinesForDiscount($invoice));

        // 4. Downpayment reversal
        [$journalRows, $totalDpDpp, $totalDpPpn] = $this->buildLinesForDownpayment($invoice, $journalRows);

        // 5. Account Payable (Utang usaha)
        $journalRows = array_merge($journalRows, $this->buildLineForUtangUsaha($invoice, $journalRows, $totalDpDpp, $totalDpPpn));

        // Save to DB
        $this->persistJournalRows($journalRows);

        // assert balance
        if (!$this->isBalanced($invoiceId)) {
            throw new \Exception("Jurnal tidak balance untuk invoice $invoiceId");
        }
    }

    private function persistJournalRows(array $rows): void
    {
        foreach ($rows as $row) {

            $nominal = $row['debet'] > 0 ? $row['debet'] : $row['kredit'];

            JurnalTransaksi::create([
                'transaction_date'     => $row['transaction_date'],
                'transaction_datetime' => $row['transaction_datetime'],
                'transaction_code'     => $row['transaction_code'],
                'transaction_id'       => $row['transaction_id'],
                'transaction_sub_id'   => $row['transaction_sub_id'] ?? 0,
                'transaction_no'       => $row['transaction_no'],
                'transaction_status'   => $row['transaction_status'],

                // MAPPING FIELD SESUAI DB
                'coa_id'        => $row['coa_id'],
                'nominal'       => $nominal,
                'income'        => $row['debet'],
                'outcome'       => $row['kredit'],
                'transaction_type' => $row['debet'] > 0 ? 'income' : 'outcome',

                'note'      => $row['note'],
                'created_by'=> $row['created_by'] ?? auth()->id(),
                'updated_by'=> $row['updated_by'] ?? auth()->id(),
            ]);
        }
    }

    public function isBalanced($invoiceId): bool
    {
        $sum = JurnalTransaksi::where('transaction_code', TransactionsCode::INVOICE_PEMBELIAN)
            ->where('transaction_id', $invoiceId)
            ->selectRaw('SUM(debet) as total_debet, SUM(kredit) as total_kredit')
            ->first();

        return round($sum->total_debet, 2) === round($sum->total_kredit, 2);
    }

    private function buildLinesForProducts($invoice): array
    {
        $rows = [];

        foreach ($invoice->orderproduct as $item) {
            $rows[] = [
                'transaction_date' => $invoice->invoice_date,
                'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
                'created_by' => $invoice->created_by,
                'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                'transaction_id' => $invoice->id,
                'transaction_sub_id' => $item->id,
                'coa_id' => $item->product?->coa_id ?? $invoice->coa_id,
                'debet' => $item->subtotal,
                'kredit' => 0,
                'transaction_no' => $invoice->invoice_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'note' => "Pembelian item {$item->service_name}",
            ];
        }

        return $rows;
    }

    private function buildLinesForTax($invoice): array
    {
        $rows = [];

        foreach ($invoice->orderproduct as $item) {
            if (!$item->tax) continue;

            foreach ($item->tax->taxgroup as $group) {
                $taxObj = $group->tax;
                $calc = TaxCalculator::calcSingle(
                    $taxObj->tax_percentage,
                    $item->subtotal,
                    $item->tax_type,
                    $taxObj->is_dpp_nilai_Lain
                );

                $rows[] = [
                    'transaction_date' => $invoice->invoice_date,
                    'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
                    'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                    'transaction_id' => $invoice->id,
                    'transaction_sub_id' => $item->id,
                    'coa_id' => $taxObj->purchase_coa_id,
                    'debet'  => $taxObj->tax_sign == 'penambah' ? $calc['ppn'] : 0,
                    'kredit' => $taxObj->tax_sign == 'pemotong' ? $calc['ppn'] : 0,
                    'transaction_no' => $invoice->invoice_no,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'note' => "Pajak {$taxObj->tax_code} item {$item->service_name}",
                ];
            }
        }

        return $rows;
    }

    private function buildLinesForDiscount($invoice): array
    {
        $coaPotongan = SettingRepo::getOptionValue(SettingEnum::COA_POTONGAN_PEMBELIAN);
        if (!$invoice->discount_total) return [];

        return [[
            'transaction_date' => $invoice->invoice_date,
            'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
            'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
            'transaction_id' => $invoice->id,
            'coa_id' => $coaPotongan,
            'debet' => 0,
            'kredit' => $invoice->discount_total,
            'transaction_no' => $invoice->invoice_no,
            'transaction_status' => JurnalStatusEnum::OK,
            'note' => "Diskon pembelian",
        ]];
    }

    private function buildLinesForDownpayment($invoice, array $existingRows): array
    {
        $rows = [];

        $coaUtangUsaha   = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);
        $coaUangMuka     = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN);

        $totalDpNominal  = 0.0;   // total DP bruto (sesuai nominal uang muka)
        $totalDpDpp      = 0.0;   // total DPP DP (setelah pecah PPN)
        $totalDpPpnDebet = 0.0;   // total PPN yang posisinya debet (pajak masukan)
        $totalDpPpnKredit= 0.0;   // total PPN yang posisinya kredit (pajak pemotong)

        $dpList = PurchaseInvoicingDp::where('invoice_id', $invoice->id)
            ->with(['downpayment', 'downpayment.tax', 'downpayment.tax.taxgroup.tax'])
            ->get();

        if ($dpList->isEmpty()) {
            return [$rows, $totalDpDpp, $totalDpPpnDebet - $totalDpPpnKredit];
        }

        foreach ($dpList as $dpLink) {
            $dp = $dpLink->downpayment;
            if (!$dp) continue;

            $nominal = (float) $dp->nominal;
            $totalDpNominal += $nominal;

            // Default: anggap seluruh DP = DPP jika tidak ada tax / faktur belum accepted
            $dpp = $nominal;

            if ($dp->faktur_accepted == TypeEnum::FAKTUR_ACCEPTED && !empty($dp->tax_id) && $dp->tax) {
                $objTax = $dp->tax;

                if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                    // Pakai helper include tax (DP selalu treated sebagai include)
                    $calc = Helpers::hitungIncludeTax($objTax->tax_percentage, $nominal);
                    $ppn = $calc[TypeEnum::PPN];
                    $dpp = $calc[TypeEnum::DPP];

                    if ($objTax->tax_sign == VarType::TAX_SIGN_PENAMBAH) {
                        $totalDpPpnDebet += $ppn;
                    } else {
                        $totalDpPpnKredit += $ppn;
                    }
                } else {
                    // Multi-group: DP-1 tetap support, tapi di-aggregate juga
                    foreach ($objTax->taxgroup as $group) {
                        $taxRow = $group->tax;
                        if (!$taxRow) continue;

                        $calc = Helpers::hitungIncludeTax($taxRow->tax_percentage, $nominal);
                        $ppn  = $calc[TypeEnum::PPN];
                        $dpp  = $calc[TypeEnum::DPP]; // override, asumsi DP all taxable sama rate

                        if ($taxRow->tax_sign == VarType::TAX_SIGN_PENAMBAH) {
                            $totalDpPpnDebet += $ppn;
                        } else {
                            $totalDpPpnKredit += $ppn;
                        }
                    }
                }
            }

            $totalDpDpp += $dpp;
        }

        // 1) Dr Utang Usaha (total DP nominal)
        $rows[] = [
            'transaction_date'     => $invoice->invoice_date,
            'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
            'created_by'           => $invoice->created_by,
            'updated_by'           => $invoice->created_by,
            'transaction_code'     => TransactionsCode::INVOICE_PEMBELIAN,
            'coa_id'               => $coaUtangUsaha,
            'transaction_id'       => $invoice->id,
            'transaction_sub_id'   => 0,
            'created_at'           => now(),
            'updated_at'           => now(),
            'transaction_no'       => $invoice->invoice_no,
            'transaction_status'   => JurnalStatusEnum::OK,
            'debet'                => $totalDpNominal,
            'kredit'               => 0,
            'note'                 => $invoice->note ?: 'Pembalik uang muka pembelian (DP agregat)',
        ];

        // 2) Cr Uang Muka = total DPP
        if ($totalDpDpp > 0) {
            $rows[] = [
                'transaction_date'     => $invoice->invoice_date,
                'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
                'created_by'           => $invoice->created_by,
                'updated_by'           => $invoice->created_by,
                'transaction_code'     => TransactionsCode::INVOICE_PEMBELIAN,
                'coa_id'               => $coaUangMuka,
                'transaction_id'       => $invoice->id,
                'transaction_sub_id'   => 0,
                'created_at'           => now(),
                'updated_at'           => now(),
                'transaction_no'       => $invoice->invoice_no,
                'transaction_status'   => JurnalStatusEnum::OK,
                'debet'                => 0,
                'kredit'               => $totalDpDpp,
                'note'                 => $invoice->note ?: 'Pembalik uang muka pembelian (DPP agregat)',
            ];
        }

        // 3) Pajak dari DP (agregat)
        $netPpnDebet  = $totalDpPpnDebet;
        $netPpnKredit = $totalDpPpnKredit;

        $coaPpnMasukan = SettingRepo::getOptionValue(SettingEnum::COA_PPN_MASUKAN);

        if ($netPpnDebet > 0) {
            $rows[] = [
                'transaction_date'     => $invoice->invoice_date,
                'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
                'created_by'           => $invoice->created_by,
                'updated_by'           => $invoice->created_by,
                'transaction_code'     => TransactionsCode::INVOICE_PEMBELIAN,
                'coa_id'               => $coaPpnMasukan,
                'transaction_id'       => $invoice->id,
                'transaction_sub_id'   => 0,
                'created_at'           => now(),
                'updated_at'           => now(),
                'transaction_no'       => $invoice->invoice_no,
                'transaction_status'   => JurnalStatusEnum::OK,
                'debet'                => $netPpnDebet,
                'kredit'               => 0,
                'note'                 => $invoice->note ?: 'PPN DP pembelian (agregat)',
            ];
        }

        if ($netPpnKredit > 0) {
            $rows[] = [
                'transaction_date'     => $invoice->invoice_date,
                'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
                'created_by'           => $invoice->created_by,
                'updated_by'           => $invoice->created_by,
                'transaction_code'     => TransactionsCode::INVOICE_PEMBELIAN,
                'coa_id'               => $coaPpnMasukan, // atau COA pajak lain jika perlu
                'transaction_id'       => $invoice->id,
                'transaction_sub_id'   => 0,
                'created_at'           => now(),
                'updated_at'           => now(),
                'transaction_no'       => $invoice->invoice_no,
                'transaction_status'   => JurnalStatusEnum::OK,
                'debet'                => 0,
                'kredit'               => $netPpnKredit,
                'note'                 => $invoice->note ?: 'PPN DP pemotong (agregat)',
            ];
        }

        // Return rows + informasi DP untuk perhitungan AP line
        return [$rows, $totalDpDpp, $totalDpPpnDebet - $totalDpPpnKredit];
    }


    private function buildLineForUtangUsaha($invoice, array $allRows, float $totalDpDpp, float $netPpnDp): array
    {
        $coaUtangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);

        // Hitung total debet/kredit sementara dari rows yang sudah terkumpul
        $totalDebet  = 0.0;
        $totalKredit = 0.0;

        foreach ($allRows as $row) {
            $totalDebet  += (float) $row['debet'];
            $totalKredit += (float) $row['kredit'];
        }

        // Supaya jurnal benar-benar balance, kita hitung "AP balancing"
        // Debit - Kredit harus 0 setelah AP ini ditambahkan
        $balance = $totalDebet - $totalKredit; // jika +ve berarti butuh kredit

        if (abs($balance) < 0.01) {
            // Sudah balance, tidak perlu AP tambahan (harusnya jarang terjadi)
            return [];
        }

        return [[
            'transaction_date'     => $invoice->invoice_date,
            'transaction_datetime' => $invoice->invoice_date . ' ' . now()->format('H:i:s'),
            'created_by'           => $invoice->created_by,
            'updated_by'           => $invoice->created_by,
            'transaction_code'     => TransactionsCode::INVOICE_PEMBELIAN,
            'coa_id'               => $coaUtangUsaha,
            'transaction_id'       => $invoice->id,
            'transaction_sub_id'   => 0,
            'created_at'           => now(),
            'updated_at'           => now(),
            'transaction_no'       => $invoice->invoice_no,
            'transaction_status'   => JurnalStatusEnum::OK,
            'debet'                => $balance < 0 ? abs($balance) : 0,
            'kredit'               => $balance > 0 ? $balance : 0,
            'note'                 => $invoice->note ?: 'Penyesuaian utang usaha invoice',
        ]];
    }



}