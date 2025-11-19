<?php
namespace Icso\Accounting\Http\Controllers\Akuntansi;


use Icso\Accounting\Exports\SaldoAwalExport;
use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Repositories\Akuntansi\BukuPembantuRepo;
use Icso\Accounting\Repositories\Akuntansi\SaldoAwal\SaldoAwalRepo;
use Icso\Accounting\Repositories\Akuntansi\SaldoAwalAkunRepo;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\Constants;
use Icso\Accounting\Utils\VarType;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SaldoAwalController extends Controller
{
    protected $saldoAwalRepo;
    protected $saldoAkunRepo;
    protected $coaRepo;

    public function __construct(SaldoAwalRepo $saldoAwalRepo, SaldoAwalAkunRepo $saldoAwalAkunRepo, CoaRepo $coaRepo)
    {
        $this->saldoAwalRepo = $saldoAwalRepo;
        $this->saldoAkunRepo = $saldoAwalAkunRepo;
        $this->coaRepo = $coaRepo;
    }

    public function findDefaultSaldoAwal()
    {
        $res = $this->saldoAwalRepo->findWhere(['is_default' => Constants::AKTIF], [], ['saldoakun', 'saldoakun.coa']);
        return $this->responseData($res, 'Data berhasil ditemukan', 'Data gagal ditemukan');
    }

    public function store(Request $request)
    {
        $res = $this->saldoAwalRepo->store($request);
        return $this->responseStatus($res, 'Data berhasil disimpan', 'Data gagal disimpan');
    }

    public function storeSaldoAkun(Request $request)
    {
        $res = $this->saldoAwalRepo->storeSaldoAkun($request);
        return $this->responseStatus($res, 'Data berhasil disimpan', 'Data gagal disimpan');
    }

    public function findCoa(Request $request)
    {
        $res = $this->saldoAkunRepo->findWhere(['coa_id' => $request->coa_id]);
        return $this->responseData($res, 'Data berhasil ditemukan', 'Data gagal ditemukan');
    }

    public function findAllCoa()
    {
        $res = $this->coaRepo->findAllByWhere(['neraca' => '1', 'coa_level' => '4'], ['coa_code', 'asc']);
        if (count($res) > 0) {
            foreach ($res as $item) {
                $this->setSaldoAkun($item);
            }
            $find_saldo = SaldoAwal::first();
            $this->data['saldo'] = $find_saldo;
            return $this->responseData($res, 'Data berhasil ditemukan', 'Data gagal ditemukan');
        }

        return $this->responseData([], 'Data berhasil ditemukan', 'Data gagal ditemukan');
    }

    public function exportExcel()
    {
        $data = $this->prepareData();
        return Excel::download(new SaldoAwalExport($data), 'saldo-awal.xlsx');
    }

    public function exportPdf()
    {
        $data = $this->prepareData();
        $pdf = PDF::loadView('accounting::saldo_awal_pdf', ['data' => $data]);
        return $pdf->download('saldo-awal.pdf');
    }

    private function prepareData()
    {
        $data = [];
        $res = $this->coaRepo->findAllByWhere(['neraca' => '1', 'coa_level' => '4'], ['coa_code', 'asc']);
        foreach ($res as $item) {
            $debet = $kredit = 0;
            $this->setSaldoAkun($item);
            $data[] = [
                'coa_name' => $item->coa_name . " " . $item->coa_code,
                'debet' => $item->saldo_akun['debet'],
                'kredit' => $item->saldo_akun['kredit'],
            ];
        }
        return $data;
    }

    private function setSaldoAkun(&$item)
    {
        $findSaldoAwalAkun = $this->saldoAkunRepo->findWhere(['coa_id' => $item->id]);
        $debet = $kredit = 0;
        $canEdit = $this->setCanEditAndValues($item, $debet, $kredit);

        if (!empty($findSaldoAwalAkun)) {
            $debet = empty($debet) ? $findSaldoAwalAkun->debet : $debet;
            $kredit = empty($kredit) ? $findSaldoAwalAkun->kredit : $kredit;
        }

        $item->saldo_akun = [
            'id' => $findSaldoAwalAkun->id ?? '',
            'saldo_id' => $findSaldoAwalAkun->saldo_id ?? '',
            'coa_id' => $item->id,
            'debet' => $debet,
            'kredit' => $kredit,
            'can_edit' => $canEdit,
        ];
    }

    private function setCanEditAndValues($item, &$debet, &$kredit)
    {
        $canEdit = false;
        if ($item->connect_db != 0) {
            $canEdit = true;
            switch ($item->connect_db) {
                case VarType::CONNECT_DB_SUPPLIER:
                    $kredit = InvoiceRepo::getTotalInvoiceBySaldoAwalCoaId($item->id);
                    break;
                case VarType::CONNECT_DB_CUSTOMER:
                    $debet = \Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo::getTotalInvoiceBySaldoAwalCoaId($item->id);
                    break;
                case VarType::CONNECT_DB_PERSEDIAAN:
                    $totalStock = InventoryRepo::getTotalStockBySaldoAwalCoaId($item->id);
                    if ($item->coa_position == 'debet') {
                        $debet = $totalStock;
                    } else {
                        $kredit = $totalStock;
                    }
                    break;
                default:
                    $nominalTotal = BukuPembantuRepo::getTotalInvoiceBySaldoAwalCoaId($item->id);
                    if ($item->coa_position == 'debet') {
                        $debet = $nominalTotal;
                    } else {
                        $kredit = $nominalTotal;
                    }
            }
        }
        return $canEdit;
    }

    private function responseData($res, $successMsg, $failMsg)
    {
        if (!empty($res)) {
            $this->data['status'] = true;
            $this->data['data'] = $res;
            $this->data['message'] = $successMsg;
        } else {
            $this->data['status'] = false;
            $this->data['data'] = '';
            $this->data['message'] = $failMsg;
        }
        return response()->json($this->data);
    }

    private function responseStatus($res, $successMsg, $failMsg)
    {
        $this->data['status'] = (bool) $res;
        $this->data['message'] = $res ? $successMsg : $failMsg;
        return response()->json($this->data);
    }
}
