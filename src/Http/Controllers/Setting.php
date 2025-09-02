<?php
namespace Icso\Accounting\Http\Controllers;

use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Contents;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Setting extends Controller
{
    protected $settingRepo;

    public function __construct(SettingRepo $settingRepo)
    {
        $this->settingRepo = $settingRepo;
    }

    public function getSystemSetting(Request $request)
    {
        $userId = $request->user_id;
        $currency = SettingRepo::getOption('currency');
        $currencyFormat = SettingRepo::getOption('currency_format');
        $resetNumber = SettingRepo::getOption('reset_number');

        $this->data['status'] = true;
        if(empty($userId)){
            $this->data['data'] = array(
                'currency' => $currency,
                'currency_format' => $currencyFormat,
                'reset_number' => $resetNumber
            );
        }
        else{
            $this->data['data'] = SettingRepo::getSystemSetting();
        }
        $this->data['message'] = "Sistem setting found";
        return response()->json($this->data);
    }

    public function storeData(Request $request) {
        $currency = $request->currency;
        $currencyFormat = $request->currency_format;
        $resetNumber = $request->reset_number;
        $userId = $request->user_id;
        DB::beginTransaction();
        try {
            SettingRepo::setOption('currency',$currency, $userId);
            SettingRepo::setOption('currency_format',$currencyFormat, $userId);
            SettingRepo::setOption('reset_number',$resetNumber, $userId);
            DB::commit();
            $this->data['status'] = true;
            $this->data['data'] = array(
                'currency' => $currency,
                'currency_format' => $currencyFormat,
                'reset_number' => $resetNumber
            );
            $this->data['message'] = "Data berhasil disimpan";
        }
        catch (\Exception $error) {
           // print_r($error->getMessage());
            DB::rollback();
            $this->data['status'] = false;
            $this->data['data'] = [];
            $this->data['message'] = "Data gagal disimpan";
        }
        return response()->json($this->data);
    }

    public function storeKeyValue(Request $request) {
        DB::beginTransaction();
        try {
            if(empty($request->meta_key)){
                if(count($request->all()) > 0){
                    foreach ($request->all() as $req){
                        $metaKey = $req['meta_key'];
                        $metaValue = $req['meta_value'];
                        $userId = $req['user_id'];
                        SettingRepo::setOption($metaKey,$metaValue, $userId);
                    }

                }
                else {
                    $metaKey = $request->meta_key;
                    $metaValue = $request->meta_value;
                    $userId = $request->user_id;
                    SettingRepo::setOption($metaKey,$metaValue, $userId);
                }
            }else {
                $metaKey = $request->meta_key;
                $metaValue = $request->meta_value;
                $userId = $request->user_id;
                SettingRepo::setOption($metaKey,$metaValue, $userId);
            }

            DB::commit();
            $this->data['status'] = true;
            $this->data['data'] = '';
            $this->data['message'] = "Data berhasil disimpan";
        }
        catch (\Exception $error) {
            // print_r($error->getMessage());
            DB::rollback();
            $this->data['status'] = false;
            $this->data['data'] = '';
            $this->data['message'] = 'Data Gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function getKeyValue(Request $request) {
        $metaKey = $request->key;
        $getSetting = SettingRepo::getOption($metaKey);
        $this->data['status'] = true;
        $this->data['data'] = $getSetting;
        $this->data['company'] = Helpers::getDetailTenant();
        $this->data['message'] = "Setting found";
        return response()->json($this->data);
    }

    public function storeAkunCoa(Request $request){
        $akunUangMukaPembelian = json_decode(json_encode($request->akunUangMukaPembelian));
        $akunPpnMasukan = json_decode(json_encode($request->akunPpnMasukan));
        $akunSediaan = json_decode(json_encode($request->akunSediaan));
        $akunUtangBelumRealisasi = json_decode(json_encode($request->akunUtangBelumRealisasi));
        $akunUtangUsaha = json_decode(json_encode($request->akunUtangUsaha));
        $akunPotonganPembelian = json_decode(json_encode($request->akunPotonganPembelian));
        $akunKasBank = json_decode(json_encode($request->akunKasBank));
        $akunUangMukaPenjualan = json_decode(json_encode($request->akunUangMukaPenjualan));
        $akunPpnKeluaran = json_decode(json_encode($request->akunPpnKeluaran));
        $akunSediaanDalamPerjalanan = json_decode(json_encode($request->akunSediaanDalamPerjalanan));
        $akunPiutangUsaha = json_decode(json_encode($request->akunPiutangUsaha));
        $akunBebanPokokPenjualan = json_decode(json_encode($request->akunBebanPokokPenjualan));
        $akunPotonganPenjualan = json_decode(json_encode($request->akunPotonganPenjualan));
        $akunPenjualan = json_decode(json_encode($request->akunPenjualan));
        $akunReturPenjualan = json_decode(json_encode($request->akunReturPenjualan));
        $akunUangMukaPembelianAsetTetap = json_decode(json_encode($request->akunUangMukaPembelianAsetTetap));
        $akunBebanDiBayarDiMuka = json_decode(json_encode($request->akunBebanDiBayarDiMuka));
        $akunUtangLainLain = json_decode(json_encode($request->akunUtangLainLain));
        $akunPiutangLainLain = json_decode(json_encode($request->akunPiutangLainLain));
        $userId = $request->user_id;
        DB::beginTransaction();
        try {
            SettingRepo::setOption(SettingEnum::COA_SEDIAAN,$akunSediaan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_UANG_MUKA_PEMBELIAN,$akunUangMukaPembelian->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_PPN_MASUKAN,$akunPpnMasukan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_UTANG_USAHA,$akunUtangUsaha->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_UTANG_USAHA_BELUM_REALISASI,$akunUtangBelumRealisasi->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_POTONGAN_PEMBELIAN,$akunPotonganPembelian->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_KAS_BANK,$akunKasBank->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_UANG_MUKA_PENJUALAN,$akunUangMukaPenjualan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN,$akunSediaanDalamPerjalanan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_PPN_KELUARAN,$akunPpnKeluaran->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_PIUTANG_USAHA,$akunPiutangUsaha->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_BEBAN_POKOK_PENJUALAN,$akunBebanPokokPenjualan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_POTONGAN_PENJUALAN,$akunPotonganPenjualan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_PENJUALAN,$akunPenjualan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_RETUR_PENJUALAN,$akunReturPenjualan->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_UANG_MUKA_PEMBELIAN_ASET_TETAP,$akunUangMukaPembelianAsetTetap->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_BEBAN_DIBAYAR_DIMUKA,$akunBebanDiBayarDiMuka->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_UTANG_LAIN_LAIN,$akunUtangLainLain->id, $userId);
            SettingRepo::setOption(SettingEnum::COA_PIUTANG_LAIN_LAIN,$akunPiutangLainLain->id, $userId);
            DB::commit();
            $this->data['status'] = true;
            $this->data['data'] = '';
            $this->data['message'] = "Data berhasil disimpan";
        } catch (\Exception $error) {
            DB::rollback();
            $this->data['status'] = false;
            $this->data['data'] = '';
            $this->data['message'] = 'Data Gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function getSettingCoa()
    {
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $coaUangMukaPembelian = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN);
        $coaPpnMasukan = SettingRepo::getOptionValue(SettingEnum::COA_PPN_MASUKAN);
        $coaUtangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);
        $coaUtangUsahaBelumRealisasi = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA_BELUM_REALISASI);
        $coaPotonganPembelian = SettingRepo::getOptionValue(SettingEnum::COA_POTONGAN_PEMBELIAN);
        $coaKasBank = SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);
        $akunUangMukaPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PENJUALAN);
        $akunPpnKeluaran = SettingRepo::getOptionValue(SettingEnum::COA_PPN_KELUARAN);
        $akunSediaanDalamPerjalanan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN);
        $akunPiutangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_USAHA);
        $akunBebanPokokPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_POKOK_PENJUALAN);
        $akunPotonganPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_POTONGAN_PENJUALAN);
        $akunPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_PENJUALAN);
        $akunReturPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_RETUR_PENJUALAN);
        $akunUangMukaPembelianAsetTetap = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN_ASET_TETAP);
        $akunBebanDiBayarDiMuka = SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_DIBAYAR_DIMUKA);
        $akunUtangLainLain = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_LAIN_LAIN);
        $akunPiutangLainLain = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_LAIN_LAIN);

        $this->data['status'] = true;
        $this->data['data'] = array(
            'akunUangMukaPembelian' => Coa::where(array('id' => $coaUangMukaPembelian))->first(),
            'akunPpnMasukan' => Coa::where(array('id' => $coaPpnMasukan))->first(),
            'akunKasBank' => Coa::where(array('id' => $coaKasBank))->first(),
            'akunSediaan' => Coa::where(array('id' => $coaSediaan))->first(),
            'akunUtangBelumRealisasi' => Coa::where(array('id' => $coaUtangUsahaBelumRealisasi))->first(),
            'akunUtangUsaha' => Coa::where(array('id' => $coaUtangUsaha))->first(),
            'akunPotonganPembelian' => Coa::where(array('id' => $coaPotonganPembelian))->first(),
            'akunUangMukaPenjualan' => Coa::where(array('id' => $akunUangMukaPenjualan))->first(),
            'akunPpnKeluaran' => Coa::where(array('id' => $akunPpnKeluaran))->first(),
            'akunSediaanDalamPerjalanan' => Coa::where(array('id' => $akunSediaanDalamPerjalanan))->first(),
            'akunPiutangUsaha' => Coa::where(array('id' => $akunPiutangUsaha))->first(),
            'akunBebanPokokPenjualan' => Coa::where(array('id' => $akunBebanPokokPenjualan))->first(),
            'akunPotonganPenjualan' => Coa::where(array('id' => $akunPotonganPenjualan))->first(),
            'akunPenjualan' => Coa::where(array('id' => $akunPenjualan))->first(),
            'akunReturPenjualan' => Coa::where(array('id' => $akunReturPenjualan))->first(),
            'akunUangMukaPembelianAsetTetap' => Coa::where(array('id' => $akunUangMukaPembelianAsetTetap))->first(),
            'akunBebanDiBayarDiMuka' => Coa::where(array('id' => $akunBebanDiBayarDiMuka))->first(),
            'akunUtangLainLain' => Coa::where(array('id' => $akunUtangLainLain))->first(),
            'akunPiutangLainLain' => Coa::where(array('id' => $akunPiutangLainLain))->first(),
        );
        $this->data['message'] = "Data berhasil ditemukan";
        return response()->json($this->data);
    }

    public function storeDashboard(Request $request)
    {
        $findIsAda = Contents::where('meta_key',$request->meta_key)->first();
        if(!empty($findIsAda)){
            $findData = Contents::find($findIsAda->id);
            $findData->title = !empty($request->title) ? $request->title : $findIsAda->title;
            $findData->data = $request->data;
            $findData->updated_at = date('Y-m-d H:i:s');
            $findData->updated_by = $request->user_id;
            $findData->save();
        } else {
            $content = new Contents();
            $content->title = !empty($request->title) ? $request->title : "Master Data";
            $content->meta_key = $request->meta_key;
            $content->data = $request->data;
            $content->created_at = date('Y-m-d H:i:s');
            $content->updated_at = date('Y-m-d H:i:s');
            $content->created_by = $request->user_id;
            $content->updated_by = $request->user_id;
            $content->is_default = StatusEnum::TIDAK_AKTIF;
            $content->save();
        }

        return response()->json(['status' => true, 'message' => 'data berhasil disimpan', 'data' => '']);
    }

    public function getContent(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = Contents::where([
            ['meta_key', $request->meta_key],
            ['created_by', $request->user_id]
        ]);

        if (!empty($request->id)) {
            $model = (clone $query)->where('id', $request->id)->first();
        } else {
            $model = (clone $query)->where('is_default', StatusEnum::TIDAK_AKTIF)->first();

            if (empty($model)) {
                $model = (clone $query)->where('is_default', StatusEnum::AKTIF)->first();
            }
        }

        $getAllModel = (clone $query)->get();

        if ($model) {
            $contentArray = json_decode($model->data, true);
            return response()->json([
                'status' => true,
                'message' => 'Content found',
                'data' => [
                    'content' => $contentArray,
                    'list' => $getAllModel
                ]
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Content not found',
            'data' => ''
        ]);
    }

    public function getAllContents(Request $request)
    {
        $model = Contents::where('meta_key',$request->meta_key)->where('created_by', $request->user_id)->get();

        if ($model) {
            return response()->json(['status' => true, 'message' => 'Content found', 'data' => $model]);
        } else {
            return response()->json(['status' => false, 'message' => 'Content not found', 'data' => '']);
        }
    }
}
