<?php

namespace Icso\Accounting\Services\Domain\Purchase\Invoice;

use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicingMeta;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\InputType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class InvoiceService
{
    public function __construct(
        private InvoiceRepo $invoiceRepo,
        private InventoryRepo $inventoryRepo,
        private JournalBuilder $journalBuilder,
        private FileUploadService $fileUploadService,
    ) {}

    /**
     * Entry point baru: gunakan di controller dibanding panggil InvoiceRepo::store() langsung.
     */
    public function storeFromRequest(Request $request): bool
    {
        $userId = $request->user_id;
        $dto    = InvoiceDTO::fromRequest($request);

        return DB::transaction(function () use ($request, $dto, $userId) {

            // gunakan logic existing gather/save agar migrasi pelan2
            $data    = $this->invoiceRepo->gatherInputData($request);
            $invoice = $this->invoiceRepo->saveInvoice($data, $request->id, $userId);

            if (!$invoice) {
                return false;
            }

            $idInvoice = $request->id ?? $invoice->id;

            if (!empty($request->id)) {
                $this->invoiceRepo->deleteAdditional($request->id);
            }

            // detail, DP, receive, inventory masih pakai method existing
            $this->invoiceRepo->handleOrderProducts(
                $request->orderproduct,
                $idInvoice,
                $data['tax_type'],
                $data['invoice_date'],
                $data['note'],
                $userId,
                $data['warehouse_id'],
                $request->input_type,
                $this->inventoryRepo
            );

            $this->invoiceRepo->handleDownPayments($request->dp, $idInvoice);
            $this->invoiceRepo->handleReceivedProducts($request->receive, $idInvoice);

            if ($data['input_type'] != InputType::JURNAL) {
                $this->invoiceRepo->postingJurnal($idInvoice);
            }

            $this->handleFileUploads($request->file('files'), $idInvoice, $userId);

            return true;
        });
    }

    private function handleFileUploads($uploadedFiles, string $invoiceId, string $userId)
    {
        if (!empty($uploadedFiles)) {
            if (count($uploadedFiles) > 0) {
                $fileUpload = new FileUploadService();
                foreach ($uploadedFiles as $file) {
                    $resUpload = $fileUpload->upload($file, tenant(), $userId);
                    if ($resUpload) {
                        PurchaseInvoicingMeta::create([
                            'invoice_id' => $invoiceId,
                            'meta_key' => 'upload',
                            'meta_value' => $resUpload
                        ]);
                    }
                }
            }
        }
    }
}